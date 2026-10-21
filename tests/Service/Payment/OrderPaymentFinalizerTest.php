<?php

namespace App\Tests\Service\Payment;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Repository\CouponRepository;
use App\Service\Inventory\InventoryService;
use App\Service\Payment\OrderPaymentFinalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderPaymentFinalizerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OrderPaymentFinalizer $finalizer;
    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->finalizer = $container->get(OrderPaymentFinalizer::class);
        $this->inventoryService = $container->get(InventoryService::class);
    }

    public function testMarkPaidCommitsStockOnlyOnce(): void
    {
        [$order, $stock] = $this->createPendingOrder(onHand: 10, quantity: 3);

        $this->assertTrue($this->finalizer->markPaid($order, 'test'));
        $this->assertSame('paid', $order->getStatus());

        // A second notification (return URL + webhook) must not deduct again.
        $this->assertFalse($this->finalizer->markPaid($order, 'test'));

        $this->entityManager->refresh($stock);
        $this->assertSame(7, $stock->getOnHand());
        $this->assertSame(0, $stock->getReserved());
    }

    public function testLatePaymentAfterExpiredReservationStillDeductsStock(): void
    {
        [$order, $stock] = $this->createPendingOrder(onHand: 10, quantity: 4);

        // Reservation expired and was cleaned up before the payment arrived.
        $this->inventoryService->release($order);
        $this->entityManager->refresh($stock);
        $this->assertSame(0, $stock->getReserved());

        $this->assertTrue($this->finalizer->markPaid($order, 'test'));

        $this->entityManager->refresh($stock);
        $this->assertSame(6, $stock->getOnHand());
    }

    public function testCancelUnpaidReleasesStockAndCancels(): void
    {
        [$order, $stock] = $this->createPendingOrder(onHand: 10, quantity: 2);

        $this->assertTrue($this->finalizer->cancelUnpaid($order, 'test'));
        $this->assertSame('cancelled', $order->getStatus());

        $this->entityManager->refresh($stock);
        $this->assertSame(10, $stock->getOnHand());
        $this->assertSame(0, $stock->getReserved());
    }

    public function testCancelUnpaidLeavesPaidOrderAlone(): void
    {
        [$order, $stock] = $this->createPendingOrder(onHand: 10, quantity: 2);
        $this->finalizer->markPaid($order, 'test');

        // e.g. the customer revisits the PayPal cancel URL after paying
        $this->assertFalse($this->finalizer->cancelUnpaid($order, 'test'));
        $this->assertSame('paid', $order->getStatus());

        $this->entityManager->refresh($stock);
        $this->assertSame(8, $stock->getOnHand());
    }

    public function testPaidOrderCountsTowardsCouponUsage(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('FIN' . strtoupper(substr(uniqid(), -6)));
        $coupon->setType(CouponType::PERCENTAGE);
        $coupon->setValue(10);
        $coupon->setIsActive(true);
        $this->entityManager->persist($coupon);
        $this->entityManager->flush();

        $couponRepository = static::getContainer()->get(CouponRepository::class);
        $this->assertSame(0, $couponRepository->countUsages($coupon));

        [$order] = $this->createPendingOrder(onHand: 5, quantity: 1);
        $order->setCouponCode($coupon->getCode());
        $this->entityManager->flush();
        $this->assertSame(0, $couponRepository->countUsages($coupon), 'unpaid orders do not count');

        $this->finalizer->markPaid($order, 'test');
        $this->assertSame(1, $couponRepository->countUsages($coupon));
    }

    /**
     * @return array{Order, StockItem}
     */
    private function createPendingOrder(int $onHand, int $quantity): array
    {
        $product = new Product();
        $product->setName('Finalizer Product');
        $product->setSlug('finalizer-product-' . uniqid('', true));
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $this->entityManager->persist($product);

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('FIN-' . uniqid());
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $this->entityManager->persist($variant);

        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand($onHand);
        $stock->setReserved(0);
        $this->entityManager->persist($stock);

        $order = new Order();
        $order->setOrderNumber('ORD-FIN-' . uniqid());
        $order->setEmail('finalizer@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(1000 * $quantity);
        $order->setTaxTotal(0);
        $order->setGrandTotal(1000 * $quantity);

        $item = new OrderItem();
        $item->setSku($variant->getSku());
        $item->setProductVariant($variant);
        $item->setNameSnapshot('Finalizer Product');
        $item->setQuantity($quantity);
        $item->setUnitPriceAmount(1000);
        $item->setTaxRate('0.1900');
        $item->setTotalAmount(1000 * $quantity);
        $order->addItem($item);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->assertTrue($this->inventoryService->reserve($order)['success']);
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        return [$order, $stock];
    }
}
