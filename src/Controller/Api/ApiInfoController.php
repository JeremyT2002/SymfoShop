<?php

namespace App\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_info_')]
#[OA\Tag(name: 'API Info')]
class ApiInfoController extends AbstractController
{
    use ApiResponderTrait;

    #[Route('', name: 'info', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1',
        summary: 'API Information',
        description: 'Public endpoint: API version, entry paths, and how to authenticate. No API key required.',
        tags: ['API Info'],
        security: []
    )]
    #[OA\Response(
        response: 200,
        description: 'API information',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'endpoints', type: 'object'),
                        new OA\Property(property: 'authentication', type: 'object'),
                        new OA\Property(property: 'documentation', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    public function info(): JsonResponse
    {
        return $this->apiData([
            'name' => 'SymfoShop API',
            'version' => '1.0.0',
            'description' => 'RESTful API for SymfoShop e-commerce platform',
            'endpoints' => [
                'products' => '/api/v1/products',
                'categories' => '/api/v1/categories',
                'cart' => '/api/v1/cart',
                'orders' => '/api/v1/orders',
                'apiKeys' => '/api/v1/api-keys',
                'auth' => '/api/v1/auth',
            ],
            'authentication' => [
                'type' => 'API Key',
                'methods' => [
                    'Authorization: Bearer <api-key>',
                    'X-API-Key: <api-key>',
                    'Query: ?api_key=<api-key> (avoid in production)',
                ],
            ],
            'documentation' => '/api/v1/docs',
        ]);
    }
}
