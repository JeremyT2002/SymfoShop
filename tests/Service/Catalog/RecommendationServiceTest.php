<?php

declare(strict_types=1);

namespace App\Tests\Service\Catalog;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Service\Catalog\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecommendationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testBoughtTogetherComesFirstThenSameCategory(): void
    {
        $category = (new Category())->setName('Reco ' . uniqid())->setSlug('reco-' . uniqid());
        $this->em->persist($category);
        $otherCategory = (new Category())->setName('Reco other ' . uniqid())->setSlug('reco-o-' . uniqid());
        $this->em->persist($otherCategory);

        $shirt = $this->product('Shirt', $category);
        $socks = $this->product('Socks', $otherCategory);      // bought together, different category
        $cap = $this->product('Cap', $category);               // same category only
        $draft = $this->product('Draft', $category, ProductStatus::DRAFT);

        $this->paidOrder([$shirt, $socks]);
        $this->paidOrder([$shirt, $socks, $draft]);
        $this->em->flush();

        $ids = array_map(
            static fn (Product $p): ?int => $p->getId(),
            static::getContainer()->get(RecommendationService::class)->forProducts([$shirt], 4)
        );

        self::assertSame($socks->getId(), $ids[0]);
        self::assertContains($cap->getId(), $ids);
        self::assertNotContains($shirt->getId(), $ids);
        self::assertNotContains($draft->getId(), $ids);
    }

    private function product(string $name, Category $category, ProductStatus $status = ProductStatus::ACTIVE): Product
    {
        $product = (new Product())
            ->setName($name . ' ' . uniqid())
            ->setSlug(strtolower($name) . '-' . uniqid())
            ->setStatus($status)
            ->setTaxClass('standard')
            ->setCategory($category);
        $variant = (new ProductVariant())->setSku('RECO-' . uniqid())->setPriceAmount(1000)->setCurrency('EUR');
        $product->addVariant($variant);
        $this->em->persist($product);
        $this->em->persist($variant);

        return $product;
    }

    /**
     * @param list<Product> $products
     */
    private function paidOrder(array $products): void
    {
        $order = (new Order())
            ->setOrderNumber('ORD-RECO-' . uniqid())
            ->setEmail('reco@example.com')
            ->setCurrency('EUR')
            ->setStatus('paid')
            ->setSubtotal(1000)
            ->setTaxTotal(0)
            ->setGrandTotal(1000);
        foreach ($products as $product) {
            $variant = $product->getVariants()->first();
            $order->addItem((new OrderItem())
                ->setSku($variant->getSku())
                ->setProductVariant($variant)
                ->setNameSnapshot($product->getName())
                ->setQuantity(1)
                ->setUnitPriceAmount(1000)
                ->setTaxRate('0.1900')
                ->setTotalAmount(1000));
        }
        $this->em->persist($order);
    }
}
