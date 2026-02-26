<?php

namespace App\Controller\Cart;

use App\Service\Cart\CartService;
use App\Service\Cart\CouponService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService
    ) {
    }

    #[Route('/cart', name: 'cart_show', methods: ['GET'])]
    public function show(): Response
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();
        
        // Calculate discount if coupon is applied
        $discount = 0;
        $coupon = null;
        if ($totals['couponCode']) {
            $validation = $this->couponService->validate($totals['couponCode'], $this->getUser(), $totals['subtotal']);
            if ($validation['valid'] && $validation['coupon']) {
                $coupon = $validation['coupon'];
                $discount = $this->couponService->calculateDiscount($coupon, $totals['subtotal']);
            } else {
                // Invalid coupon, clear it
                $this->cartService->clearCoupon();
                $totals = $this->cartService->getTotals();
            }
        }
        
        $totals['discount'] = $discount;

        return $this->render('cart/show.html.twig', [
            'items' => $items,
            'totals' => $totals,
            'coupon' => $coupon,
        ]);
    }

    #[Route('/cart/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');
        $quantity = (int) $request->request->get('quantity', 1);

        if ($variantId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid variant ID',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Quantity must be positive',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->cartService->add($variantId, $quantity);
            $totals = $this->cartService->getTotals();

            return new JsonResponse([
                'success' => true,
                'message' => 'Item added to cart',
                'totals' => $totals,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');
        $quantity = (int) $request->request->get('quantity');

        if ($variantId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid variant ID',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity <= 0) {
            // If quantity is 0 or negative, remove the item
            try {
                $this->cartService->remove($variantId);
                $totals = $this->cartService->getTotals();

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'totals' => $totals,
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $this->cartService->update($variantId, $quantity);
            $totals = $this->cartService->getTotals();

            return new JsonResponse([
                'success' => true,
                'message' => 'Cart updated',
                'totals' => $totals,
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/cart/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $variantId = (int) $request->request->get('variantId');

        if ($variantId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid variant ID',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->cartService->remove($variantId);
            $totals = $this->cartService->getTotals();

            return new JsonResponse([
                'success' => true,
                'message' => 'Item removed from cart',
                'totals' => $totals,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        try {
            $this->cartService->clear();
            $this->cartService->clearCoupon();

            return new JsonResponse([
                'success' => true,
                'message' => 'Cart cleared',
                'totals' => [
                    'itemsCount' => 0,
                    'totalQuantity' => 0,
                    'subtotal' => 0,
                    'discount' => 0,
                    'currency' => 'EUR',
                    'couponCode' => null,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/cart/coupon/apply', name: 'cart_coupon_apply', methods: ['POST'])]
    public function applyCoupon(Request $request): JsonResponse
    {
        $code = trim($request->request->get('code', ''));
        $user = $this->getUser();

        if (empty($code)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Coupon code is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $totals = $this->cartService->getTotals();
        $validation = $this->couponService->validate($code, $user, $totals['subtotal']);

        if (!$validation['valid']) {
            return new JsonResponse([
                'success' => false,
                'message' => implode(', ', $validation['errors']),
                'errors' => $validation['errors'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $coupon = $validation['coupon'];
        $discount = $this->couponService->calculateDiscount($coupon, $totals['subtotal']);

        // Store coupon code in session
        $this->cartService->setCouponCode($code);

        // Get updated totals with discount
        $updatedTotals = $this->cartService->getTotals();
        $updatedTotals['discount'] = $discount;

        return new JsonResponse([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'coupon' => [
                'code' => $coupon->getCode(),
                'type' => $coupon->getType()->value,
                'discount' => $discount,
            ],
            'totals' => $updatedTotals,
        ]);
    }

    #[Route('/cart/coupon/remove', name: 'cart_coupon_remove', methods: ['POST'])]
    public function removeCoupon(): JsonResponse
    {
        $this->cartService->clearCoupon();
        $totals = $this->cartService->getTotals();

        return new JsonResponse([
            'success' => true,
            'message' => 'Coupon removed',
            'totals' => $totals,
        ]);
    }
}

