<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    /** @var list<string> */
    private const VERIFIED_ORDER_STATUSES = ['paid', 'shipped', 'completed'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function existsVerifiedPurchase(User $user, Product $product): bool
    {
        return (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->join('oi.order', 'o')
            ->join('oi.productVariant', 'pv')
            ->where('o.user = :user')
            ->andWhere('pv.product = :product')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->setParameter('statuses', self::VERIFIED_ORDER_STATUSES)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}

