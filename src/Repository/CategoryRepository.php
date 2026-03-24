<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findOneBySlug(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns child counts for root categories in one query.
     *
     * @return array<int, int> map: categoryId => childrenCount
     */
    public function getRootCategoryChildrenCounts(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id', 'COUNT(ch.id) AS childrenCount')
            ->leftJoin('c.children', 'ch')
            ->where('c.parent IS NULL')
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = (int) $row['childrenCount'];
        }

        return $result;
    }
}
