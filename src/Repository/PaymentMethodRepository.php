<?php

namespace App\Repository;

use App\Entity\PaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentMethod>
 */
class PaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethod::class);
    }

    /**
     * @return list<PaymentMethod>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('pm')
            ->orderBy('pm.sortOrder', 'ASC')
            ->addOrderBy('pm.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PaymentMethod>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pm.sortOrder', 'ASC')
            ->addOrderBy('pm.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultActive(): ?PaymentMethod
    {
        return $this->createQueryBuilder('pm')
            ->andWhere('pm.isDefault = :isDefault')
            ->andWhere('pm.isActive = :active')
            ->setParameter('isDefault', true)
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function clearDefaultFlags(): void
    {
        $this->createQueryBuilder('pm')
            ->update()
            ->set('pm.isDefault', ':off')
            ->setParameter('off', false)
            ->getQuery()
            ->execute();
    }
}

