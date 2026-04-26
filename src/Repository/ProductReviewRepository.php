<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReview>
 */
class ProductReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReview::class);
    }

    public function hasReviewForProductAndUser(Product $product, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.product = :product')
            ->andWhere('r.user = :user')
            ->setParameter('product', $product)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<ProductReview>
     */
    public function findApprovedByProductPaginated(Product $product, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countApprovedByProduct(Product $product): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.product = :product')
            ->andWhere('r.isApproved = :approved')
            ->setParameter('product', $product)
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<ProductReview>
     */
    public function findForAdminList(?bool $approved, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.product', 'p')->addSelect('p')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($approved !== null) {
            $qb->andWhere('r.isApproved = :approved')
                ->setParameter('approved', $approved);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?bool $approved): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');
        if ($approved !== null) {
            $qb->andWhere('r.isApproved = :approved')
                ->setParameter('approved', $approved);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isApproved = :approved')
            ->setParameter('approved', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

