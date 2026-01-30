<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/orders', name: 'api_orders_')]
#[OA\Tag(name: 'Orders')]
#[Security(name: 'BearerAuth')]
class OrderApiController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/orders',
        summary: 'List orders',
        description: 'Get a paginated list of orders. Users can only see their own orders. Admins can see all orders.',
        tags: ['Orders']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Page number',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Items per page (max 100)',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)
    )]
    #[OA\Response(
        response: 200,
        description: 'List of orders',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(
                    property: 'pagination',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'limit', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'pages', type: 'integer'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        // Users can only see their own orders (unless admin)
        $criteria = ['email' => $user->getEmail()];
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $criteria = []; // Admins can see all orders
        }

        $orders = $this->orderRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
        $total = $this->orderRepository->count($criteria);

        $data = array_map(fn(Order $order) => $this->serializeOrder($order), $orders);

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    #[Route('/{orderNumber}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/orders/{orderNumber}',
        summary: 'Get order by order number',
        description: 'Get detailed information about a specific order',
        tags: ['Orders']
    )]
    #[OA\Parameter(
        name: 'orderNumber',
        in: 'path',
        description: 'Order number',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Order details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Order not found')]
    public function show(string $orderNumber): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user has access to this order
        if (!in_array('ROLE_ADMIN', $user->getRoles()) && $order->getEmail() !== $user->getEmail()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['data' => $this->serializeOrder($order, true)]);
    }

    private function serializeOrder(Order $order, bool $detailed = false): array
    {
        $data = [
            'orderNumber' => $order->getOrderNumber(),
            'email' => $order->getEmail(),
            'status' => $order->getStatus(),
            'currency' => $order->getCurrency(),
            'totals' => [
                'subtotal' => [
                    'amount' => $order->getSubtotal(),
                    'formatted' => number_format($order->getSubtotal() / 100, 2, '.', ',') . ' ' . $order->getCurrency(),
                ],
                'taxTotal' => [
                    'amount' => $order->getTaxTotal(),
                    'formatted' => number_format($order->getTaxTotal() / 100, 2, '.', ',') . ' ' . $order->getCurrency(),
                ],
                'grandTotal' => [
                    'amount' => $order->getGrandTotal(),
                    'formatted' => number_format($order->getGrandTotal() / 100, 2, '.', ',') . ' ' . $order->getCurrency(),
                ],
            ],
            'createdAt' => $order->getCreatedAt()->format('c'),
        ];

        if ($detailed) {
            $items = [];
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getNameSnapshot(),
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => [
                        'amount' => $item->getUnitPriceAmount(),
                        'formatted' => number_format($item->getUnitPriceAmount() / 100, 2, '.', ',') . ' ' . $order->getCurrency(),
                    ],
                    'taxRate' => (float) $item->getTaxRate(),
                    'total' => [
                        'amount' => $item->getTotalAmount(),
                        'formatted' => number_format($item->getTotalAmount() / 100, 2, '.', ',') . ' ' . $order->getCurrency(),
                    ],
                ];
            }
            $data['items'] = $items;

            if ($order->getTrackingNumber()) {
                $data['shipping'] = [
                    'trackingNumber' => $order->getTrackingNumber(),
                    'carrier' => $order->getCarrier(),
                    'shippedAt' => $order->getShippedAt()?->format('c'),
                ];
            }
        }

        return $data;
    }
}

