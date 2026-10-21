<?php

namespace App\Repository;

use App\Entity\Coupon;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Coupon>
 */
class CouponRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coupon::class);
    }

    /**
     * Find coupon by code (case-insensitive)
     */
    public function findByCode(string $code): ?Coupon
    {
        return $this->createQueryBuilder('c')
            ->where('UPPER(c.code) = UPPER(:code)')
            ->setParameter('code', trim($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count total usages of a coupon: every paid (or later) order that redeemed it, guests included.
     */
    public function countUsages(Coupon $coupon): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->where('UPPER(o.couponCode) = UPPER(:code)')
            ->andWhere('o.status IN (:redeemedStatuses)')
            ->setParameter('code', $coupon->getCode())
            ->setParameter('redeemedStatuses', OrderRepository::DASHBOARD_COMPLETED_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count usages by a specific user
     */
    public function countUserUsages(Coupon $coupon, User $user): int
    {
        $usage = $this->getEntityManager()
            ->getRepository(\App\Entity\CouponUsage::class)
            ->findOneBy([
                'coupon' => $coupon,
                'user' => $user,
            ]);

        return $usage ? $usage->getUsageCount() : 0;
    }
}

