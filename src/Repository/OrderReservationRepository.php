<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderReservation>
 */
class OrderReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderReservation::class);
    }

    /**
     * Find all expired reservations
     *
     * @return OrderReservation[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations for an order
     *
     * @return OrderReservation[]
     */
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getResult();
    }
}

