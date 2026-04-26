<?php

namespace App\Service\Admin;

use App\Entity\Order;
use App\Entity\ProductReview;
use App\Entity\StockItem;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductReviewRepository;
use App\Repository\StockItemRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminActivityService
{
    private const SEEN_SIGNATURES_SESSION_KEY = 'admin.notifications.seen_signatures';

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ProductReviewRepository $productReviewRepository,
        private readonly StockItemRepository $stockItemRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array{unreadCount:int,items:list<array<string,mixed>>}
     */
    public function getNotifications(): array
    {
        $items = [];

        $pendingReviews = $this->productReviewRepository->countPending();
        if ($pendingReviews > 0) {
            $items[] = [
                'type' => 'review_pending',
                'title' => 'Pending reviews need moderation',
                'detail' => sprintf('%d review(s) waiting for approval', $pendingReviews),
                'url' => 'admin_reviews_index',
                'at' => new \DateTimeImmutable(),
                'severity' => 'warning',
            ];
        }

        $lowStockItems = $this->stockItemRepository->findLowStockItems(5, 1);
        if ($lowStockItems !== []) {
            /** @var StockItem $stock */
            $stock = $lowStockItems[0];
            $product = $stock->getVariant()->getProduct();
            $items[] = [
                'type' => 'low_stock',
                'title' => 'Low stock detected',
                'detail' => sprintf('%s is low (%d available)', $product->getName(), $stock->getAvailable()),
                'url' => 'admin_products_show',
                'routeParams' => ['id' => $product->getId()],
                'at' => new \DateTimeImmutable(),
                'severity' => 'danger',
            ];
        }

        $latestOrders = $this->orderRepository->findBy([], ['createdAt' => 'DESC'], 3);
        foreach ($latestOrders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            $items[] = [
                'type' => 'order_new',
                'title' => 'New order received',
                'detail' => sprintf('%s (%s)', $order->getOrderNumber(), $order->getEmail()),
                'url' => 'admin_orders_show',
                'routeParams' => ['id' => $order->getId()],
                'at' => $order->getCreatedAt(),
                'severity' => 'info',
            ];
        }

        usort($items, static function (array $a, array $b): int {
            /** @var \DateTimeInterface $aAt */
            $aAt = $a['at'];
            /** @var \DateTimeInterface $bAt */
            $bAt = $b['at'];

            return $bAt <=> $aAt;
        });

        $items = array_slice($items, 0, 8);
        $seenSignatures = $this->getSeenSignatures();
        $unreadCount = 0;

        foreach ($items as $index => $item) {
            $signature = $this->buildNotificationSignature($item);
            $isRead = in_array($signature, $seenSignatures, true);
            if (!$isRead) {
                $unreadCount++;
            }

            $items[$index]['signature'] = $signature;
            $items[$index]['isRead'] = $isRead;
        }

        return [
            'unreadCount' => $unreadCount,
            'items' => $items,
        ];
    }

    public function markAllNotificationsAsSeen(): void
    {
        $notifications = $this->getNotifications();
        $signatures = array_map(
            static fn (array $item): string => (string) ($item['signature'] ?? ''),
            $notifications['items']
        );
        $signatures = array_values(array_filter($signatures, static fn (string $value): bool => $value !== ''));

        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set(self::SEEN_SIGNATURES_SESSION_KEY, $signatures);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAuditEntries(int $limit = 80): array
    {
        $entries = [];

        $orders = $this->orderRepository->findBy([], ['createdAt' => 'DESC'], 30);
        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            $entries[] = [
                'type' => 'order',
                'title' => sprintf('Order %s created', $order->getOrderNumber()),
                'detail' => sprintf('Customer: %s, status: %s', $order->getEmail(), $order->getStatus()),
                'route' => 'admin_orders_show',
                'routeParams' => ['id' => $order->getId()],
                'at' => $order->getCreatedAt(),
            ];
        }

        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC'], 30);
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $entries[] = [
                'type' => 'user',
                'title' => 'User registered',
                'detail' => (string) $user->getEmail(),
                'route' => 'admin_users_show',
                'routeParams' => ['id' => $user->getId()],
                'at' => $user->getCreatedAt(),
            ];
        }

        $reviews = $this->productReviewRepository->findBy([], ['createdAt' => 'DESC'], 30);
        foreach ($reviews as $review) {
            if (!$review instanceof ProductReview) {
                continue;
            }

            $productName = $review->getProduct()?->getName() ?? 'Unknown product';
            $entries[] = [
                'type' => 'review',
                'title' => sprintf('Review submitted for %s', $productName),
                'detail' => sprintf(
                    'By %s, rating %d/5, approved: %s',
                    $review->getUser()?->getEmail() ?? 'n/a',
                    $review->getRating(),
                    $review->isApproved() ? 'yes' : 'no'
                ),
                'route' => 'admin_reviews_index',
                'routeParams' => [],
                'at' => $review->getCreatedAt(),
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            /** @var \DateTimeInterface $aAt */
            $aAt = $a['at'];
            /** @var \DateTimeInterface $bAt */
            $bAt = $b['at'];

            return $bAt <=> $aAt;
        });

        return array_slice($entries, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function getSeenSignatures(): array
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return [];
        }

        $seen = $request->getSession()->get(self::SEEN_SIGNATURES_SESSION_KEY, []);
        if (!is_array($seen)) {
            return [];
        }

        return array_values(array_filter($seen, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @param array<string,mixed> $item
     */
    private function buildNotificationSignature(array $item): string
    {
        $payload = [
            'type' => (string) ($item['type'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'detail' => (string) ($item['detail'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'routeParams' => $item['routeParams'] ?? [],
        ];

        return hash('sha256', (string) json_encode($payload));
    }
}

