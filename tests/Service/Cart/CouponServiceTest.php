<?php

namespace App\Tests\Service\Cart;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\User;
use App\Repository\CouponRepository;
use App\Repository\CouponUsageRepository;
use App\Service\Cart\CouponService;
use PHPUnit\Framework\TestCase;

class CouponServiceTest extends TestCase
{
    private CouponService $couponService;
    private CouponRepository $couponRepository;
    private CouponUsageRepository $couponUsageRepository;

    protected function setUp(): void
    {
        $this->couponRepository = $this->createMock(CouponRepository::class);
        $this->couponUsageRepository = $this->createMock(CouponUsageRepository::class);
        $this->couponService = new CouponService($this->couponRepository, $this->couponUsageRepository);
    }

    public function testValidateEmptyCode(): void
    {
        $result = $this->couponService->validate('', null, 1000);
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['coupon']);
        $this->assertContains('Coupon code is required', $result['errors']);
    }

    public function testValidateInvalidCode(): void
    {
        $this->couponRepository->method('findByCode')->willReturn(null);
        
        $result = $this->couponService->validate('INVALID', null, 1000);
        
        $this->assertFalse($result['valid']);
        $this->assertNull($result['coupon']);
        $this->assertContains('Invalid coupon code', $result['errors']);
    }

    public function testValidateExpiredCoupon(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        $coupon->setExpiresAt(new \DateTimeImmutable('-1 day'));
        
        $this->couponRepository->method('findByCode')->willReturn($coupon);
        $this->couponRepository->method('countUsages')->willReturn(0);
        
        $result = $this->couponService->validate('SAVE10', null, 1000);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('This coupon has expired', $result['errors']);
    }

    public function testValidateInactiveCoupon(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        $coupon->setIsActive(false);
        
        $this->couponRepository->method('findByCode')->willReturn($coupon);
        $this->couponRepository->method('countUsages')->willReturn(0);
        
        $result = $this->couponService->validate('SAVE10', null, 1000);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('This coupon is not active', $result['errors']);
    }

    public function testValidateUsageLimitExceeded(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        $coupon->setUsageLimit(5);
        
        $this->couponRepository->method('findByCode')->willReturn($coupon);
        $this->couponRepository->method('countUsages')->willReturn(5);
        
        $result = $this->couponService->validate('SAVE10', null, 1000);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('This coupon has reached its usage limit', $result['errors']);
    }

    public function testValidateValidCoupon(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        
        $this->couponRepository->method('findByCode')->willReturn($coupon);
        $this->couponRepository->method('countUsages')->willReturn(0);
        
        $result = $this->couponService->validate('SAVE10', null, 1000);
        
        $this->assertTrue($result['valid']);
        $this->assertSame($coupon, $result['coupon']);
        $this->assertEmpty($result['errors']);
    }

    public function testCalculateDiscountPercentage(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        
        $discount = $this->couponService->calculateDiscount($coupon, 10000); // 100.00 EUR
        
        $this->assertEquals(1000, $discount); // 10.00 EUR
    }

    public function testCalculateDiscountFixed(): void
    {
        $coupon = $this->createCoupon('FIXED5', CouponType::FIXED, 500); // 5.00 EUR
        
        $discount = $this->couponService->calculateDiscount($coupon, 10000); // 100.00 EUR
        
        $this->assertEquals(500, $discount); // 5.00 EUR
    }

    public function testCalculateDiscountFixedExceedsSubtotal(): void
    {
        $coupon = $this->createCoupon('FIXED20', CouponType::FIXED, 2000); // 20.00 EUR
        
        $discount = $this->couponService->calculateDiscount($coupon, 1000); // 10.00 EUR
        
        // Discount should not exceed subtotal
        $this->assertEquals(1000, $discount); // 10.00 EUR (capped)
    }

    public function testCalculateDiscountZeroSubtotal(): void
    {
        $coupon = $this->createCoupon('SAVE10', CouponType::PERCENTAGE, 10);
        
        $discount = $this->couponService->calculateDiscount($coupon, 0);
        
        $this->assertEquals(0, $discount);
    }

    public function testCalculateDiscount100Percent(): void
    {
        $coupon = $this->createCoupon('FREE', CouponType::PERCENTAGE, 100);
        
        $discount = $this->couponService->calculateDiscount($coupon, 10000);
        
        $this->assertEquals(10000, $discount);
    }

    private function createCoupon(string $code, CouponType $type, int $value): Coupon
    {
        $coupon = new Coupon();
        $coupon->setCode($code);
        $coupon->setType($type);
        $coupon->setValue($value);
        $coupon->setIsActive(true);
        
        return $coupon;
    }
}

