<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use App\Entity\StockNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockNotification>
 */
class StockNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockNotification::class);
    }

    /**
     * @return list<StockNotification>
     */
    public function findOpenForVariant(ProductVariant $variant): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.productVariant = :variant')
            ->andWhere('n.notifiedAt IS NULL')
            ->andWhere('(n.confirmedAt IS NOT NULL OR n.user IS NOT NULL)')
            ->setParameter('variant', $variant)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByConfirmationToken(string $token): ?StockNotification
    {
        return $this->findOneBy(['confirmationToken' => $token]);
    }

    public function existsOpenForVariantAndEmail(ProductVariant $variant, string $email): bool
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.productVariant = :variant')
            ->andWhere('n.email = :email')
            ->andWhere('n.notifiedAt IS NULL')
            ->setParameter('variant', $variant)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}

