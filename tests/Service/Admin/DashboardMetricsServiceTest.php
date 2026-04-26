<?php

namespace App\Tests\Service\Admin;

use App\Repository\OrderRepository;
use App\Repository\ProductReviewRepository;
use App\Repository\StockItemRepository;
use App\Repository\UserRepository;
use App\Service\Admin\DashboardMetricsService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class DashboardMetricsServiceTest extends TestCase
{
    public function testBuildsExpectedMetricsPayload(): void
    {
        $orderRepository = $this->createMock(OrderRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $reviewRepository = $this->createMock(ProductReviewRepository::class);
        $stockRepository = $this->createMock(StockItemRepository::class);

        $orderRepository->method('getRevenueAndOrderCountBetween')
            ->willReturnOnConsecutiveCalls(
                ['revenue' => 120000, 'orders' => 30],
                ['revenue' => 100000, 'orders' => 25]
            );
        $userRepository->method('countCreatedBetween')
            ->willReturnOnConsecutiveCalls(20, 16);
        $orderRepository->method('getRevenueRowsBetween')
            ->willReturn([
                ['createdAt' => new \DateTimeImmutable('-2 days'), 'grandTotal' => 5000],
                ['createdAt' => new \DateTimeImmutable('-1 day'), 'grandTotal' => 3000],
            ]);
        $orderRepository->method('getTopProductsByRevenueBetween')
            ->willReturn([
                ['name' => 'Shirt', 'revenue' => 10000],
                ['name' => 'Shoes', 'revenue' => 7000],
            ]);
        $orderRepository->method('getOrdersByStatusBetween')
            ->willReturn([
                ['status' => 'new', 'count' => 2],
                ['status' => 'completed', 'count' => 8],
            ]);
        $orderRepository->method('findRecentForDashboard')->willReturn([]);
        $reviewRepository->method('findForAdminList')->willReturn([]);
        $stockRepository->method('findLowStockItems')->willReturn([]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->method('get')->willReturnCallback(
            static fn (string $key, callable $callback): array => $callback(new class implements ItemInterface {
                public function getKey(): string { return 'test'; }
                public function get(): mixed { return null; }
                public function isHit(): bool { return false; }
                public function set(mixed $value): static { return $this; }
                public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
                public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
                public function tag(string|iterable $tags): static { return $this; }
                public function getMetadata(): array { return []; }
            })
        );

        $service = new DashboardMetricsService(
            $orderRepository,
            $userRepository,
            $reviewRepository,
            $stockRepository,
            $cache,
            5
        );

        $result = $service->getDashboardData('7d');

        self::assertSame(120000, $result['kpis']['revenue']['value']);
        self::assertSame(20.0, $result['kpis']['revenue']['change']);
        self::assertSame(30, $result['kpis']['orders']['value']);
        self::assertSame(7, \count($result['charts']['status']['labels']));
        self::assertSame('Shirt', $result['charts']['topProducts'][0]['name']);
    }
}

