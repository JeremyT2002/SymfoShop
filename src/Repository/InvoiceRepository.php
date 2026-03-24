<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findOneByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->where('i.invoiceNumber = :invoiceNumber')
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByOrderId(int $orderId): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->where('i.order = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Invoice>
     */
    public function findByOrderEmail(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.order', 'o')
            ->addSelect('o')
            ->where('o.email = :email')
            ->setParameter('email', $email)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserByInvoiceNumber(string $invoiceNumber, string $email): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.order', 'o')
            ->addSelect('o')
            ->where('i.invoiceNumber = :invoiceNumber')
            ->andWhere('o.email = :email')
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

