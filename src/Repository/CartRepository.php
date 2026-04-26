<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findOneByUser(User $user): ?Cart
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * @return list<Cart>
     */
    public function findAbandonedCarts(\DateTimeImmutable $olderThan, \DateTimeImmutable $newerThan): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')
            ->innerJoin('c.items', 'i')
            ->where('c.updatedAt <= :olderThan')
            ->andWhere('c.updatedAt >= :newerThan')
            ->andWhere('c.reminderSentAt IS NULL')
            ->andWhere('u.marketingOptIn = :optIn')
            ->setParameter('olderThan', $olderThan)
            ->setParameter('newerThan', $newerThan)
            ->setParameter('optIn', true)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }
}

