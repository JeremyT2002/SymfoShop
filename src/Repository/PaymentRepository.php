<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByPaymentIntentId(string $paymentIntentId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->where('p.paymentIntentId = :paymentIntentId')
            ->setParameter('paymentIntentId', $paymentIntentId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

