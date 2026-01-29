<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use App\Entity\StockItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockItem>
 */
class StockItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockItem::class);
    }

    public function findOneByVariant(ProductVariant $variant): ?StockItem
    {
        return $this->createQueryBuilder('s')
            ->where('s.variant = :variant')
            ->setParameter('variant', $variant)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByVariantForUpdate(ProductVariant $variant): ?StockItem
    {
        return $this->createQueryBuilder('s')
            ->where('s.variant = :variant')
            ->setParameter('variant', $variant)
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

