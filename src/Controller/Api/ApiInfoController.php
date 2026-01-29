<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_info_')]
class ApiInfoController extends AbstractController
{
    #[Route('', name: 'info', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return $this->json([
            'name' => 'SymfoShop API',
            'version' => '1.0.0',
            'description' => 'RESTful API for SymfoShop e-commerce platform',
            'endpoints' => [
                'products' => '/api/v1/products',
                'categories' => '/api/v1/categories',
                'cart' => '/api/v1/cart',
                'orders' => '/api/v1/orders',
                'api-keys' => '/api/v1/api-keys',
                'auth' => '/api/v1/auth',
            ],
            'authentication' => [
                'type' => 'API Key',
                'methods' => [
                    'Authorization: Bearer <api-key>',
                    'X-API-Key: <api-key>',
                    'Query parameter: ?api_key=<api-key>',
                ],
            ],
            'documentation' => '/api/v1/docs',
        ]);
    }
}

