<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    /** @var list<string> */
    public const DASHBOARD_COMPLETED_STATUSES = ['paid', 'processing', 'shipped', 'completed'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findOneByOrderNumber(string $orderNumber): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.orderNumber = :orderNumber')
            ->setParameter('orderNumber', $orderNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByOrderNumberAndEmail(string $orderNumber, string $email): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.orderNumber = :orderNumber')
            ->andWhere('LOWER(o.email) = :email')
            ->setParameter('orderNumber', trim($orderNumber))
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Order>
     */
    public function findForAdminList(
        ?string $status,
        ?string $search,
        int $limit,
        int $offset
    ): array {
        $qb = $this->createAdminListQueryBuilder($status, $search)
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?string $status, ?string $search): int
    {
        $qb = $this->createAdminListQueryBuilder($status, $search)
            ->select('COUNT(o.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Order>
     */
    public function findByCustomerEmail(string $email, int $limit = 20): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.email = :email')
            ->setParameter('email', $email)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get daily order counts and revenue for the last N days.
     * @return array{labels: string[], orderCounts: int[], revenue: int[]}
     */
    public function getSalesOverTime(int $days = 30): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0, 0, 0);
        $orders = $this->createQueryBuilder('o')
            ->select('o.createdAt', 'o.grandTotal')
            ->where('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($orders as $row) {
            $day = $row['createdAt']->format('Y-m-d');
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['count' => 0, 'revenue' => 0];
            }
            $byDay[$day]['count'] += 1;
            $byDay[$day]['revenue'] += $row['grandTotal'];
        }

        $labels = [];
        $orderCounts = [];
        $revenue = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = (new \DateTimeImmutable())->modify("-{$i} days")->format('Y-m-d');
            $labels[] = (new \DateTimeImmutable($day))->format('M j');
            $orderCounts[] = $byDay[$day]['count'] ?? 0;
            $revenue[] = $byDay[$day]['revenue'] ?? 0;
        }

        return ['labels' => $labels, 'orderCounts' => $orderCounts, 'revenue' => $revenue];
    }

    /**
     * Get order counts grouped by status.
     * @return array<array{status: string, count: int}>
     */
    public function getOrdersByStatus(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) as cnt')
            ->groupBy('o.status')
            ->orderBy('cnt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn ($row) => [
            'status' => $row['status'] ?? 'unknown',
            'count' => (int) ($row['cnt'] ?? 0),
        ], $result);
    }

    /**
     * @return array{revenue:int, orders:int}
     */
    public function getRevenueAndOrderCountBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.grandTotal), 0) AS revenue', 'COUNT(o.id) AS orders')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('statuses', self::DASHBOARD_COMPLETED_STATUSES)
            ->getQuery()
            ->getSingleResult();

        return [
            'revenue' => (int) ($row['revenue'] ?? 0),
            'orders' => (int) ($row['orders'] ?? 0),
        ];
    }

    /**
     * @return list<array{name:string,revenue:int}>
     */
    public function getTopProductsByRevenueBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->from('App\Entity\OrderItem', 'oi')
            ->join('oi.order', 'o')
            ->select('oi.nameSnapshot AS name', 'COALESCE(SUM(oi.totalAmount), 0) AS revenue')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:statuses)')
            ->groupBy('oi.nameSnapshot')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('statuses', self::DASHBOARD_COMPLETED_STATUSES)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'name' => (string) ($row['name'] ?? ''),
            'revenue' => (int) ($row['revenue'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<array{status:string,count:int}>
     */
    public function getOrdersByStatusBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status', 'COUNT(o.id) AS cnt')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->groupBy('o.status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'status' => (string) ($row['status'] ?? 'unknown'),
            'count' => (int) ($row['cnt'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<array{createdAt:\DateTimeImmutable,grandTotal:int}>
     */
    public function getRevenueRowsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.createdAt AS createdAt', 'o.grandTotal AS grandTotal')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('statuses', self::DASHBOARD_COMPLETED_STATUSES)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'createdAt' => $row['createdAt'],
            'grandTotal' => (int) ($row['grandTotal'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<Order>
     */
    public function findRecentForDashboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function createAdminListQueryBuilder(?string $status, ?string $search): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');

        if ($status !== null && $status !== '') {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . mb_strtolower(trim($search)) . '%';
            $qb->andWhere('LOWER(o.orderNumber) LIKE :term OR LOWER(o.email) LIKE :term')
                ->setParameter('term', $term);
        }

        return $qb;
    }
}

