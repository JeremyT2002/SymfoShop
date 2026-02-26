<?php

namespace App\Repository;

use App\Entity\Coupon;
use App\Entity\CouponUsage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CouponUsage>
 */
class CouponUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CouponUsage::class);
    }

    /**
     * Find or create coupon usage for user
     */
    public function findOrCreate(Coupon $coupon, User $user): CouponUsage
    {
        $usage = $this->findOneBy([
            'coupon' => $coupon,
            'user' => $user,
        ]);

        if (!$usage) {
            $usage = new CouponUsage();
            $usage->setCoupon($coupon);
            $usage->setUser($user);
            $this->getEntityManager()->persist($usage);
        }

        return $usage;
    }

    /**
     * Increment usage count for user
     */
    public function incrementUsage(Coupon $coupon, User $user): void
    {
        $usage = $this->findOrCreate($coupon, $user);
        $usage->incrementUsageCount();
        $this->getEntityManager()->flush();
    }
}

