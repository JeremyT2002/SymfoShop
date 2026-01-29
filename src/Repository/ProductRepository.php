<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findOneBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', ProductStatus::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveProductsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->orderBy('p.createdAt', 'DESC');
    }

    /**
     * Find active products (category filtering can be added when Product-Category relationship exists)
     */
    public function findActiveProducts(int $offset = 0, int $limit = 12): array
    {
        return $this->findActiveProductsQueryBuilder()
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveProducts(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
