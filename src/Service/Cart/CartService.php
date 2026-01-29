<?php

namespace App\Service\Cart;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const SESSION_KEY = 'cart_items';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductVariantRepository $variantRepository
    ) {
    }

    private function getSession()
    {
        return $this->requestStack->getSession();
    }

    /**
     * Add item to cart or update quantity if already exists
     */
    public function add(int $variantId, int $quantity): void
    {
        $items = $this->getItems();
        $existingItem = $this->findItem($items, $variantId);

        if ($existingItem !== null) {
            $items[$variantId] = $existingItem->withQuantity($existingItem->quantity + $quantity);
        } else {
            $items[$variantId] = new CartItem($variantId, $quantity);
        }

        $this->saveItems($items);
    }

    /**
     * Update quantity of an existing cart item
     */
    public function update(int $variantId, int $quantity): void
    {
        $items = $this->getItems();

        if (!isset($items[$variantId])) {
            throw new \InvalidArgumentException('Cart item not found');
        }

        $items[$variantId] = new CartItem($variantId, $quantity);
        $this->saveItems($items);
    }

    /**
     * Remove item from cart
     */
    public function remove(int $variantId): void
    {
        $items = $this->getItems();
        unset($items[$variantId]);
        $this->saveItems($items);
    }

    /**
     * Clear all items from cart
     */
    public function clear(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * Get all cart items with detailed product information
     *
     * @return array<int, array{variant: ProductVariant, quantity: int, itemTotal: int}>
     */
    public function getDetailedItems(): array
    {
        $items = $this->getItems();
        $detailedItems = [];

        foreach ($items as $item) {
            $variant = $this->variantRepository->find($item->variantId);

            if ($variant === null) {
                // Remove invalid items from cart
                $this->remove($item->variantId);
                continue;
            }

            $detailedItems[] = [
                'variant' => $variant,
                'quantity' => $item->quantity,
                'itemTotal' => $item->quantity * $variant->getPriceAmount(),
            ];
        }

        return $detailedItems;
    }

    /**
     * Get cart totals
     *
     * @return array{itemsCount: int, totalQuantity: int, subtotal: int, currency: string}
     */
    public function getTotals(): array
    {
        $detailedItems = $this->getDetailedItems();

        if (empty($detailedItems)) {
            return [
                'itemsCount' => 0,
                'totalQuantity' => 0,
                'subtotal' => 0,
                'currency' => 'EUR',
            ];
        }

        $totalQuantity = 0;
        $subtotal = 0;
        $currency = $detailedItems[0]['variant']->getCurrency();

        foreach ($detailedItems as $item) {
            $totalQuantity += $item['quantity'];
            $subtotal += $item['itemTotal'];

            // Ensure all items have the same currency (or handle multi-currency later)
            if ($item['variant']->getCurrency() !== $currency) {
                // For now, use the first currency found
                // In production, you might want to handle multi-currency carts differently
            }
        }

        return [
            'itemsCount' => count($detailedItems),
            'totalQuantity' => $totalQuantity,
            'subtotal' => $subtotal,
            'currency' => $currency,
        ];
    }

    /**
     * Get raw cart items from session
     *
     * @return array<int, CartItem>
     */
    private function getItems(): array
    {
        $data = $this->getSession()->get(self::SESSION_KEY, []);

        if (!is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $variantId => $itemData) {
            if (!isset($itemData['variantId']) || !isset($itemData['quantity'])) {
                continue;
            }

            try {
                $items[(int) $variantId] = new CartItem(
                    (int) $itemData['variantId'],
                    (int) $itemData['quantity']
                );
            } catch (\InvalidArgumentException $e) {
                // Skip invalid items
                continue;
            }
        }

        return $items;
    }

    /**
     * Save cart items to session
     *
     * @param array<int, CartItem> $items
     */
    private function saveItems(array $items): void
    {
        $data = [];
        foreach ($items as $item) {
            $data[$item->variantId] = [
                'variantId' => $item->variantId,
                'quantity' => $item->quantity,
            ];
        }

        $this->getSession()->set(self::SESSION_KEY, $data);
    }

    /**
     * Find cart item by variant ID
     *
     * @param array<int, CartItem> $items
     */
    private function findItem(array $items, int $variantId): ?CartItem
    {
        return $items[$variantId] ?? null;
    }
}

