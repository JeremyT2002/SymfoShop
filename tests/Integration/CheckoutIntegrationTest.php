<?php

namespace App\Tests\Integration;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Repository\OrderRepository;
use App\Service\Cart\CartService;
use App\Service\Checkout\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CheckoutIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CartService $cartService;
    private CheckoutService $checkoutService;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->cartService = $container->get(CartService::class);
        $this->checkoutService = $container->get(CheckoutService::class);
        $this->orderRepository = $container->get(OrderRepository::class);

        // Clear cart before each test
        $this->cartService->clear();
    }

    public function testCartToOrderDraftCreation(): void
    {
        // Create test product and variant
        $product = $this->createTestProduct();
        $variant = $this->createTestVariant($product, 1, 1999, 'EUR');
        
        $this->entityManager->persist($product);
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        // Add items to cart
        $this->cartService->add($variant->getId(), 2);
        $this->cartService->add($variant->getId(), 1); // Should increment to 3

        // Verify cart has items
        $items = $this->cartService->getDetailedItems();
        $this->assertCount(1, $items);
        $this->assertEquals(3, $items[0]['quantity']);

        // Create customer info and address
        $customerInfo = new CustomerInfoDTO(
            'test@example.com',
            'John',
            'Doe',
            '+1234567890'
        );

        $shippingAddress = new AddressDTO(
            '123 Main Street',
            'New York',
            '10001',
            'US',
            'NY'
        );

        // Create order
        $order = $this->checkoutService->createOrder($customerInfo, $shippingAddress);

        // Verify order was created
        $this->assertInstanceOf(Order::class, $order);
        $this->assertNotNull($order->getId());
        $this->assertNotEmpty($order->getOrderNumber());
        $this->assertStringStartsWith('ORD-', $order->getOrderNumber());
        $this->assertEquals('test@example.com', $order->getEmail());
        $this->assertEquals('EUR', $order->getCurrency());
        $this->assertEquals('new', $order->getStatus());

        // Verify order totals (3 * 1999 = 5997, tax 20% = 1199, total = 7196)
        $this->assertEquals(5997, $order->getSubtotal());
        $this->assertEquals(1199, $order->getTaxTotal()); // 5997 * 0.20 = 1199.4, rounded to 1199
        $this->assertEquals(7196, $order->getGrandTotal());

        // Verify order items
        $orderItems = $order->getItems();
        $this->assertCount(1, $orderItems);

        $orderItem = $orderItems->first();
        $this->assertEquals($variant->getSku(), $orderItem->getSku());
        $this->assertEquals($product->getName(), $orderItem->getNameSnapshot());
        $this->assertEquals(3, $orderItem->getQuantity());
        $this->assertEquals(1999, $orderItem->getUnitPriceAmount()); // Frozen price
        $this->assertEquals('0.2000', $orderItem->getTaxRate());
        $this->assertEquals(5997, $orderItem->getTotalAmount()); // Frozen total

        // Verify cart was cleared
        $cartItems = $this->cartService->getDetailedItems();
        $this->assertCount(0, $cartItems);

        // Verify order can be retrieved by order number
        $retrievedOrder = $this->orderRepository->findOneByOrderNumber($order->getOrderNumber());
        $this->assertNotNull($retrievedOrder);
        $this->assertEquals($order->getId(), $retrievedOrder->getId());
    }

    public function testOrderWithMultipleItems(): void
    {
        // Create test products and variants
        $product1 = $this->createTestProduct('Product 1');
        $product2 = $this->createTestProduct('Product 2');
        $variant1 = $this->createTestVariant($product1, 1, 1000, 'EUR');
        $variant2 = $this->createTestVariant($product2, 2, 2000, 'EUR');

        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->persist($variant1);
        $this->entityManager->persist($variant2);
        $this->entityManager->flush();

        // Add multiple items to cart
        $this->cartService->add($variant1->getId(), 2);
        $this->cartService->add($variant2->getId(), 1);

        // Create order
        $customerInfo = new CustomerInfoDTO('test@example.com', 'Jane', 'Smith');
        $shippingAddress = new AddressDTO('456 Oak Ave', 'Los Angeles', '90001', 'US');

        $order = $this->checkoutService->createOrder($customerInfo, $shippingAddress);

        // Verify order has 2 items
        $this->assertCount(2, $order->getItems());

        // Verify totals: (2 * 1000) + (1 * 2000) = 4000, tax = 800, total = 4800
        $this->assertEquals(4000, $order->getSubtotal());
        $this->assertEquals(800, $order->getTaxTotal());
        $this->assertEquals(4800, $order->getGrandTotal());
    }

    public function testOrderPriceSnapshotsAreFrozen(): void
    {
        $product = $this->createTestProduct();
        $variant = $this->createTestVariant($product, 1, 1000, 'EUR');

        $this->entityManager->persist($product);
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        $this->cartService->add($variant->getId(), 1);

        $customerInfo = new CustomerInfoDTO('test@example.com', 'Test', 'User');
        $shippingAddress = new AddressDTO('123 St', 'City', '12345', 'US');

        $order = $this->checkoutService->createOrder($customerInfo, $shippingAddress);

        // Change the variant price after order creation
        $variant->setPriceAmount(2000);
        $this->entityManager->flush();

        // Verify order item still has the original frozen price
        $orderItem = $order->getItems()->first();
        $this->assertEquals(1000, $orderItem->getUnitPriceAmount()); // Original price, not updated
    }

    private function createTestProduct(string $name = 'Test Product'): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug(strtolower(str_replace(' ', '-', $name)) . '-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');

        return $product;
    }

    private function createTestVariant(Product $product, int $id, int $priceAmount, string $currency): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('SKU-' . $id . '-' . uniqid());
        $variant->setPriceAmount($priceAmount);
        $variant->setCurrency($currency);
        $variant->setAttributes([]);

        return $variant;
    }
}

