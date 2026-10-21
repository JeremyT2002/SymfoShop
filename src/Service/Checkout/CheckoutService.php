<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\ShippingMethod;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ShippingMethodRepository;
use App\Repository\UserRepository;
use App\Service\Cart\CartService;
use App\Service\Cart\CouponService;
use App\Service\Inventory\InventoryService;
use App\Service\Tax\TaxCalculator;
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
        private readonly TaxCalculator $taxCalculator,
        private readonly UserRepository $userRepository,
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
     * Order totals for the current cart. Product and shipping prices are gross: inside the EU the
     * customer pays them unchanged and the contained VAT is reported; exports are charged net.
     *
     * @return array{
     *   subtotal: int,
     *   discount: int,
     *   shippingAmount: int,
     *   shippingMethodCode: ?string,
     *   shippingMethodLabel: ?string,
     *   taxTotal: int,
     *   taxBreakdown: list<array{rate: float, amount: int}>,
     *   pricesIncludeTax: bool,
     *   grandTotal: int,
     *   currency: string,
     *   couponCode: ?string
     * }
     */
    public function calculateTotals(?string $shippingMethodCode = null, ?string $countryCode = null, ?User $user = null): array
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();
        $export = $this->taxCalculator->isExport($countryCode);

        if (empty($items)) {
            return [
                'subtotal' => 0,
                'discount' => 0,
                'shippingAmount' => 0,
                'shippingMethodCode' => null,
                'shippingMethodLabel' => null,
                'taxTotal' => 0,
                'taxBreakdown' => [],
                'pricesIncludeTax' => !$export,
                'grandTotal' => 0,
                'currency' => 'EUR',
                'couponCode' => null,
            ];
        }

        // Charged amount per line, grouped by the VAT rate that applies to it
        $lines = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $taxClass = $item['variant']->getProduct()->getTaxClass();
            $amount = $this->taxCalculator->chargeableAmount($item['itemTotal'], $taxClass, $countryCode);
            $lines[] = ['amount' => $amount, 'rate' => $this->taxCalculator->rateFor($taxClass, $countryCode)];
            $subtotal += $amount;
        }

        $discount = 0;
        $couponCode = $totals['couponCode'] ?? null;
        if ($couponCode) {
            $validation = $this->couponService->validate($couponCode, $user, $subtotal);
            if ($validation['valid'] && $validation['coupon']) {
                $discount = $this->couponService->calculateDiscount($validation['coupon'], $subtotal);
            } else {
                $this->cartService->clearCoupon();
                $couponCode = null;
            }
        }

        $shipping = $this->resolveShippingMethod($shippingMethodCode);
        $shippingAmount = $shipping !== null
            ? $this->taxCalculator->chargeableAmount($this->shippingPrice($shipping, $subtotal - $discount), 'standard', $countryCode)
            : 0;

        // The discount reduces every line proportionally; shipping is taxed at the standard rate.
        $buckets = [];
        foreach ($lines as $line) {
            $share = $subtotal > 0 ? $line['amount'] / $subtotal : 0;
            $key = (string) $line['rate'];
            $buckets[$key] = ($buckets[$key] ?? 0) + $line['amount'] - $discount * $share;
        }
        $shippingRateKey = (string) $this->taxCalculator->rateFor('standard', $countryCode);
        $buckets[$shippingRateKey] = ($buckets[$shippingRateKey] ?? 0) + $shippingAmount;

        $breakdown = [];
        $taxTotal = 0;
        foreach ($buckets as $rate => $gross) {
            $tax = $this->taxCalculator->includedTax((int) round($gross), (float) $rate);
            if ($tax > 0) {
                $breakdown[] = ['rate' => (float) $rate, 'amount' => $tax];
                $taxTotal += $tax;
            }
        }
        usort($breakdown, static fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shippingAmount' => $shippingAmount,
            'shippingMethodCode' => $shipping?->getCode(),
            'shippingMethodLabel' => $shipping?->getName(),
            'taxTotal' => $taxTotal,
            'taxBreakdown' => $breakdown,
            'pricesIncludeTax' => !$export,
            'grandTotal' => $subtotal - $discount + $shippingAmount,
            'currency' => $totals['currency'],
            'couponCode' => $couponCode,
        ];
    }

    /**
     * Gross shipping price for the given (discounted) goods value.
     */
    private function shippingPrice(ShippingMethod $method, int $goodsValue): int
    {
        return $method->priceFor($goodsValue);
    }

    /**
     * Create order from cart with price snapshots
     */
    public function createOrder(
        CustomerInfoDTO $customerInfo,
        AddressDTO $shippingAddress,
        ?string $shippingMethodCode = null,
        ?User $user = null
    ): Order
    {
        $validation = $this->validateCart();
        if (!$validation['valid']) {
            throw new \RuntimeException('Cart validation failed: ' . implode(', ', $validation['errors']));
        }

        $items = $this->cartService->getDetailedItems();
        $countryForTax = trim($shippingAddress->country) !== '' ? trim($shippingAddress->country) : null;
        $totals = $this->calculateTotals($shippingMethodCode, $countryForTax, $user);

        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setEmail($customerInfo->email);
        $order->setFirstName($customerInfo->firstName);
        $order->setLastName($customerInfo->lastName);
        $order->setPhone($customerInfo->phone);
        $order->setShippingStreet($shippingAddress->street);
        $order->setShippingPostalCode($shippingAddress->postalCode);
        $order->setShippingCity($shippingAddress->city);
        $order->setShippingState($shippingAddress->state);
        $order->setShippingCountry($countryForTax !== null ? strtoupper($countryForTax) : null);
        $request = $this->requestStack->getMainRequest();
        $locale = $request?->getLocale() ?? $this->defaultLocale;
        if (!\in_array($locale, self::ENABLED_LOCALES, true)) {
            $locale = $this->defaultLocale;
        }
        $order->setLocale($locale);
        $order->setCurrency($totals['currency']);
        $order->setStatus('new');
        $order->setSubtotal($totals['subtotal']);
        $order->setDiscountTotal($totals['discount']);
        $order->setCouponCode($totals['couponCode']);
        $order->setTaxTotal($totals['taxTotal']);
        // New orders: grand total = subtotal - discount + shipping; taxTotal is the VAT contained (0 for exports)
        $order->setPricesIncludeTax(true);
        $order->setGrandTotal($totals['grandTotal']);
        $order->setShippingAmount($totals['shippingAmount']);
        $order->setShippingMethodCode($totals['shippingMethodCode']);
        $order->setShippingMethodLabel($totals['shippingMethodLabel']);
        if ($user instanceof User) {
            $order->setUser($user);
        } else {
            // Guest checkout with the address of a verified account: the order belongs to that account
            $owner = $this->userRepository->findOneBy(['email' => $customerInfo->email]);
            if ($owner instanceof User && $owner->isVerified()) {
                $order->setUser($owner);
            }
        }

        foreach ($items as $item) {
            $variant = $item['variant'];
            $quantity = $item['quantity'];

            $orderItem = new OrderItem();
            $orderItem->setSku($variant->getSku());
            $orderItem->setProductVariant($variant);
            $orderItem->setNameSnapshot($variant->getProduct()->getName());
            $orderItem->setQuantity($quantity);
            $taxClass = $variant->getProduct()->getTaxClass();
            $orderItem->setUnitPriceAmount($this->taxCalculator->chargeableAmount($variant->getPriceAmount(), $taxClass, $countryForTax));
            $orderItem->setTaxRate($this->formatTaxRate($this->taxCalculator->rateFor($taxClass, $countryForTax)));
            $orderItem->setTotalAmount($this->taxCalculator->chargeableAmount($item['itemTotal'], $taxClass, $countryForTax));

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
