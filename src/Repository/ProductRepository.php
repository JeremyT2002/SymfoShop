<?php

namespace App\Repository;

use App\Catalog\CatalogFilters;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
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

    /**
     * @return list<Product>
     */
    public function findForAdminList(
        ?ProductStatus $status,
        ?string $search,
        int $limit,
        int $offset,
        string $sortBy = 'createdAt',
        string $sortDir = 'DESC'
    ): array {
        $sortBy = $this->resolveAdminSortField($sortBy);
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createAdminListQueryBuilder($status, $search)
            ->orderBy('p.' . $sortBy, $sortDir)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?ProductStatus $status, ?string $search): int
    {
        $qb = $this->createAdminListQueryBuilder($status, $search)
            ->select('COUNT(p.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findActiveProductsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->orderBy('p.createdAt', 'DESC');
    }

    /**
     * Find active products (no category filter)
     */
    public function findActiveProducts(int $offset = 0, int $limit = 12): array
    {
        return $this->findActiveProductsQueryBuilder()
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Homepage-optimized query:
     * - first query fetches product IDs with pagination/sorting
     * - second query eager-loads variants + media to avoid N+1 in cards
     *
     * @return list<Product>
     */
    public function findActiveProductsForHomepage(int $limit = 8): array
    {
        $ids = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        if ($ids === []) {
            return [];
        }

        return $this->hydrateForCards($ids);
    }

    /**
     * Paginated active products with variants + media (product cards).
     *
     * @return list<Product>
     */
    public function findActiveProductsForListing(int $offset = 0, int $limit = 12, ?string $search = null): array
    {
        $idQuery = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE);
        $this->applySearch($idQuery, $search);

        $ids = $idQuery
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        if ($ids === []) {
            return [];
        }

        return $this->hydrateForCards($ids);
    }

    public function countActiveProducts(?string $search = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE);
        $this->applySearch($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Storefront search: every word must appear in the name or description (case-insensitive).
     */
    private function applySearch(QueryBuilder $qb, ?string $search): void
    {
        $words = preg_split('/\s+/', mb_strtolower(trim((string) $search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Wildcards are dropped rather than escaped: LIKE escaping differs between SQLite and MySQL.
        $words = array_values(array_filter(array_map(static fn (string $w): string => str_replace(['%', '_'], '', $w), $words)));

        foreach (\array_slice($words, 0, 5) as $i => $word) {
            $qb->andWhere(sprintf('(LOWER(p.name) LIKE :q%1$d OR LOWER(p.description) LIKE :q%1$d)', $i))
                ->setParameter('q' . $i, '%' . $word . '%');
        }
    }

    /**
     * Find products in category with filters and sorting. Paginated.
     *
     * @return Product[]
     */
    public function findFilteredByCategory(Category $category, CatalogFilters $filters, int $offset = 0, int $limit = 12): array
    {
        $qb = $this->createFilteredByCategoryQueryBuilder($category, $filters);
        $this->applySort($qb, $filters->sort);
        $qb->setFirstResult($offset)->setMaxResults($limit);

        $ids = array_map(static fn (Product $p): ?int => $p->getId(), $qb->getQuery()->getResult());

        return $this->hydrateForCards($ids);
    }

    /**
     * Products for cards in the given id order (variants, stock and media loaded).
     *
     * @param list<int> $ids
     * @return list<Product>
     */
    public function findForCardsByIds(array $ids): array
    {
        return $this->hydrateForCards($ids);
    }

    /**
     * Loads products with everything a product card needs (variants, stock, media) in one query,
     * keeping the order of $ids. Avoids one stock query per variant (inverse one-to-one).
     *
     * @param list<int|string|null> $ids
     * @return list<Product>
     */
    private function hydrateForCards(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        /** @var list<Product> $products */
        $products = $this->createQueryBuilder('p')
            ->leftJoin('p.variants', 'v')
            ->addSelect('v')
            ->leftJoin('v.stockItem', 'si')
            ->addSelect('si')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $position = array_flip($ids);
        usort($products, static fn (Product $a, Product $b): int => $position[$a->getId()] <=> $position[$b->getId()]);

        return $products;
    }

    public function countFilteredByCategory(Category $category, CatalogFilters $filters): int
    {
        $qb = $this->createFilteredByCategoryQueryBuilder($category, $filters);
        $qb->select('COUNT(DISTINCT p.id)');
        $qb->resetDQLPart('groupBy');
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createFilteredByCategoryQueryBuilder(Category $category, CatalogFilters $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.category = :category')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->setParameter('category', $category);

        $qb->innerJoin('p.variants', 'v');

        if ($filters->minPrice !== null || $filters->maxPrice !== null) {
            if ($filters->minPrice !== null) {
                $qb->andWhere('v.priceAmount >= :minPrice')->setParameter('minPrice', $filters->minPrice);
            }
            if ($filters->maxPrice !== null) {
                $qb->andWhere('v.priceAmount <= :maxPrice')->setParameter('maxPrice', $filters->maxPrice);
            }
        }

        if ($filters->inStockOnly) {
            $qb->leftJoin('v.stockItem', 's');
            $qb->andWhere('s.id IS NOT NULL AND (s.onHand - s.reserved) > 0');
        }

        $attrProductIds = $this->getProductIdsMatchingAttributeFilter($category->getId(), $filters->attributeFilters);
        if ($attrProductIds !== []) {
            $qb->andWhere('p.id IN (:attrProductIds)')->setParameter('attrProductIds', $attrProductIds);
        } elseif ($filters->attributeFilters !== []) {
            $qb->andWhere('1 = 0');
        }

        $qb->groupBy('p.id');
        return $qb;
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        switch ($sort) {
            case 'price-asc':
                $qb->addSelect('MIN(v.priceAmount) AS HIDDEN min_price');
                $qb->orderBy('min_price', 'ASC');
                break;
            case 'price-desc':
                $qb->addSelect('MAX(v.priceAmount) AS HIDDEN max_price');
                $qb->orderBy('max_price', 'DESC');
                break;
            case 'name-asc':
                $qb->orderBy('p.name', 'ASC');
                break;
            case 'name-desc':
                $qb->orderBy('p.name', 'DESC');
                break;
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }
    }

    /**
     * Get product IDs that have at least one variant matching all attribute filters.
     * Uses native SQL for JSON attribute filtering (SQLite json_extract).
     *
     * @param array<string, list<string>> $attributeFilters
     * @return list<int>
     */
    private function getProductIdsMatchingAttributeFilter(int $categoryId, array $attributeFilters): array
    {
        if ($attributeFilters === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();

        if (!$platform instanceof SqlitePlatform) {
            return $this->getProductIdsMatchingAttributeFilterGeneric($categoryId, $attributeFilters, $conn);
        }

        $conditions = [];
        $params = ['category_id' => $categoryId];
        $paramIdx = 0;
        foreach ($attributeFilters as $attrKey => $values) {
            if ($values === []) {
                continue;
            }
            $placeholders = [];
            foreach ($values as $v) {
                $key = 'p' . ($paramIdx++);
                $params[$key] = $v;
                $placeholders[] = ':' . $key;
            }
            $conditions[] = "json_extract(pv.attributes, '$.$attrKey') IN (" . implode(', ', $placeholders) . ')';
        }
        if ($conditions === []) {
            return [];
        }

        $sql = "SELECT DISTINCT p.id FROM product p
                INNER JOIN product_variant pv ON pv.product_id = p.id
                WHERE p.category_id = :category_id AND p.status = 'active'
                AND " . implode(' AND ', $conditions);
        $stmt = $conn->executeQuery($sql, $params);
        $rows = $stmt->fetchAllAssociative();
        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Fallback for non-SQLite: fetch variant attributes in PHP and filter (works on any DB).
     *
     * @param array<string, list<string>> $attributeFilters
     * @return list<int>
     */
    private function getProductIdsMatchingAttributeFilterGeneric(int $categoryId, array $attributeFilters, Connection $conn): array
    {
        $sql = 'SELECT p.id, pv.attributes FROM product p
                INNER JOIN product_variant pv ON pv.product_id = p.id
                WHERE p.category_id = :cid';
        $stmt = $conn->executeQuery($sql, ['cid' => $categoryId]);
        $productMatches = [];
        while ($row = $stmt->fetchAssociative()) {
            $pid = (int) $row['id'];
            $attrs = json_decode($row['attributes'] ?? '{}', true);
            if (!is_array($attrs)) {
                continue;
            }
            $matches = true;
            foreach ($attributeFilters as $key => $values) {
                $val = $attrs[$key] ?? null;
                if (!in_array((string) $val, $values, true)) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $productMatches[$pid] = true;
            }
        }
        return array_keys($productMatches);
    }

    /**
     * Get filter options (price min/max, attribute value counts) for the category.
     *
     * @return array{price_min: int, price_max: int, attributes: array<string, array<string, int>>}
     */
    public function getFilterOptionsForCategory(Category $category): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $priceSql = "SELECT MIN(pv.price_amount) AS min_p, MAX(pv.price_amount) AS max_p
                     FROM product p
                     INNER JOIN product_variant pv ON pv.product_id = p.id
                     WHERE p.category_id = ? AND p.status = 'active'";
        $priceRow = $conn->fetchAssociative($priceSql, [$category->getId()]);
        $priceMin = $priceRow && $priceRow['min_p'] !== null ? (int) $priceRow['min_p'] : 0;
        $priceMax = $priceRow && $priceRow['max_p'] !== null ? (int) $priceRow['max_p'] : 0;

        $variantsSql = "SELECT pv.attributes FROM product p
                       INNER JOIN product_variant pv ON pv.product_id = p.id
                       WHERE p.category_id = ? AND p.status = 'active'";
        $stmt = $conn->executeQuery($variantsSql, [$category->getId()]);
        $attributes = [];
        while ($row = $stmt->fetchAssociative()) {
            $attrs = json_decode($row['attributes'] ?? '{}', true);
            if (!is_array($attrs)) {
                continue;
            }
            foreach ($attrs as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                if (!isset($attributes[$key][$value])) {
                    $attributes[$key][$value] = 0;
                }
                $attributes[$key][$value]++;
            }
        }

        return [
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return list<string>
     */
    public function findActiveSlugs(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->where('p.status = :status')
            ->andWhere('p.seoNoIndex = :noIndex')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->setParameter('noIndex', false)
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    private function createAdminListQueryBuilder(?ProductStatus $status, ?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if ($status !== null) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb->andWhere('LOWER(p.name) LIKE :term OR LOWER(p.slug) LIKE :term')
                ->setParameter('term', $term);
        }

        return $qb;
    }

    private function resolveAdminSortField(string $requested): string
    {
        $allowed = ['id', 'name', 'slug', 'status', 'createdAt'];

        return in_array($requested, $allowed, true) ? $requested : 'createdAt';
    }
}
