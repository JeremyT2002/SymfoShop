<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Api\ApiCartService;
use Nelmio\ApiDocBundle\Attribute\Security as DocSecurity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cart', name: 'api_cart_')]
#[OA\Tag(name: 'Cart')]
#[DocSecurity(name: 'BearerAuth')]
class CartApiController extends AbstractController
{
    use ApiResponderTrait;

    public function __construct(
        private readonly ApiCartService $apiCartService
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/cart',
        summary: 'Get cart',
        description: 'Server-side cart for the API user (not the browser session cart).',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Cart payload',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    public function show(): JsonResponse
    {
        $user = $this->getApiUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = $this->apiCartService->getDetailedItems($user->getId());
        $totals = $this->apiCartService->getTotals($user->getId());

        $data = [
            'items' => array_map(fn (array $item) => [
                'variantId' => $item['variantId'],
                'product' => [
                    'id' => $item['variant']->getProduct()->getId(),
                    'name' => $item['variant']->getProduct()->getName(),
                    'slug' => $item['variant']->getProduct()->getSlug(),
                ],
                'variant' => [
                    'id' => $item['variant']->getId(),
                    'sku' => $item['variant']->getSku(),
                    'attributes' => $item['variant']->getAttributes(),
                ],
                'quantity' => $item['quantity'],
                'unitPrice' => [
                    'amount' => $item['variant']->getPriceAmount(),
                    'currency' => $item['variant']->getCurrency(),
                    'formatted' => number_format($item['variant']->getPriceAmount() / 100, 2, '.', ',').' '.$item['variant']->getCurrency(),
                ],
                'itemTotal' => [
                    'amount' => $item['itemTotal'],
                    'currency' => $item['variant']->getCurrency(),
                    'formatted' => number_format($item['itemTotal'] / 100, 2, '.', ',').' '.$item['variant']->getCurrency(),
                ],
            ], $items),
            'totals' => [
                'itemsCount' => $totals['itemsCount'],
                'totalQuantity' => $totals['totalQuantity'],
                'subtotal' => [
                    'amount' => $totals['subtotal'],
                    'currency' => $totals['currency'],
                    'formatted' => number_format($totals['subtotal'] / 100, 2, '.', ',').' '.$totals['currency'],
                ],
            ],
        ];

        return $this->apiData($data);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/cart/add',
        summary: 'Add line to cart',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['variantId'],
            properties: [
                new OA\Property(property: 'variantId', type: 'integer'),
                new OA\Property(property: 'quantity', type: 'integer', default: 1),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Updated cart totals', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'object'), new OA\Property(property: 'meta', type: 'object')]))]
    #[OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function add(Request $request): JsonResponse
    {
        $user = $this->requireApiUser();

        $data = $this->apiJsonBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $variantId = $data['variantId'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);

        if (!$variantId || (int) $variantId <= 0) {
            return $this->apiError('Invalid variantId', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        if ($quantity <= 0) {
            return $this->apiError('Quantity must be positive', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        try {
            $this->apiCartService->add($user->getId(), (int) $variantId, $quantity);
            $totals = $this->apiCartService->getTotals($user->getId());

            return $this->apiData(['totals' => $totals], Response::HTTP_OK, ['message' => 'Item added to cart']);
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/update', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/cart/update',
        summary: 'Update line quantity',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['variantId', 'quantity'],
            properties: [
                new OA\Property(property: 'variantId', type: 'integer'),
                new OA\Property(property: 'quantity', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'OK')]
    #[OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getApiUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $this->apiJsonBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $variantId = $data['variantId'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if (!$variantId || (int) $variantId <= 0) {
            return $this->apiError('Invalid variantId', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        if (null === $quantity) {
            return $this->apiError('Quantity is required', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        $quantity = (int) $quantity;

        if ($quantity <= 0) {
            try {
                $this->apiCartService->remove($user->getId(), (int) $variantId);
                $totals = $this->apiCartService->getTotals($user->getId());

                return $this->apiData(['totals' => $totals], Response::HTTP_OK, ['message' => 'Item removed from cart']);
            } catch (\Throwable $e) {
                return $this->apiError($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $this->apiCartService->update($user->getId(), (int) $variantId, $quantity);
            $totals = $this->apiCartService->getTotals($user->getId());

            return $this->apiData(['totals' => $totals], Response::HTTP_OK, ['message' => 'Cart updated']);
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/remove', name: 'remove', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/cart/remove',
        summary: 'Remove line',
        tags: ['Cart']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['variantId'],
            properties: [
                new OA\Property(property: 'variantId', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'OK')]
    #[OA\Response(response: 400, description: 'Bad request', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function remove(Request $request): JsonResponse
    {
        $user = $this->getApiUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $this->apiJsonBody($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $variantId = $data['variantId'] ?? null;

        if (!$variantId || (int) $variantId <= 0) {
            return $this->apiError('Invalid variantId', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        try {
            $this->apiCartService->remove($user->getId(), (int) $variantId);
            $totals = $this->apiCartService->getTotals($user->getId());

            return $this->apiData(['totals' => $totals], Response::HTTP_OK, ['message' => 'Item removed from cart']);
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/clear', name: 'clear', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/cart/clear',
        summary: 'Clear cart',
        tags: ['Cart']
    )]
    #[OA\Response(response: 200, description: 'OK')]
    public function clear(): JsonResponse
    {
        $user = $this->requireApiUser();

        try {
            $this->apiCartService->clear($user->getId());

            return $this->apiData(
                [
                    'totals' => [
                        'itemsCount' => 0,
                        'totalQuantity' => 0,
                        'subtotal' => 0,
                        'discount' => 0,
                        'currency' => 'EUR',
                        'couponCode' => null,
                    ],
                ],
                Response::HTTP_OK,
                ['message' => 'Cart cleared']
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getApiUserOrError(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->apiError('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }
}
