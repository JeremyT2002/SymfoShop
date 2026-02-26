<?php

namespace App\Service\Cart;

use App\Entity\Coupon;
use App\Entity\CouponType;
use App\Entity\User;
use App\Repository\CouponRepository;
use App\Repository\CouponUsageRepository;

class CouponService
{
    public function __construct(
        private readonly CouponRepository $couponRepository,
        private readonly CouponUsageRepository $couponUsageRepository
    ) {
    }

    /**
     * Validate coupon code and return validation result
     *
     * @return array{valid: bool, coupon: ?Coupon, errors: string[]}
     */
    public function validate(string $code, ?User $user = null, int $subtotal = 0): array
    {
        $errors = [];
        $code = strtoupper(trim($code));

        if (empty($code)) {
            return [
                'valid' => false,
                'coupon' => null,
                'errors' => ['Coupon code is required'],
            ];
        }

        $coupon = $this->couponRepository->findByCode($code);

        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'errors' => ['Invalid coupon code'],
            ];
        }

        // Check if active
        if (!$coupon->isActive()) {
            $errors[] = 'This coupon is not active';
        }

        // Check expiration
        if ($coupon->isExpired()) {
            $errors[] = 'This coupon has expired';
        }

        // Check total usage limit
        if ($coupon->getUsageLimit() !== null) {
            $totalUsages = $this->couponRepository->countUsages($coupon);
            if ($totalUsages >= $coupon->getUsageLimit()) {
                $errors[] = 'This coupon has reached its usage limit';
            }
        }

        // Check per-user limit (if user is logged in)
        if ($user && $coupon->getPerUserLimit() !== null) {
            $userUsages = $this->couponRepository->countUserUsages($coupon, $user);
            if ($userUsages >= $coupon->getPerUserLimit()) {
                $errors[] = 'You have reached the usage limit for this coupon';
            }
        }

        // Validate value ranges
        if ($coupon->getType() === CouponType::PERCENTAGE) {
            if ($coupon->getValue() < 0 || $coupon->getValue() > 100) {
                $errors[] = 'Invalid coupon percentage value';
            }
        } elseif ($coupon->getType() === CouponType::FIXED) {
            if ($coupon->getValue() <= 0) {
                $errors[] = 'Invalid coupon fixed amount';
            }
            if ($subtotal > 0 && $coupon->getValue() > $subtotal) {
                // This is a warning, not an error - we'll cap it during calculation
            }
        }

        return [
            'valid' => empty($errors),
            'coupon' => $coupon,
            'errors' => $errors,
        ];
    }

    /**
     * Calculate discount amount for a coupon and subtotal
     *
     * @return int Discount amount in cents
     */
    public function calculateDiscount(Coupon $coupon, int $subtotal): int
    {
        if ($subtotal <= 0) {
            return 0;
        }

        $discount = 0;

        if ($coupon->getType() === CouponType::PERCENTAGE) {
            // Percentage discount
            $discount = (int) round($subtotal * ($coupon->getValue() / 100));
        } elseif ($coupon->getType() === CouponType::FIXED) {
            // Fixed amount discount
            $discount = $coupon->getValue();
        }

        // Ensure discount doesn't exceed subtotal
        return min($discount, $subtotal);
    }

    /**
     * Check if user can use this coupon
     */
    public function canUse(Coupon $coupon, ?User $user = null): bool
    {
        $validation = $this->validate($coupon->getCode(), $user);
        return $validation['valid'];
    }

    /**
     * Record coupon usage (increment usage count)
     */
    public function recordUsage(Coupon $coupon, ?User $user = null): void
    {
        if ($user) {
            $this->couponUsageRepository->incrementUsage($coupon, $user);
        }
    }
}

