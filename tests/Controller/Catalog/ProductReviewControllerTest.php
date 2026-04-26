<?php

namespace App\Tests\Controller\Catalog;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductReviewControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        static::ensureKernelShutdown();
    }

    public function testVerifiedBuyerCanSubmitReview(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        [$product, $variant] = $this->createProductWithVariant();
        $this->createPaidOrderForUserAndVariant($user, $variant);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/product/' . $product->getSlug());
        $reviewForm = $crawler->filter('form')->reduce(
            static fn (\Symfony\Component\DomCrawler\Crawler $node): bool => $node->filter('select[name="rating"]')->count() > 0
        );
        $token = (string) $reviewForm->first()->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/product/' . $product->getSlug(), [
            '_token' => $token,
            'rating' => 5,
            'title' => 'Excellent',
            'body' => 'Fast delivery and perfect quality.',
        ]);

        $this->assertResponseRedirects('/product/' . $product->getSlug());
        $review = $this->entityManager->getRepository(ProductReview::class)->findOneBy([
            'product' => $product,
            'user' => $user,
        ]);
        $this->assertNotNull($review);
        $this->assertTrue($review->isVerifiedPurchase());
        $this->assertFalse($review->isApproved());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('reviewer-' . uniqid('', false) . '@example.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function createProductWithVariant(): array
    {
        $product = new Product();
        $product->setName('Review Product ' . uniqid('', false));
        $product->setSlug('review-product-' . uniqid('', false));
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('REV-' . strtoupper(substr(uniqid('', false), -8)));
        $variant->setPriceAmount(1299);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);

        $this->entityManager->persist($product);
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        return [$product, $variant];
    }

    private function createPaidOrderForUserAndVariant(User $user, ProductVariant $variant): void
    {
        $order = new Order();
        $order->setOrderNumber('ORD-REV-' . strtoupper(substr(uniqid('', false), -8)));
        $order->setEmail($user->getEmail() ?? 'reviewer@example.com');
        $order->setUser($user);
        $order->setCurrency('EUR');
        $order->setStatus('paid');
        $order->setSubtotal(1299);
        $order->setTaxTotal(247);
        $order->setGrandTotal(1546);

        $item = new OrderItem();
        $item->setOrder($order);
        $item->setSku($variant->getSku());
        $item->setProductVariant($variant);
        $item->setNameSnapshot($variant->getProduct()->getName() ?? 'Product');
        $item->setQuantity(1);
        $item->setUnitPriceAmount($variant->getPriceAmount());
        $item->setTaxRate('0.1900');
        $item->setTotalAmount(1299);
        $order->addItem($item);

        $this->entityManager->persist($order);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

