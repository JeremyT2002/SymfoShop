<?php

namespace App\Service\Admin;

use App\Repository\OrderRepository;
use App\Repository\ProductReviewRepository;
use App\Repository\StockItemRepository;
use App\Repository\UserRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class DashboardMetricsService
{
    public const CACHE_TAG = 'dashboard_metrics';

    /** @var list<string> */
    private const ALLOWED_PERIODS = ['today', '7d', '30d', '12m'];

    /** @var list<string> */
    private const ORDER_STATUSES = ['new', 'payment_pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ProductReviewRepository $productReviewRepository,
        private readonly StockItemRepository $stockItemRepository,
        private readonly TagAwareCacheInterface $cache,
        private readonly int $lowStockThreshold = 5,
    ) {
    }

    /**
     * @return array{
     *     period:string,
     *     kpis:array<string,mixed>,
     *     charts:array<string,mixed>,
     *     recentOrders:list<\App\Entity\Order>,
     *     lowStockItems:list<\App\Entity\StockItem>,
     *     pendingReviews:list<\App\Entity\ProductReview>
     * }
     */
    public function getDashboardData(string $period): array
    {
        $period = \in_array($period, self::ALLOWED_PERIODS, true) ? $period : '7d';
        $window = $this->resolvePeriodWindow($period);

        return $this->cache->get(
            sprintf('admin.dashboard.metrics.%s', $period),
            function (ItemInterface $item) use ($period, $window): array {
                $item->expiresAfter(300);
                $item->tag([self::CACHE_TAG]);

                $current = $this->orderRepository->getRevenueAndOrderCountBetween($window['start'], $window['end']);
                $previous = $this->orderRepository->getRevenueAndOrderCountBetween($window['previousStart'], $window['start']);
                $newCustomers = $this->userRepository->countCreatedBetween($window['start'], $window['end']);
                $previousNewCustomers = $this->userRepository->countCreatedBetween($window['previousStart'], $window['start']);

                $aov = $current['orders'] > 0 ? (int) round($current['revenue'] / $current['orders']) : 0;
                $previousAov = $previous['orders'] > 0 ? (int) round($previous['revenue'] / $previous['orders']) : 0;
                $conversion = $newCustomers > 0 ? round(($current['orders'] / $newCustomers) * 100, 2) : 0.0;
                $previousConversion = $previousNewCustomers > 0 ? round(($previous['orders'] / $previousNewCustomers) * 100, 2) : 0.0;

                $revenueSeries = $this->buildRevenueSeries(
                    $this->orderRepository->getRevenueRowsBetween($window['start'], $window['end']),
                    $period,
                    $window['start'],
                    $window['end']
                );

                $topProducts = $this->orderRepository->getTopProductsByRevenueBetween($window['start'], $window['end'], 10);
                $statusRaw = $this->orderRepository->getOrdersByStatusBetween($window['start'], $window['end']);
                $statusChart = $this->normalizeStatusChart($statusRaw);

                return [
                    'period' => $period,
                    'kpis' => [
                        'revenue' => [
                            'value' => $current['revenue'],
                            'change' => $this->calculateChange($current['revenue'], $previous['revenue']),
                        ],
                        'orders' => [
                            'value' => $current['orders'],
                            'change' => $this->calculateChange($current['orders'], $previous['orders']),
                        ],
                        'aov' => [
                            'value' => $aov,
                            'change' => $this->calculateChange($aov, $previousAov),
                        ],
                        'newCustomers' => [
                            'value' => $newCustomers,
                            'change' => $this->calculateChange($newCustomers, $previousNewCustomers),
                        ],
                        'conversion' => [
                            'value' => $conversion,
                            'change' => $this->calculateChange($conversion, $previousConversion),
                            'formula' => 'orders_per_new_users',
                        ],
                    ],
                    'charts' => [
                        'revenue' => $revenueSeries,
                        'topProducts' => $topProducts,
                        'status' => $statusChart,
                    ],
                    'recentOrders' => $this->orderRepository->findRecentForDashboard(10),
                    'lowStockItems' => $this->stockItemRepository->findLowStockItems($this->lowStockThreshold, 10),
                    'pendingReviews' => $this->productReviewRepository->findForAdminList(false, 10, 0),
                ];
            }
        );
    }

    /**
     * @return array{start:\DateTimeImmutable,end:\DateTimeImmutable,previousStart:\DateTimeImmutable}
     */
    private function resolvePeriodWindow(string $period): array
    {
        $now = new \DateTimeImmutable();

        return match ($period) {
            'today' => [
                'start' => $now->setTime(0, 0, 0),
                'end' => $now->setTime(23, 59, 59)->modify('+1 second'),
                'previousStart' => $now->setTime(0, 0, 0)->modify('-1 day'),
            ],
            '30d' => [
                'start' => $now->setTime(0, 0, 0)->modify('-30 days'),
                'end' => $now->modify('+1 second'),
                'previousStart' => $now->setTime(0, 0, 0)->modify('-60 days'),
            ],
            '12m' => [
                'start' => $now->setDate((int) $now->format('Y') - 1, (int) $now->format('m'), 1)->setTime(0, 0, 0),
                'end' => $now->modify('+1 second'),
                'previousStart' => $now->setDate((int) $now->format('Y') - 2, (int) $now->format('m'), 1)->setTime(0, 0, 0),
            ],
            default => [
                'start' => $now->setTime(0, 0, 0)->modify('-7 days'),
                'end' => $now->modify('+1 second'),
                'previousStart' => $now->setTime(0, 0, 0)->modify('-14 days'),
            ],
        };
    }

    private function calculateChange(int|float $current, int|float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * @param list<array{createdAt:\DateTimeImmutable,grandTotal:int}> $rows
     * @return array{labels:list<string>,revenue:list<float>}
     */
    private function buildRevenueSeries(array $rows, string $period, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $date = $row['createdAt'];
            $key = match ($period) {
                '12m' => $date->format('Y-m'),
                default => $date->format('Y-m-d'),
            };

            if (!isset($grouped[$key])) {
                $grouped[$key] = 0;
            }
            $grouped[$key] += $row['grandTotal'];
        }

        if ($period === '12m') {
            $labels = [];
            $values = [];
            $cursor = $start;
            while ($cursor < $end) {
                $key = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $values[] = round(($grouped[$key] ?? 0) / 100, 2);
                $cursor = $cursor->modify('+1 month');
            }

            return ['labels' => $labels, 'revenue' => $values];
        }

        $labels = [];
        $values = [];
        $cursor = $start;
        while ($cursor < $end) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('d M');
            $values[] = round(($grouped[$key] ?? 0) / 100, 2);
            $cursor = $cursor->modify('+1 day');
        }

        return ['labels' => $labels, 'revenue' => $values];
    }

    /**
     * @param list<array{status:string,count:int}> $rows
     * @return array{labels:list<string>,values:list<int>}
     */
    private function normalizeStatusChart(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['status']] = $row['count'];
        }

        $labels = [];
        $values = [];
        foreach (self::ORDER_STATUSES as $status) {
            $labels[] = $status;
            $values[] = $mapped[$status] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }
}

