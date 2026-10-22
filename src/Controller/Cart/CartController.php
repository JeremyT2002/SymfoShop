<?php

namespace App\Controller\Cart;

use App\Entity\ProductStatus;
use App\Repository\ProductVariantRepository;
use App\Repository\ShippingMethodRepository;
use App\Service\Cart\CartService;
use App\Service\Cart\CouponService;
use App\Service\Catalog\RecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService,
        private readonly ProductVariantRepository $variantRepository,
        private readonly TranslatorInterface $translator,
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly RecommendationService $recommendationService,
    ) {
    }

    #[Route('/cart', name: 'cart_show', methods: ['GET'])]
    public function show(): Response
    {
        $items = $this->cartService->getDetailedItems();
        [$totals, $coupon] = $this->totalsWithDiscount();

        return $this->render('cart/show.html.twig', [
            'items' => $items,
            'recommendations' => $this->recommendationService->forProducts(array_map(static fn (array $item) => $item['variant']->getProduct(), $items), 4),
            'totals' => $totals,
            'coupon' => $coupon,
        ]);
    }

    #[Route('/cart/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');
        $quantity = (int) $request->request->get('quantity', 1);

        if ($variantId <= 0 || $quantity <= 0) {
            return $this->error('ui.cart.invalid_request');
        }

        $variant = $this->variantRepository->find($variantId);
        if ($variant === null || $variant->getProduct()?->getStatus() !== ProductStatus::ACTIVE) {
            return $this->error('ui.cart.product_unavailable', Response::HTTP_NOT_FOUND);
        }

        $inCart = 0;
        foreach ($this->cartService->getDetailedItems() as $item) {
            if ($item['variant']->getId() === $variantId) {
                $inCart = $item['quantity'];
            }
        }
        $available = $variant->getStockItem()?->getAvailable() ?? 0;
        if ($inCart + $quantity > $available) {
            return $this->error(
                $available > $inCart ? 'ui.cart.only_n_available' : 'ui.cart.out_of_stock',
                Response::HTTP_CONFLICT,
                ['%count%' => max(0, $available - $inCart)]
            );
        }

        $this->cartService->add($variantId, $quantity);

        return $this->success('cart.item_added');
    }

    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');
        $quantity = (int) $request->request->get('quantity');

        if ($variantId <= 0) {
            return $this->error('ui.cart.invalid_request');
        }

        try {
            if ($quantity <= 0) {
                $this->cartService->remove($variantId);

                return $this->success('cart.item_removed');
            }

            $available = $this->variantRepository->find($variantId)?->getStockItem()?->getAvailable() ?? 0;
            if ($quantity > $available) {
                return $this->error('ui.cart.only_n_available', Response::HTTP_CONFLICT, ['%count%' => $available]);
            }

            $this->cartService->update($variantId, $quantity);
        } catch (\InvalidArgumentException) {
            return $this->error('ui.cart.item_not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->success('cart.updated_successfully');
    }

    #[Route('/cart/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');
        if ($variantId <= 0) {
            return $this->error('ui.cart.invalid_request');
        }

        $this->cartService->remove($variantId);

        return $this->success('cart.item_removed');
    }

    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        $this->cartService->clear();
        $this->cartService->clearCoupon();

        return $this->success('cart.cart_cleared');
    }

    #[Route('/cart/coupon/apply', name: 'cart_coupon_apply', methods: ['POST'])]
    public function applyCoupon(Request $request): JsonResponse
    {
        $code = trim((string) $request->request->get('code', ''));
        if ($code === '') {
            return $this->error('ui.coupon.code_required');
        }

        $totals = $this->cartService->getTotals();
        $validation = $this->couponService->validate($code, $this->getUser(), $totals['subtotal']);
        if (!$validation['valid']) {
            return $this->error('ui.coupon.invalid');
        }

        $this->cartService->setCouponCode($code);

        return $this->success('ui.coupon.applied');
    }

    #[Route('/cart/coupon/remove', name: 'cart_coupon_remove', methods: ['POST'])]
    public function removeCoupon(): JsonResponse
    {
        $this->cartService->clearCoupon();

        return $this->success('ui.coupon.removed');
    }

    /**
     * Cart totals including the coupon discount; drops a coupon that is no longer valid.
     *
     * @return array{0: array<string, mixed>, 1: ?\App\Entity\Coupon}
     */
    private function totalsWithDiscount(): array
    {
        $totals = $this->cartService->getTotals();
        $coupon = null;
        $totals['discount'] = 0;

        if ($totals['couponCode']) {
            $validation = $this->couponService->validate($totals['couponCode'], $this->getUser(), $totals['subtotal']);
            if ($validation['valid'] && $validation['coupon']) {
                $coupon = $validation['coupon'];
                $totals['discount'] = $this->couponService->calculateDiscount($coupon, $totals['subtotal']);
            } else {
                $this->cartService->clearCoupon();
                $totals['couponCode'] = null;
            }
        }

        $totals['freeShipping'] = $this->freeShippingProgress($totals['subtotal'] - $totals['discount']);

        return [$totals, $coupon];
    }

    /**
     * Progress towards free shipping with the default shipping method, or null if it has no threshold.
     *
     * @return array{threshold: int, remaining: int}|null
     */
    private function freeShippingProgress(int $goodsValue): ?array
    {
        $threshold = $this->shippingMethodRepository->findFirstActive()?->getFreeAboveCents();
        if ($threshold === null) {
            return null;
        }

        return ['threshold' => $threshold, 'remaining' => max(0, $threshold - $goodsValue)];
    }

    private function success(string $messageKey): JsonResponse
    {
        [$totals] = $this->totalsWithDiscount();

        $lines = [];
        foreach ($this->cartService->getDetailedItems() as $item) {
            $lines[(string) $item['variant']->getId()] = [
                'quantity' => $item['quantity'],
                'total' => $item['itemTotal'],
            ];
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translator->trans($messageKey),
            'totals' => $totals,
            'lines' => $lines,
        ]);
    }

    /**
     * @param array<string, int|string> $params
     */
    private function error(string $messageKey, int $status = Response::HTTP_BAD_REQUEST, array $params = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->translator->trans($messageKey, $params),
        ], $status);
    }
}
