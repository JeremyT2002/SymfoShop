<?php

namespace App\Controller\Api;

use App\Service\Cart\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cart', name: 'api_cart_')]
class CartApiController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $items = $this->cartService->getDetailedItems();
        $totals = $this->cartService->getTotals();

        $data = [
            'items' => array_map(fn($item) => [
                'variantId' => $item->variantId,
                'product' => [
                    'id' => $item->variant->getProduct()->getId(),
                    'name' => $item->variant->getProduct()->getName(),
                    'slug' => $item->variant->getProduct()->getSlug(),
                ],
                'variant' => [
                    'id' => $item->variant->getId(),
                    'sku' => $item->variant->getSku(),
                    'attributes' => $item->variant->getAttributes(),
                ],
                'quantity' => $item->quantity,
                'unitPrice' => [
                    'amount' => $item->variant->getPriceAmount(),
                    'currency' => $item->variant->getCurrency(),
                    'formatted' => number_format($item->variant->getPriceAmount() / 100, 2, '.', ',') . ' ' . $item->variant->getCurrency(),
                ],
                'itemTotal' => [
                    'amount' => $item->itemTotal,
                    'currency' => $item->variant->getCurrency(),
                    'formatted' => number_format($item->itemTotal / 100, 2, '.', ',') . ' ' . $item->variant->getCurrency(),
                ],
            ], $items),
            'totals' => [
                'itemsCount' => $totals['itemsCount'],
                'totalQuantity' => $totals['totalQuantity'],
                'subtotal' => [
                    'amount' => $totals['subtotal'],
                    'currency' => $totals['currency'],
                    'formatted' => number_format($totals['subtotal'] / 100, 2, '.', ',') . ' ' . $totals['currency'],
                ],
            ],
        ];

        return $this->json(['data' => $data]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $variantId = $data['variantId'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (!$variantId || $variantId <= 0) {
            return $this->json(['error' => 'Invalid variant ID'], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity <= 0) {
            return $this->json(['error' => 'Quantity must be positive'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->cartService->add((int) $variantId, (int) $quantity);
            $totals = $this->cartService->getTotals();

            return $this->json([
                'success' => true,
                'message' => 'Item added to cart',
                'data' => ['totals' => $totals],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/update', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $variantId = $data['variantId'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if (!$variantId || $variantId <= 0) {
            return $this->json(['error' => 'Invalid variant ID'], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity === null) {
            return $this->json(['error' => 'Quantity is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            try {
                $this->cartService->remove((int) $variantId);
                $totals = $this->cartService->getTotals();

                return $this->json([
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'data' => ['totals' => $totals],
                ]);
            } catch (\Exception $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $this->cartService->update((int) $variantId, (int) $quantity);
            $totals = $this->cartService->getTotals();

            return $this->json([
                'success' => true,
                'message' => 'Cart updated',
                'data' => ['totals' => $totals],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/remove', name: 'remove', methods: ['DELETE'])]
    public function remove(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $variantId = $data['variantId'] ?? null;

        if (!$variantId || $variantId <= 0) {
            return $this->json(['error' => 'Invalid variant ID'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->cartService->remove((int) $variantId);
            $totals = $this->cartService->getTotals();

            return $this->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'data' => ['totals' => $totals],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/clear', name: 'clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        try {
            $this->cartService->clear();

            return $this->json([
                'success' => true,
                'message' => 'Cart cleared',
                'data' => [
                    'totals' => [
                        'itemsCount' => 0,
                        'totalQuantity' => 0,
                        'subtotal' => 0,
                        'currency' => 'EUR',
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

