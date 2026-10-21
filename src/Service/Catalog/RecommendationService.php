<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Product recommendations from real orders ("customers also bought"), filled up with products
 * from the same category when there is not enough order history yet.
 */
final class RecommendationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @param list<Product> $products  products the recommendation is for (product page: one, cart: all lines)
     * @return list<Product>
     */
    public function forProducts(array $products, int $limit = 4): array
    {
        $productIds = array_values(array_filter(array_map(static fn (Product $p): ?int => $p->getId(), $products)));
        if ($productIds === []) {
            return [];
        }

        $ids = $this->boughtTogether($productIds, $limit);

        if (\count($ids) < $limit) {
            $ids = array_merge($ids, $this->sameCategory($products, array_merge($productIds, $ids), $limit - \count($ids)));
        }

        return $this->productRepository->findForCardsByIds($ids);
    }

    /**
     * Products that appear in the same paid orders as the given ones, most frequent first.
     *
     * @param list<int> $productIds
     * @return list<int>
     */
    private function boughtTogether(array $productIds, int $limit): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id AS id', 'COUNT(DISTINCT o.id) AS orders')
            ->from(OrderItem::class, 'item')
            ->join('item.order', 'o')
            ->join('item.productVariant', 'v')
            ->join('v.product', 'source')
            ->join(OrderItem::class, 'other', 'WITH', 'other.order = o')
            ->join('other.productVariant', 'ov')
            ->join('ov.product', 'p')
            ->where('source.id IN (:ids)')
            ->andWhere('p.id NOT IN (:ids)')
            ->andWhere('p.status = :active')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('ids', $productIds)
            ->setParameter('active', ProductStatus::ACTIVE)
            ->setParameter('statuses', OrderRepository::DASHBOARD_COMPLETED_STATUSES)
            ->groupBy('p.id')
            ->orderBy('orders', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<Product> $products
     * @param list<int>     $exclude
     * @return list<int>
     */
    private function sameCategory(array $products, array $exclude, int $limit): array
    {
        $categories = array_values(array_unique(array_filter(array_map(static fn (Product $p): ?int => $p->getCategory()?->getId(), $products))));
        if ($categories === [] || $limit <= 0) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id AS id')
            ->from(Product::class, 'p')
            ->where('IDENTITY(p.category) IN (:categories)')
            ->andWhere('p.id NOT IN (:exclude)')
            ->andWhere('p.status = :active')
            ->setParameter('categories', $categories)
            ->setParameter('exclude', $exclude)
            ->setParameter('active', ProductStatus::ACTIVE)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
