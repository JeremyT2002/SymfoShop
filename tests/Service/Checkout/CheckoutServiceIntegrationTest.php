<?php

namespace App\Tests\Service\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
use App\Service\Cart\CartService;
use App\Service\Checkout\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CheckoutServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CheckoutService $checkoutService;
    private CartService $cartService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->checkoutService = $container->get(CheckoutService::class);
        $this->cartService = $container->get(CartService::class);

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $container->get(RequestStack::class)->push($request);
        $this->cartService->clear();
    }

    public function testValidateCartEmpty(): void
    {
        $r = $this->checkoutService->validateCart();
        $this->assertFalse($r['valid']);
        $this->assertContains('Cart is empty', $r['errors']);
    }

    public function testValidateCartAndInventoryWithStock(): void
    {
        $product = new Product();
        $product->setName('Co ' . uniqid());
        $product->setSlug('co-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-CHK-' . uniqid());
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand(50);
        $stock->setReserved(0);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stock);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 2);

        $v = $this->checkoutService->validateCart();
        $this->assertTrue($v['valid']);

        $inv = $this->checkoutService->validateInventory();
        $this->assertTrue($inv['valid']);
    }

    public function testValidateInventoryMissingStock(): void
    {
        $product = new Product();
        $product->setName('No stock ' . uniqid());
        $product->setSlug('ns-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-NS-' . uniqid());
        $variant->setPriceAmount(500);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 1);

        $inv = $this->checkoutService->validateInventory();
        $this->assertFalse($inv['valid']);
        $this->assertNotEmpty($inv['errors']);
    }

    public function testCalculateTotalsClearsInvalidCoupon(): void
    {
        $product = new Product();
        $product->setName('Coupon ' . uniqid());
        $product->setSlug('cp-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-CP-' . uniqid());
        $variant->setPriceAmount(2000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand(10);
        $stock->setReserved(0);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stock);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 1);
        $this->cartService->setCouponCode('DOES_NOT_EXIST');

        $totals = $this->checkoutService->calculateTotals();
        $this->assertSame(0, $totals['discount']);
        $this->assertNull($totals['couponCode']);
        $this->assertNull($this->cartService->getCouponCode());
    }

    public function testCalculateTotalsAppliesValidPercentageCoupon(): void
    {
        $coupon = new Coupon();
        $coupon->setCode('CHK10-' . strtoupper(substr(uniqid(), -6)));
        $coupon->setType(CouponType::PERCENTAGE);
        $coupon->setValue(10);
        $this->em->persist($coupon);

        $product = new Product();
        $product->setName('Valid cp ' . uniqid());
        $product->setSlug('vcp-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-VCP-' . uniqid());
        $variant->setPriceAmount(2000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand(10);
        $stock->setReserved(0);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stock);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 1);
        $this->cartService->setCouponCode($coupon->getCode());

        $totals = $this->checkoutService->calculateTotals();
        $this->assertSame(2000, $totals['subtotal']);
        $this->assertSame(200, $totals['discount']);
        $this->assertSame($coupon->getCode(), $totals['couponCode']);
    }

    public function testCreateOrderPersistsAndClearsCart(): void
    {
        $product = new Product();
        $product->setName('Ord ' . uniqid());
        $product->setSlug('ord-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-ORD-' . uniqid());
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand(5);
        $stock->setReserved(0);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stock);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 2);

        $order = $this->checkoutService->createOrder(
            new CustomerInfoDTO('buyer@example.com', 'Bo', 'Buyer', null),
            new AddressDTO('Hauptstr. 1', 'Berlin', '10115', 'DE', null)
        );

        $this->assertNotNull($order->getId());
        $this->assertSame('buyer@example.com', $order->getEmail());
        $this->assertCount(1, $order->getItems());
        $this->assertSame([], $this->cartService->getDetailedItems());
    }

    public function testCreateOrderThrowsWhenReservationFails(): void
    {
        $product = new Product();
        $product->setName('No res ' . uniqid());
        $product->setSlug('nr-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');
        $variant = new ProductVariant();
        $variant->setSku('SKU-NORES-' . uniqid());
        $variant->setPriceAmount(500);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);
        $product->addVariant($variant);
        $stock = new StockItem();
        $stock->setVariant($variant);
        $stock->setOnHand(1);
        $stock->setReserved(0);
        $this->em->persist($product);
        $this->em->persist($variant);
        $this->em->persist($stock);
        $this->em->flush();

        $this->cartService->add($variant->getId(), 5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Inventory reservation failed');
        $this->checkoutService->createOrder(
            new CustomerInfoDTO('x@y.z', 'A', 'B', null),
            new AddressDTO('S', 'C', '1', 'DE', null)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
