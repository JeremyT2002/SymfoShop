<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Electronics products
        $this->createProduct(
            $manager,
            'iPhone 15 Pro',
            'The latest iPhone with advanced features, A17 Pro chip, and Pro camera system.',
            'iphone-15-pro',
            $this->getReference('category_smartphones', Category::class),
            [
                ['sku' => 'IPH15P-128-BLK', 'price' => 99900, 'attributes' => ['Storage' => '128GB', 'Color' => 'Black'], 'stock' => 50],
                ['sku' => 'IPH15P-256-BLK', 'price' => 109900, 'attributes' => ['Storage' => '256GB', 'Color' => 'Black'], 'stock' => 30],
                ['sku' => 'IPH15P-512-BLK', 'price' => 129900, 'attributes' => ['Storage' => '512GB', 'Color' => 'Black'], 'stock' => 20],
            ],
            ['https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800', 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800']
        );

        $this->createProduct(
            $manager,
            'MacBook Pro 16"',
            'Powerful laptop with M3 Pro chip, perfect for professionals and creatives.',
            'macbook-pro-16',
            $this->getReference('category_laptops', Category::class),
            [
                ['sku' => 'MBP16-M3-512', 'price' => 249900, 'attributes' => ['Chip' => 'M3 Pro', 'Storage' => '512GB'], 'stock' => 15],
                ['sku' => 'MBP16-M3-1TB', 'price' => 279900, 'attributes' => ['Chip' => 'M3 Pro', 'Storage' => '1TB'], 'stock' => 10],
            ],
            ['https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=800']
        );

        $this->createProduct(
            $manager,
            'Sony WH-1000XM5 Headphones',
            'Industry-leading noise canceling with exceptional sound quality.',
            'sony-wh1000xm5',
            $this->getReference('category_headphones', Category::class),
            [
                ['sku' => 'SONY-WH1000XM5-BLK', 'price' => 39900, 'attributes' => ['Color' => 'Black'], 'stock' => 100],
                ['sku' => 'SONY-WH1000XM5-SLV', 'price' => 39900, 'attributes' => ['Color' => 'Silver'], 'stock' => 80],
            ],
            ['https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800']
        );

        // Clothing products
        $this->createProduct(
            $manager,
            'Classic Cotton T-Shirt',
            'Comfortable 100% cotton t-shirt, perfect for everyday wear.',
            'classic-cotton-tshirt',
            $this->getReference('category_mens', Category::class),
            [
                ['sku' => 'TSHIRT-S-BLK', 'price' => 1999, 'attributes' => ['Size' => 'S', 'Color' => 'Black'], 'stock' => 200],
                ['sku' => 'TSHIRT-M-BLK', 'price' => 1999, 'attributes' => ['Size' => 'M', 'Color' => 'Black'], 'stock' => 250],
                ['sku' => 'TSHIRT-L-BLK', 'price' => 1999, 'attributes' => ['Size' => 'L', 'Color' => 'Black'], 'stock' => 200],
                ['sku' => 'TSHIRT-XL-BLK', 'price' => 1999, 'attributes' => ['Size' => 'XL', 'Color' => 'Black'], 'stock' => 150],
            ],
            ['https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=800']
        );

        $this->createProduct(
            $manager,
            'Running Shoes',
            'Lightweight running shoes with excellent cushioning and support.',
            'running-shoes',
            $this->getReference('category_shoes', Category::class),
            [
                ['sku' => 'RUN-SHOE-40-BLK', 'price' => 8999, 'attributes' => ['Size' => '40', 'Color' => 'Black'], 'stock' => 50],
                ['sku' => 'RUN-SHOE-42-BLK', 'price' => 8999, 'attributes' => ['Size' => '42', 'Color' => 'Black'], 'stock' => 60],
                ['sku' => 'RUN-SHOE-44-BLK', 'price' => 8999, 'attributes' => ['Size' => '44', 'Color' => 'Black'], 'stock' => 55],
            ],
            ['https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800']
        );

        // Books
        $this->createProduct(
            $manager,
            'The Complete Guide to Symfony',
            'Comprehensive guide to building web applications with Symfony framework.',
            'complete-guide-symfony',
            $this->getReference('category_technology', Category::class),
            [
                ['sku' => 'BOOK-SYMFONY-001', 'price' => 4999, 'attributes' => ['Format' => 'Paperback'], 'stock' => 100],
                ['sku' => 'BOOK-SYMFONY-002', 'price' => 2999, 'attributes' => ['Format' => 'eBook'], 'stock' => 999],
            ],
            ['https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=800']
        );

        $this->createProduct(
            $manager,
            'Mystery Novel Collection',
            'A thrilling collection of mystery novels from bestselling authors.',
            'mystery-novel-collection',
            $this->getReference('category_fiction', Category::class),
            [
                ['sku' => 'BOOK-MYSTERY-001', 'price' => 2499, 'attributes' => ['Format' => 'Paperback'], 'stock' => 75],
            ],
            ['https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=800']
        );

        // Home & Garden
        $this->createProduct(
            $manager,
            'Smart LED Light Bulb',
            'WiFi-enabled LED bulb with color changing capabilities and app control.',
            'smart-led-bulb',
            $this->getReference('category_home', Category::class),
            [
                ['sku' => 'LED-BULB-SMART-1', 'price' => 2999, 'attributes' => ['Wattage' => '9W'], 'stock' => 150],
                ['sku' => 'LED-BULB-SMART-2', 'price' => 5499, 'attributes' => ['Wattage' => '9W', 'Pack' => '2 Pack'], 'stock' => 100],
            ],
            ['https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=800']
        );

        // Sports
        $this->createProduct(
            $manager,
            'Yoga Mat Premium',
            'Non-slip yoga mat with extra cushioning for maximum comfort.',
            'yoga-mat-premium',
            $this->getReference('category_sports', Category::class),
            [
                ['sku' => 'YOGA-MAT-PREM-1', 'price' => 3999, 'attributes' => ['Thickness' => '6mm'], 'stock' => 80],
                ['sku' => 'YOGA-MAT-PREM-2', 'price' => 4999, 'attributes' => ['Thickness' => '8mm'], 'stock' => 60],
            ],
            ['https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=800']
        );

        $manager->flush();
    }

    private function createProduct(
        ObjectManager $manager,
        string $name,
        string $description,
        string $slug,
        $category,
        array $variants,
        array $imageUrls = []
    ): void {
        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setSlug($slug);
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $product->setCreatedAt(new \DateTimeImmutable());
        $product->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($product);

        // Create variants
        foreach ($variants as $index => $variantData) {
            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setSku($variantData['sku']);
            $variant->setPriceAmount($variantData['price']);
            $variant->setCurrency('EUR');
            $variant->setAttributes($variantData['attributes'] ?? []);
            $variant->setCreatedAt(new \DateTimeImmutable());
            $variant->setUpdatedAt(new \DateTimeImmutable());

            $manager->persist($variant);

            // Create stock item
            $stockItem = new StockItem();
            $stockItem->setVariant($variant);
            $stockItem->setOnHand($variantData['stock'] ?? 0);
            $stockItem->setReserved(0);
            $variant->setStockItem($stockItem);

            $manager->persist($stockItem);
        }

        // Create media
        foreach ($imageUrls as $index => $imageUrl) {
            $media = new ProductMedia();
            $media->setProduct($product);
            $media->setPath($imageUrl);
            $media->setAlt($name . ' - Image ' . ($index + 1));
            $media->setSort($index);

            $manager->persist($media);
        }
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixture::class,
        ];
    }
}

