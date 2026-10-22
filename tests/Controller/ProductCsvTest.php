<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProductCsvTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $suffix;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
        $this->suffix = strtolower(uniqid());

        $admin = (new User())->setEmail('csv-admin-' . $this->suffix . '@example.com')->setPassword('x')->setRoles(['ROLE_ADMIN']);
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    public function testExportContainsVariants(): void
    {
        $variant = $this->createVariant(price: 1999, stock: 7);

        $this->client->request('GET', '/admin/products/export.csv');
        self::assertResponseIsSuccessful();
        $csv = $this->client->getInternalResponse()->getContent();

        self::assertStringStartsWith("\xEF\xBB\xBFsku,product_slug,product_name,status", $csv);
        self::assertStringContainsString($variant->getSku() . ',csv-shirt-' . $this->suffix . ',"CSV Shirt",active,,standard,19.99,,EUR,7,size=M,', $csv);
    }

    public function testPreviewChangesNothingThenApplyCreatesAndUpdates(): void
    {
        $existing = $this->createVariant(price: 1999, stock: 7);
        $category = (new Category())->setName('CSV Cat')->setSlug('csv-cat-' . $this->suffix);
        $this->em->persist($category);
        $this->em->flush();

        // German Excel: semicolons and decimal commas
        $csv = "sku;product_slug;product_name;status;category;price;compare_at_price;stock;attributes\n"
            . "{$existing->getSku()};;;;;24,90;29,90;3;\n"
            . "NEW-{$this->suffix};csv-mug-{$this->suffix};CSV Mug;active;csv-cat-{$this->suffix};9,50;;12;\"color=Blue; size=L\"\n";

        $this->upload($csv, apply: false);
        self::assertSelectorTextContains('.alert-info', '1');
        $this->em->clear();
        self::assertSame(1999, $this->variant($existing->getSku())->getPriceAmount());
        self::assertNull($this->variant('NEW-' . $this->suffix));

        $this->upload($csv, apply: true);
        self::assertSelectorExists('.alert-success');
        $this->em->clear();

        $updated = $this->variant($existing->getSku());
        self::assertSame(2490, $updated->getPriceAmount());
        self::assertSame(2990, $updated->getCompareAtPriceAmount());
        self::assertSame(3, $updated->getStockItem()->getOnHand());
        self::assertSame('CSV Shirt', $updated->getProduct()->getName(), 'empty columns keep the value');

        $new = $this->variant('NEW-' . $this->suffix);
        self::assertSame(950, $new->getPriceAmount());
        self::assertSame(['color' => 'Blue', 'size' => 'L'], $new->getAttributes());
        self::assertSame(12, $new->getStockItem()->getOnHand());
        self::assertSame('CSV Mug', $new->getProduct()->getName());
        self::assertSame(ProductStatus::ACTIVE, $new->getProduct()->getStatus());
        self::assertSame('csv-cat-' . $this->suffix, $new->getProduct()->getCategory()?->getSlug());
    }

    public function testFileWithErrorsChangesNothing(): void
    {
        $existing = $this->createVariant(price: 1999, stock: 7);

        $csv = "sku,product_name,price,category\n"
            . "{$existing->getSku()},,5.00,\n"
            . "BAD-{$this->suffix},Bad,abc,\n"
            . "OTHER-{$this->suffix},Other,1.00,no-such-category\n";

        $this->upload($csv, apply: true);
        self::assertSelectorTextContains('.alert-error', 'Invalid price "abc"');
        self::assertSelectorTextContains('.alert-error', 'Unknown category "no-such-category"');
        $this->em->clear();
        self::assertSame(1999, $this->variant($existing->getSku())->getPriceAmount());
    }

    public function testBulkSaleStockAndPriceChange(): void
    {
        $variant = $this->createVariant(price: 2000, stock: 1);
        $productId = $variant->getProduct()->getId();

        $this->bulk($productId, 'sale_start', '25');
        $this->em->clear();
        $v = $this->variant($variant->getSku());
        self::assertSame(1500, $v->getPriceAmount());
        self::assertSame(2000, $v->getCompareAtPriceAmount());

        // A second sale is based on the regular price, not the reduced one
        $this->bulk($productId, 'sale_start', '10');
        $this->em->clear();
        self::assertSame(1800, $this->variant($variant->getSku())->getPriceAmount());

        $this->bulk($productId, 'sale_end', '');
        $this->bulk($productId, 'price_percent', '+5');
        $this->bulk($productId, 'stock_set', '40');
        $this->em->clear();
        $v = $this->variant($variant->getSku());
        self::assertSame(2100, $v->getPriceAmount());
        self::assertNull($v->getCompareAtPriceAmount());
        self::assertSame(40, $v->getStockItem()->getOnHand());

        $this->bulk($productId, 'price_percent', '-150');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-error, [role="alert"]');
    }

    private function bulk(int $productId, string $action, string $value): void
    {
        $crawler = $this->client->request('GET', '/admin/products');
        $token = $crawler->filter('form[action$="/bulk/edit"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/products/bulk/edit', [
            '_token' => $token,
            'ids' => (string) $productId,
            'action' => $action,
            'value' => $value,
        ]);
        self::assertResponseRedirects('/admin/products');
    }

    private function upload(string $csv, bool $apply): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        $crawler = $this->client->request('GET', '/admin/products/import');
        $form = $crawler->filter('form[enctype="multipart/form-data"]')->form();
        $form['file']->upload($path);
        if ($apply) {
            $form['apply']->tick();
        }
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        @unlink($path);
    }

    private function createVariant(int $price, int $stock): ProductVariant
    {
        $product = (new Product())
            ->setName('CSV Shirt')
            ->setSlug('csv-shirt-' . $this->suffix)
            ->setStatus(ProductStatus::ACTIVE)
            ->setTaxClass('standard');
        $variant = (new ProductVariant())->setProduct($product)->setSku('CSV-' . $this->suffix)->setPriceAmount($price)->setCurrency('EUR')->setAttributes(['size' => 'M']);
        $product->addVariant($variant);
        $stockItem = (new StockItem())->setVariant($variant)->setOnHand($stock)->setReserved(0);
        $variant->setStockItem($stockItem);

        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stockItem);
        $this->em->flush();

        return $variant;
    }

    private function variant(string $sku): ?ProductVariant
    {
        return $this->em->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]);
    }
}
