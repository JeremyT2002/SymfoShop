<?php

namespace App\Service\Api;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Per-user cart for stateless API requests (not session-based).
 */
final class ApiCartService
{
    private const KEY_PREFIX = 'api_cart_v1_';

    public function __construct(
        private readonly CacheItemPoolInterface $apiCartCache,
        private readonly ProductVariantRepository $variantRepository,
    ) {
    }

    public function add(int $userId, int $variantId, int $quantity): void
    {
        $items = $this->load($userId);
        $items[$variantId] = ($items[$variantId] ?? 0) + $quantity;
        $this->save($userId, $items);
    }

    public function update(int $userId, int $variantId, int $quantity): void
    {
        $items = $this->load($userId);
        if (!isset($items[$variantId])) {
            throw new \InvalidArgumentException('Cart item not found');
        }
        $items[$variantId] = $quantity;
        $this->save($userId, $items);
    }

    public function remove(int $userId, int $variantId): void
    {
        $items = $this->load($userId);
        unset($items[$variantId]);
        $this->save($userId, $items);
    }

    public function clear(int $userId): void
    {
        $this->apiCartCache->deleteItem($this->key($userId));
    }

    /**
     * @return list<array{variant: ProductVariant, quantity: int, itemTotal: int, variantId: int}>
     */
    public function getDetailedItems(int $userId): array
    {
        $items = $this->load($userId);
        $detailed = [];

        foreach ($items as $variantId => $quantity) {
            $variantId = (int) $variantId;
            $quantity = (int) $quantity;
            $variant = $this->variantRepository->find($variantId);

            if (null === $variant) {
                unset($items[$variantId]);
                $this->save($userId, $items);
                continue;
            }

            $detailed[] = [
                'variantId' => $variantId,
                'variant' => $variant,
                'quantity' => $quantity,
                'itemTotal' => $quantity * $variant->getPriceAmount(),
            ];
        }

        return $detailed;
    }

    /**
     * @return array{itemsCount: int, totalQuantity: int, subtotal: int, discount: int, currency: string, couponCode: null}
     */
    public function getTotals(int $userId): array
    {
        $detailedItems = $this->getDetailedItems($userId);

        if ([] === $detailedItems) {
            return [
                'itemsCount' => 0,
                'totalQuantity' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'currency' => 'EUR',
                'couponCode' => null,
            ];
        }

        $totalQuantity = 0;
        $subtotal = 0;
        $currency = $detailedItems[0]['variant']->getCurrency();

        foreach ($detailedItems as $item) {
            $totalQuantity += $item['quantity'];
            $subtotal += $item['itemTotal'];
        }

        return [
            'itemsCount' => \count($detailedItems),
            'totalQuantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'discount' => 0,
            'currency' => $currency,
            'couponCode' => null,
        ];
    }

    private function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }

    /**
     * @return array<int, int> variantId => quantity
     */
    private function load(int $userId): array
    {
        $item = $this->apiCartCache->getItem($this->key($userId));
        if (!$item->isHit()) {
            return [];
        }

        $data = $item->get();
        if (!\is_array($data)) {
            return [];
        }

        $normalized = [];
        foreach ($data as $vid => $qty) {
            $normalized[(int) $vid] = (int) $qty;
        }

        return $normalized;
    }

    /**
     * @param array<int, int> $items
     */
    private function save(int $userId, array $items): void
    {
        $item = $this->apiCartCache->getItem($this->key($userId));
        $item->set($items);
        $item->expiresAfter(60 * 60 * 24 * 60);
        $this->apiCartCache->save($item);
    }
}
