<?php

declare(strict_types=1);

namespace App\Tests\Controller\Seo;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoNoIndexTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        static::ensureKernelShutdown();
    }

    public function testNoIndexAffectsRobotsMetaAndSitemap(): void
    {
        $productNoIndex = new Product();
        $productNoIndex->setName('SEO NoIndex Product ' . uniqid('', false));
        $productNoIndex->setSlug('seo-noindex-product-' . uniqid('', false));
        $productNoIndex->setDescription('SEO NoIndex Product description');
        $productNoIndex->setStatus(ProductStatus::ACTIVE);
        $productNoIndex->setSeoTitle('SEO NoIndex Product');
        $productNoIndex->setSeoDescription('SEO NoIndex Product description for meta tags.');
        $productNoIndex->setSeoNoIndex(true);

        $productIndex = new Product();
        $productIndex->setName('SEO Index Product ' . uniqid('', false));
        $productIndex->setSlug('seo-index-product-' . uniqid('', false));
        $productIndex->setDescription('SEO Index Product description');
        $productIndex->setStatus(ProductStatus::ACTIVE);
        $productIndex->setSeoTitle('SEO Index Product');
        $productIndex->setSeoDescription('SEO Index Product description for meta tags.');
        $productIndex->setSeoNoIndex(false);

        $categoryNoIndex = new Category();
        $categoryNoIndex->setName('SEO NoIndex Category ' . uniqid('', false));
        $categoryNoIndex->setSlug('seo-noindex-category-' . uniqid('', false));
        $categoryNoIndex->setSeoTitle('SEO NoIndex Category');
        $categoryNoIndex->setSeoDescription('SEO NoIndex Category description for meta tags.');
        $categoryNoIndex->setSeoNoIndex(true);

        $categoryIndex = new Category();
        $categoryIndex->setName('SEO Index Category ' . uniqid('', false));
        $categoryIndex->setSlug('seo-index-category-' . uniqid('', false));
        $categoryIndex->setSeoTitle('SEO Index Category');
        $categoryIndex->setSeoDescription('SEO Index Category description for meta tags.');
        $categoryIndex->setSeoNoIndex(false);

        $this->entityManager->persist($productNoIndex);
        $this->entityManager->persist($productIndex);
        $this->entityManager->persist($categoryNoIndex);
        $this->entityManager->persist($categoryIndex);
        $this->entityManager->flush();

        $client = static::createClient();

        // Product: robots meta + twitter tags
        $client->request('GET', '/product/' . $productNoIndex->getSlug());
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('content="noindex,nofollow"', $html);
        $this->assertStringContainsString('twitter:title', $html);
        $this->assertStringContainsString('SEO NoIndex Product - SymfoShop', $html);

        // Product: indexable variant
        $client->request('GET', '/product/' . $productIndex->getSlug());
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('content="index,follow"', $html);

        // Category: robots meta + twitter tags
        $client->request('GET', '/category/' . $categoryNoIndex->getSlug());
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('content="noindex,nofollow"', $html);
        $this->assertStringContainsString('twitter:title', $html);
        $this->assertStringContainsString('SEO NoIndex Category - SymfoShop', $html);

        // Category: indexable variant
        $client->request('GET', '/category/' . $categoryIndex->getSlug());
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('content="index,follow"', $html);

        // Sitemap: noindex pages must be excluded
        $client->request('GET', '/sitemap.xml');
        $this->assertResponseIsSuccessful();
        $xml = (string) $client->getResponse()->getContent();

        $this->assertStringNotContainsString('/product/' . $productNoIndex->getSlug(), $xml);
        $this->assertStringNotContainsString('/category/' . $categoryNoIndex->getSlug(), $xml);

        $this->assertStringContainsString('/product/' . $productIndex->getSlug(), $xml);
        $this->assertStringContainsString('/category/' . $categoryIndex->getSlug(), $xml);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

