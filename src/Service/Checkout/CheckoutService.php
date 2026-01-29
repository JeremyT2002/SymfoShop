<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Repository\OrderRepository;
use App\Service\Cart\CartService;
use App\Service\Inventory\InventoryService;
use Doctrine\ORM\EntityManagerInterface;

class CheckoutService
{
    private const TAX_RATE = '0.2000'; // 20% default tax rate

    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly InventoryService $inventoryService
    ) {
    }

    /**
     * Validate that cart has items and all variants are valid
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateCart(): array
    {
        $items = $this->cartService->getDetailedItems();

        if (empty($items)) {
            return [
                'valid' => false,
                'errors' => ['Cart is empty'],
            ];
        }

        $errors = [];
        foreach ($items as $item) {
            $variant = $item['variant'];
            if (!$variant instanceof ProductVariant) {
                $errors[] = 'Invalid product variant in cart';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate inventory availability for cart items
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateInventory(): array
    {
        $items = $this->cartService->getDetailedItems();

        if (empty($items)) {
            return [
                'valid' => false,
                'errors' => ['Cart is empty'],
            ];
        }

        $errors = [];
        foreach ($items as $item) {
            $variant = $item['variant'];
            $quantity = $item['quantity'];

            $stockItem = $this->inventoryService->getStockItem($variant);
            if (!$stockItem) {
                $errors[] = sprintf('Stock not available for %s', $variant->getSku());
                continue;
            }

            $available = $stockItem->getAvailable();
            if ($available < $quantity) {
                $errors[] = sprintf(
                    'Insufficient stock for %s. Available: %d, Required: %d',
                    $variant->getSku(),
                    $available,
                    $quantity
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Calculate totals for cart items
     *
     * @return array{subtotal: int, taxTotal: int, grandTotal: int, currency: string}
     */
    public function calculateTotals(): array
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();

        if (empty($items)) {
            return [
                'subtotal' => 0,
                'taxTotal' => 0,
                'grandTotal' => 0,
                'currency' => 'EUR',
            ];
        }

        $subtotal = $totals['subtotal'];
        $taxRate = (float) self::TAX_RATE;
        $taxTotal = (int) round($subtotal * $taxRate);
        $grandTotal = $subtotal + $taxTotal;

        return [
            'subtotal' => $subtotal,
            'taxTotal' => $taxTotal,
            'grandTotal' => $grandTotal,
            'currency' => $totals['currency'],
        ];
    }

    /**
     * Create order from cart with price snapshots
     */
    public function createOrder(CustomerInfoDTO $customerInfo, AddressDTO $shippingAddress): Order
    {
        $validation = $this->validateCart();
        if (!$validation['valid']) {
            throw new \RuntimeException('Cart validation failed: ' . implode(', ', $validation['errors']));
        }

        $items = $this->cartService->getDetailedItems();
        $totals = $this->calculateTotals();

        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setEmail($customerInfo->email);
        $order->setCurrency($totals['currency']);
        $order->setStatus('new');
        $order->setSubtotal($totals['subtotal']);
        $order->setTaxTotal($totals['taxTotal']);
        $order->setGrandTotal($totals['grandTotal']);

        // Create order items with price snapshots
        foreach ($items as $item) {
            $variant = $item['variant'];
            $quantity = $item['quantity'];

            $orderItem = new OrderItem();
            $orderItem->setSku($variant->getSku());
            $orderItem->setNameSnapshot($variant->getProduct()->getName());
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPriceAmount($variant->getPriceAmount()); // Frozen price
            $orderItem->setTaxRate(self::TAX_RATE);
            $orderItem->setTotalAmount($item['itemTotal']); // Frozen total

            $order->addItem($orderItem);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Reserve inventory for the order
        $reservationResult = $this->inventoryService->reserve($order);
        if (!$reservationResult['success']) {
            // Rollback order creation if reservation fails
            $this->entityManager->remove($order);
            $this->entityManager->flush();
            throw new \RuntimeException('Inventory reservation failed: ' . implode(', ', $reservationResult['errors']));
        }

        // Clear cart after successful order creation
        $this->cartService->clear();

        return $order;
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -8)) . '-' . date('Ymd');
            $existing = $this->orderRepository->findOneByOrderNumber($orderNumber);
        } while ($existing !== null);

        return $orderNumber;
    }
}

