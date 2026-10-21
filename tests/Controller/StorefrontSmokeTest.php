<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Renders every storefront page with real data (strict Twig variables in the test env),
 * so template errors surface in CI instead of in production.
 */
final class StorefrontSmokeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testGuestShoppingJourney(): void
    {
        [$category, $product, $variant, $soldOut] = $this->createCatalog();

        foreach (['/', '/products', '/products?q=' . urlencode($product->getName()), '/category/' . $category->getSlug(),
            '/category/' . $category->getSlug() . '?min_price=100&in_stock=1&sort=price-asc',
            '/product/' . $product->getSlug(), '/product/' . $soldOut->getSlug(),
            '/login', '/register', '/reset-password', '/contact', '/support/return-request',
            '/legal/terms', '/legal/privacy', '/legal/cookies', '/legal/returns', '/legal/imprint', '/legal/faq', '/api/v1/docs',
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful(sprintf('GET %s failed', $url));
        }

        $this->client->request('GET', '/product/' . $product->getSlug());
        self::assertSelectorExists('[data-add-to-cart][data-variant-id="' . $variant->getId() . '"]');
        self::assertSelectorExists('script[type="application/ld+json"]');
        self::assertSelectorTextContains('main', '−26 %', 'sale badge for the compare-at price');

        // Live search suggestions
        $this->client->request('GET', '/search/suggest?q=' . urlencode($product->getName()));
        self::assertResponseIsSuccessful();
        $suggest = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($product->getName(), $suggest['products'][0]['name']);
        self::assertStringStartsWith('/media/', $suggest['products'][0]['image']);
        $this->client->request('GET', '/search/suggest?q=' . urlencode($category->getName()));
        self::assertSame($category->getName(), json_decode((string) $this->client->getResponse()->getContent(), true)['categories'][0]['name']);

        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('main', $this->trans('cart.empty'));

        $this->client->request('POST', '/cart/add', ['variantId' => $variant->getId(), 'quantity' => 2]);
        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($json['success']);
        self::assertSame(2, $json['totals']['totalQuantity']);

        // More than in stock is refused with a readable message
        $this->client->request('POST', '/cart/add', ['variantId' => $variant->getId(), 'quantity' => 99]);
        self::assertResponseStatusCodeSame(409);

        $this->client->request('GET', '/cart');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-cart-line="' . $variant->getId() . '"]');
        self::assertSelectorTextContains('[data-cart-count]', '2');

        $this->client->request('GET', '/checkout');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[data-checkout-form] input[name="customer_info[email]"]');

        // Gross prices: the total is what the customer sees, VAT is contained in it (home rate without OSS)
        $this->client->request('GET', '/checkout/totals?country=FR');
        self::assertResponseIsSuccessful();
        $totals = json_decode((string) $this->client->getResponse()->getContent(), true)['totals'];
        self::assertSame($totals['subtotal'] + $totals['shippingAmount'], $totals['grandTotal']);
        self::assertSame(5198, $totals['subtotal']);
        $gross = $totals['grandTotal'];
        self::assertSame((int) round($gross - $gross / 1.19), $totals['taxTotal']);

        // Export: home VAT removed, nothing charged on top
        $this->client->request('GET', '/checkout/totals?country=US');
        $export = json_decode((string) $this->client->getResponse()->getContent(), true)['totals'];
        self::assertSame(0, $export['taxTotal']);
        self::assertFalse($export['pricesIncludeTax']);
        self::assertSame((int) round(5198 / 1.19), $export['subtotal']);
    }

    public function testOptionPickerWithSwatchesAndVariantImages(): void
    {
        [$category] = $this->createCatalog();
        $product = $this->createProduct('Swatch Shirt ' . uniqid(), $category, stock: 3, price: 1999);
        $red = $product->getVariants()->first();
        $red->setAttributes(['color' => 'Red', 'size' => 'M']);

        $blue = new ProductVariant();
        $blue->setProduct($product)->setSku('SMOKE-BLUE-' . uniqid())->setPriceAmount(1999)->setCurrency('EUR')->setAttributes(['color' => 'Blau', 'size' => 'L']);
        $product->addVariant($blue);
        $this->em->persist($blue);

        $blueImage = (new ProductMedia())->setProduct($product)->setPath('/uploads/products/blue.jpg')->setSort(1)->setVariant($blue);
        $product->addMedia($blueImage);
        $this->em->persist($blueImage);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/product/' . $product->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[title="Red"][aria-pressed="true"]');
        self::assertSelectorExists('button[title="Blau"][aria-pressed="false"]');

        $variants = json_decode($crawler->filter('script[data-variants]')->text(), true);
        $blueData = array_values(array_filter($variants, static fn (array $v): bool => $v['id'] === $blue->getId()))[0];
        self::assertSame([1], $blueData['images']);
        self::assertSame(0, $blueData['available']);
    }

    public function testAccountPagesRender(): void
    {
        [, $product] = $this->createCatalog();

        $user = new User();
        $user->setEmail('smoke-' . uniqid('', true) . '@example.com');
        $user->setPassword('password');
        $user->setFirstName('Jane');
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user);

        $this->client->request('POST', '/account/wishlist/toggle', ['productId' => $product->getId()]);
        self::assertResponseIsSuccessful();

        foreach (['/account', '/account?tab=orders', '/account?tab=invoices', '/account/wishlist', '/product/' . $product->getSlug(), '/'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful(sprintf('GET %s failed', $url));
        }

        // The heart is rendered as active without an extra request
        self::assertSelectorExists('[data-wishlist-toggle][data-product-id="' . $product->getId() . '"][aria-pressed="true"]');
    }

    /**
     * @return array{Category, Product, ProductVariant, Product}
     */
    private function createCatalog(): array
    {
        $suffix = uniqid('', false);

        $category = (new Category())->setName('Smoke Category ' . $suffix)->setSlug('smoke-category-' . $suffix);
        $this->em->persist($category);

        $product = $this->createProduct('Smoke Product ' . $suffix, $category, stock: 5, price: 2599);
        $product->getVariants()->first()->setCompareAtPriceAmount(3499);
        $soldOut = $this->createProduct('Smoke Sold Out ' . $suffix, $category, stock: 0, price: 1000);

        $this->em->flush();

        return [$category, $product, $product->getVariants()->first(), $soldOut];
    }

    private function createProduct(string $name, Category $category, int $stock, int $price): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug(strtolower(str_replace(' ', '-', $name)));
        $product->setDescription("First line\nSecond line with <b>markup</b> & ampersand");
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $product->setCategory($category);
        $this->em->persist($product);

        $media = new ProductMedia();
        $media->setProduct($product);
        $media->setPath('/uploads/products/smoke.jpg');
        $media->setAlt($name);
        $media->setSort(0);
        $product->addMedia($media);
        $this->em->persist($media);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('SMOKE-' . uniqid());
        $variant->setPriceAmount($price);
        $variant->setCurrency('EUR');
        $variant->setAttributes(['size' => 'M']);
        $product->addVariant($variant);
        $this->em->persist($variant);

        $stockItem = new StockItem();
        $stockItem->setVariant($variant);
        $stockItem->setOnHand($stock);
        $stockItem->setReserved(0);
        $variant->setStockItem($stockItem);
        $this->em->persist($stockItem);

        return $product;
    }

    private function trans(string $key): string
    {
        return $this->client->getContainer()->get('translator')->trans($key);
    }
}
