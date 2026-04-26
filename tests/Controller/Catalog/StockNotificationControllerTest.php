<?php

namespace App\Tests\Controller\Catalog;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StockNotificationControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        putenv('MAILER_DSN=null://null');
        $_ENV['MAILER_DSN'] = 'null://null';
        $_SERVER['MAILER_DSN'] = 'null://null';
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        static::ensureKernelShutdown();
    }

    public function testGuestSubscriptionRequiresPrivacyConsent(): void
    {
        $client = static::createClient();
        $variant = $this->createVariant();
        $crawler = $client->request('GET', '/product/' . $variant->getProduct()->getSlug());
        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/stock-notifications/subscribe', [
            '_token' => $token,
            'variant_id' => $variant->getId(),
            'email' => 'guest@example.com',
            'privacy_consent' => '0',
        ]);

        $this->assertResponseRedirects('/product/' . $variant->getProduct()->getSlug());
        $row = $this->entityManager->getRepository(StockNotification::class)->findOneBy(['email' => 'guest@example.com']);
        $this->assertNull($row);
    }

    private function createVariant(): ProductVariant
    {
        $product = new Product();
        $product->setName('Notify Product ' . uniqid('', false));
        $product->setSlug('notify-product-' . uniqid('', false));
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('NOTIFY-' . strtoupper(substr(uniqid('', false), -8)));
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);

        $this->entityManager->persist($product);
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        return $variant;
    }
}

