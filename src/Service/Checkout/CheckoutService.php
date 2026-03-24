<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\ShippingMethod;
use App\Repository\OrderRepository;
use App\Repository\ShippingMethodRepository;
use App\Service\Cart\CartService;
use App\Service\Cart\CouponService;
use App\Service\Inventory\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

class CheckoutService
{
    /** @var list<string> */
    private const ENABLED_LOCALES = ['en', 'de', 'fr'];

    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly InventoryService $inventoryService,
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly RequestStack $requestStack,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
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
     * @return array{
     *   subtotal: int,
     *   discount: int,
     *   shippingAmount: int,
     *   shippingMethodCode: ?string,
     *   shippingMethodLabel: ?string,
     *   taxTotal: int,
     *   grandTotal: int,
     *   currency: string,
     *   couponCode: ?string
     * }
     */
    public function calculateTotals(?string $shippingMethodCode = null, ?string $countryCode = null): array
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();

        if (empty($items)) {
            return [
                'subtotal' => 0,
                'discount' => 0,
                'shippingAmount' => 0,
                'shippingMethodCode' => null,
                'shippingMethodLabel' => null,
                'taxTotal' => 0,
                'grandTotal' => 0,
                'currency' => 'EUR',
                'couponCode' => null,
            ];
        }

        $subtotal = $totals['subtotal'];
        $discount = 0;
        $couponCode = $totals['couponCode'] ?? null;

        if ($couponCode) {
            $user = null;
            $validation = $this->couponService->validate($couponCode, $user, $subtotal);
            if ($validation['valid'] && $validation['coupon']) {
                $discount = $this->couponService->calculateDiscount($validation['coupon'], $subtotal);
            } else {
                $this->cartService->clearCoupon();
                $couponCode = null;
            }
        }

        $subtotalAfterDiscount = $subtotal - $discount;
        $shipping = $this->resolveShippingMethod($shippingMethodCode);
        $shippingAmount = $shipping?->getAmountCents() ?? 0;
        $shippingCode = $shipping?->getCode();
        $shippingLabel = $shipping?->getName();

        $taxRate = $this->resolveTaxRateForCountry($countryCode);
        $taxableBase = $subtotalAfterDiscount + $shippingAmount;
        $taxTotal = (int) round($taxableBase * $taxRate);
        $grandTotal = $taxableBase + $taxTotal;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shippingAmount' => $shippingAmount,
            'shippingMethodCode' => $shippingCode,
            'shippingMethodLabel' => $shippingLabel,
            'taxTotal' => $taxTotal,
            'grandTotal' => $grandTotal,
            'currency' => $totals['currency'],
            'couponCode' => $couponCode,
        ];
    }

    /**
     * Create order from cart with price snapshots
     */
    public function createOrder(CustomerInfoDTO $customerInfo, AddressDTO $shippingAddress, ?string $shippingMethodCode = null): Order
    {
        $validation = $this->validateCart();
        if (!$validation['valid']) {
            throw new \RuntimeException('Cart validation failed: ' . implode(', ', $validation['errors']));
        }

        $items = $this->cartService->getDetailedItems();
        $countryForTax = trim($shippingAddress->country) !== '' ? trim($shippingAddress->country) : null;
        $totals = $this->calculateTotals($shippingMethodCode, $countryForTax);
        $taxRateStr = $this->formatTaxRate($this->resolveTaxRateForCountry($countryForTax));

        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setEmail($customerInfo->email);
        $request = $this->requestStack->getMainRequest();
        $locale = $request?->getLocale() ?? $this->defaultLocale;
        if (!\in_array($locale, self::ENABLED_LOCALES, true)) {
            $locale = $this->defaultLocale;
        }
        $order->setLocale($locale);
        $order->setCurrency($totals['currency']);
        $order->setStatus('new');
        $order->setSubtotal($totals['subtotal']);
        $order->setTaxTotal($totals['taxTotal']);
        $order->setGrandTotal($totals['grandTotal']);
        $order->setShippingAmount($totals['shippingAmount']);
        $order->setShippingMethodCode($totals['shippingMethodCode']);
        $order->setShippingMethodLabel($totals['shippingMethodLabel']);

        foreach ($items as $item) {
            $variant = $item['variant'];
            $quantity = $item['quantity'];

            $orderItem = new OrderItem();
            $orderItem->setSku($variant->getSku());
            $orderItem->setNameSnapshot($variant->getProduct()->getName());
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPriceAmount($variant->getPriceAmount());
            $orderItem->setTaxRate($taxRateStr);
            $orderItem->setTotalAmount($item['itemTotal']);

            $order->addItem($orderItem);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $reservationResult = $this->inventoryService->reserve($order);
        if (!$reservationResult['success']) {
            $this->entityManager->remove($order);
            $this->entityManager->flush();
            throw new \RuntimeException('Inventory reservation failed: ' . implode(', ', $reservationResult['errors']));
        }

        $this->cartService->clear();

        return $order;
    }

    private function resolveShippingMethod(?string $code): ?ShippingMethod
    {
        $active = $this->shippingMethodRepository->findActiveOrdered();
        if ($active === []) {
            return null;
        }

        if ($code !== null && $code !== '') {
            $found = $this->shippingMethodRepository->findOneByCode($code);
            if ($found !== null && $found->isActive()) {
                return $found;
            }
        }

        return $this->shippingMethodRepository->findFirstActive();
    }

    private function resolveTaxRateForCountry(?string $countryCode): float
    {
        $cc = $countryCode !== null ? strtoupper(trim($countryCode)) : '';
        if ($cc === '') {
            return 0.19;
        }

        return match ($cc) {
            'DE' => 0.19,
            'FR' => 0.20,
            'US' => 0.0,
            default => 0.19,
        };
    }

    private function formatTaxRate(float $rate): string
    {
        return number_format($rate, 4, '.', '');
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
