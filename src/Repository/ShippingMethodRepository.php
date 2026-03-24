<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShippingMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethod>
 */
class ShippingMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethod::class);
    }

    /**
     * @return list<ShippingMethod>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ShippingMethod>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?ShippingMethod
    {
        $code = mb_strtolower(trim($code));

        return $this->createQueryBuilder('s')
            ->andWhere('s.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFirstActive(): ?ShippingMethod
    {
        $list = $this->findActiveOrdered();

        return $list[0] ?? null;
    }
}
