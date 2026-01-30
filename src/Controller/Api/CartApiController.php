<?php

namespace App\Controller\Api;

use App\Service\Cart\CartService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cart', name: 'api_cart_')]
#[OA\Tag(name: 'Cart')]
#[Security(name: 'BearerAuth')]
class CartApiController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/cart',
        summary: 'Get cart',
        description: 'Get the current shopping cart with all items and totals',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Cart details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
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
    #[OA\Post(
        path: '/api/v1/cart/add',
        summary: 'Add item to cart',
        description: 'Add a product variant to the shopping cart',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'variantId', type: 'integer', description: 'Product variant ID'),
                new OA\Property(property: 'quantity', type: 'integer', description: 'Quantity to add', default: 1),
            ],
            required: ['variantId']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Item added successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid request')]
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
    #[OA\Put(
        path: '/api/v1/cart/update',
        summary: 'Update cart item',
        description: 'Update the quantity of an item in the cart. If quantity is 0 or negative, the item will be removed.',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'variantId', type: 'integer', description: 'Product variant ID'),
                new OA\Property(property: 'quantity', type: 'integer', description: 'New quantity'),
            ],
            required: ['variantId', 'quantity']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Cart updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid request')]
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
    #[OA\Delete(
        path: '/api/v1/cart/remove',
        summary: 'Remove item from cart',
        description: 'Remove a specific item from the shopping cart',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'variantId', type: 'integer', description: 'Product variant ID to remove'),
            ],
            required: ['variantId']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Item removed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid request')]
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
    #[OA\Delete(
        path: '/api/v1/cart/clear',
        summary: 'Clear cart',
        description: 'Remove all items from the shopping cart',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Cart cleared successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
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

