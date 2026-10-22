<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Refund;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Refund>
 */
class RefundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Refund::class);
    }

    /**
     * @return list<Refund>
     */
    public function findByOrder(Order $order): array
    {
        return $this->findBy(['order' => $order], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    /** Sum of all refunds of the order in minor units (pending ones included: the money is on its way). */
    public function sumForOrder(Order $order): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.amount), 0)')
            ->where('r.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Refund>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.order', 'o')
            ->addSelect('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserByCreditNoteNumber(string $number, User $user): ?Refund
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.order', 'o')
            ->where('r.creditNoteNumber = :number')
            ->andWhere('o.user = :user')
            ->setParameter('number', $number)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCreditNoteNumber(string $number): ?Refund
    {
        return $this->findOneBy(['creditNoteNumber' => $number]);
    }

    /** Next credit note number of the year: CN-2026-0001, CN-2026-0002, … */
    public function nextCreditNoteNumber(): string
    {
        $prefix = 'CN-' . date('Y') . '-';

        $last = $this->createQueryBuilder('r')
            ->select('r.creditNoteNumber')
            ->where('r.creditNoteNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('r.creditNoteNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $next = $last !== null ? (int) substr((string) $last['creditNoteNumber'], -4) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', \STR_PAD_LEFT);
    }
}
