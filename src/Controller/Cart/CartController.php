<?php

namespace App\Controller\Cart;

use App\Service\Cart\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    #[Route('/cart', name: 'cart_show', methods: ['GET'])]
    public function show(): Response
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();

        return $this->render('cart/show.html.twig', [
            'items' => $items,
            'totals' => $totals,
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

            return new JsonResponse([
                'success' => true,
                'message' => 'Cart cleared',
                'totals' => [
                    'itemsCount' => 0,
                    'totalQuantity' => 0,
                    'subtotal' => 0,
                    'currency' => 'EUR',
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

