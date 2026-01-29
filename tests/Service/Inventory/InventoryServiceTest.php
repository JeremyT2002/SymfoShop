<?php

namespace App\Tests\Service\Inventory;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderReservation;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Repository\OrderReservationRepository;
use App\Repository\StockItemRepository;
use App\Service\Inventory\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InventoryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InventoryService $inventoryService;
    private StockItemRepository $stockItemRepository;
    private OrderReservationRepository $reservationRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->inventoryService = $kernel->getContainer()->get(InventoryService::class);
        $this->stockItemRepository = $this->entityManager->getRepository(StockItem::class);
        $this->reservationRepository = $this->entityManager->getRepository(OrderReservation::class);

        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\OrderReservation')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\StockItem')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\OrderItem')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Order')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ProductVariant')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Product')->execute();
    }

    public function testReserveInventorySuccessfully(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 0);
        $order = $this->createOrderWithItem($variant, 10);

        $result = $this->inventoryService->reserve($order);

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(10, $stockItem->getReserved());
        $this->assertEquals(90, $stockItem->getAvailable());

        $reservations = $this->reservationRepository->findByOrder($order);
        $this->assertCount(1, $reservations);
        $this->assertEquals(10, $reservations[0]->getQuantity());
    }

    public function testReserveInventoryInsufficientStock(): void
    {
        $variant = $this->createTestVariant();
        $this->createStockItem($variant, 5, 0);
        $order = $this->createOrderWithItem($variant, 10);

        $result = $this->inventoryService->reserve($order);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Insufficient stock', $result['errors'][0]);
    }

    public function testReserveInventoryWithExistingReservations(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 20); // 20 already reserved
        $order = $this->createOrderWithItem($variant, 10);

        $result = $this->inventoryService->reserve($order);

        $this->assertTrue($result['success']);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(30, $stockItem->getReserved()); // 20 + 10
        $this->assertEquals(70, $stockItem->getAvailable()); // 100 - 30
    }

    public function testCommitInventory(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 0);
        $order = $this->createOrderWithItem($variant, 10);

        // First reserve
        $this->inventoryService->reserve($order);

        // Then commit
        $this->inventoryService->commit($order);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(90, $stockItem->getOnHand()); // Reduced by 10
        $this->assertEquals(0, $stockItem->getReserved()); // Reservation released

        $reservations = $this->reservationRepository->findByOrder($order);
        $this->assertEmpty($reservations); // Reservation should be removed
    }

    public function testReleaseInventory(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 0);
        $order = $this->createOrderWithItem($variant, 10);

        // First reserve
        $this->inventoryService->reserve($order);

        // Then release
        $this->inventoryService->release($order);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(100, $stockItem->getOnHand()); // Unchanged
        $this->assertEquals(0, $stockItem->getReserved()); // Reservation released

        $reservations = $this->reservationRepository->findByOrder($order);
        $this->assertEmpty($reservations); // Reservation should be removed
    }

    public function testConcurrentReservations(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 0);

        $order1 = $this->createOrderWithItem($variant, 50);
        $order2 = $this->createOrderWithItem($variant, 50);
        $order3 = $this->createOrderWithItem($variant, 10); // This should fail

        // Reserve first two orders
        $result1 = $this->inventoryService->reserve($order1);
        $result2 = $this->inventoryService->reserve($order2);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);

        // Third reservation should fail (only 0 available)
        $result3 = $this->inventoryService->reserve($order3);
        $this->assertFalse($result3['success']);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(100, $stockItem->getReserved());
        $this->assertEquals(0, $stockItem->getAvailable());
    }

    public function testReleaseExpiredReservations(): void
    {
        $variant = $this->createTestVariant();
        $stockItem = $this->createStockItem($variant, 100, 0);
        $order = $this->createOrderWithItem($variant, 10);

        // Reserve
        $this->inventoryService->reserve($order);

        // Manually expire the reservation
        $reservations = $this->reservationRepository->findByOrder($order);
        $reservation = $reservations[0];
        $reservation->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        // Release expired
        $count = $this->inventoryService->releaseExpiredReservations();

        $this->assertEquals(1, $count);

        $this->entityManager->refresh($stockItem);
        $this->assertEquals(0, $stockItem->getReserved());

        $reservations = $this->reservationRepository->findByOrder($order);
        $this->assertEmpty($reservations);
    }

    public function testReserveMultipleVariants(): void
    {
        $variant1 = $this->createTestVariant('SKU-001');
        $variant2 = $this->createTestVariant('SKU-002');
        $stockItem1 = $this->createStockItem($variant1, 100, 0);
        $stockItem2 = $this->createStockItem($variant2, 50, 0);

        $order = new Order();
        $order->setOrderNumber('ORD-TEST-001');
        $order->setEmail('test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(0);
        $order->setTaxTotal(0);
        $order->setGrandTotal(0);
        $this->entityManager->persist($order);

        $item1 = new OrderItem();
        $item1->setSku($variant1->getSku());
        $item1->setNameSnapshot('Product 1');
        $item1->setQuantity(20);
        $item1->setUnitPriceAmount(1000);
        $item1->setTaxRate('0.2000');
        $item1->setTotalAmount(20000);
        $order->addItem($item1);

        $item2 = new OrderItem();
        $item2->setSku($variant2->getSku());
        $item2->setNameSnapshot('Product 2');
        $item2->setQuantity(30);
        $item2->setUnitPriceAmount(2000);
        $item2->setTaxRate('0.2000');
        $item2->setTotalAmount(60000);
        $order->addItem($item2);

        $this->entityManager->flush();

        $result = $this->inventoryService->reserve($order);

        $this->assertTrue($result['success']);

        $this->entityManager->refresh($stockItem1);
        $this->entityManager->refresh($stockItem2);

        $this->assertEquals(20, $stockItem1->getReserved());
        $this->assertEquals(30, $stockItem2->getReserved());
    }

    public function testReserveWithPartialFailure(): void
    {
        $variant1 = $this->createTestVariant('SKU-001');
        $variant2 = $this->createTestVariant('SKU-002');
        $this->createStockItem($variant1, 100, 0);
        $this->createStockItem($variant2, 5, 0); // Insufficient

        $order = new Order();
        $order->setOrderNumber('ORD-TEST-002');
        $order->setEmail('test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(0);
        $order->setTaxTotal(0);
        $order->setGrandTotal(0);
        $this->entityManager->persist($order);

        $item1 = new OrderItem();
        $item1->setSku($variant1->getSku());
        $item1->setNameSnapshot('Product 1');
        $item1->setQuantity(20);
        $item1->setUnitPriceAmount(1000);
        $item1->setTaxRate('0.2000');
        $item1->setTotalAmount(20000);
        $order->addItem($item1);

        $item2 = new OrderItem();
        $item2->setSku($variant2->getSku());
        $item2->setNameSnapshot('Product 2');
        $item2->setQuantity(10); // More than available
        $item2->setUnitPriceAmount(2000);
        $item2->setTaxRate('0.2000');
        $item2->setTotalAmount(20000);
        $order->addItem($item2);

        $this->entityManager->flush();

        $result = $this->inventoryService->reserve($order);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);

        // Verify no reservations were created (transaction rollback)
        $reservations = $this->reservationRepository->findByOrder($order);
        $this->assertEmpty($reservations);
    }

    private function createTestVariant(string $sku = 'TEST-SKU'): ProductVariant
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setSlug('test-product');
        $product->setStatus('active');
        $product->setTaxClass('standard');
        $this->entityManager->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku($sku);
        $variant->setName('Test Variant');
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        return $variant;
    }

    private function createStockItem(ProductVariant $variant, int $onHand, int $reserved): StockItem
    {
        $stockItem = new StockItem();
        $stockItem->setVariant($variant);
        $stockItem->setOnHand($onHand);
        $stockItem->setReserved($reserved);
        $this->entityManager->persist($stockItem);
        $this->entityManager->flush();

        return $stockItem;
    }

    private function createOrderWithItem(ProductVariant $variant, int $quantity): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-TEST-' . uniqid());
        $order->setEmail('test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(0);
        $order->setTaxTotal(0);
        $order->setGrandTotal(0);
        $this->entityManager->persist($order);

        $item = new OrderItem();
        $item->setSku($variant->getSku());
        $item->setNameSnapshot($variant->getProduct()->getName());
        $item->setQuantity($quantity);
        $item->setUnitPriceAmount($variant->getPriceAmount());
        $item->setTaxRate('0.2000');
        $item->setTotalAmount($variant->getPriceAmount() * $quantity);
        $order->addItem($item);

        $this->entityManager->flush();

        return $order;
    }
}

