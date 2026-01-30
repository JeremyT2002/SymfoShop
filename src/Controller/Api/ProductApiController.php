<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/products', name: 'api_products_')]
#[OA\Tag(name: 'Products')]
#[Security(name: 'BearerAuth')]
class ProductApiController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/products',
        summary: 'List products',
        description: 'Get a paginated list of products with optional filtering',
        tags: ['Products']
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
    #[OA\Parameter(
        name: 'status',
        in: 'query',
        description: 'Filter by status',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['active', 'draft', 'archived'])
    )]
    #[OA\Response(
        response: 200,
        description: 'List of products',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: Product::class))),
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
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $status = $request->query->get('status');
        $categorySlug = $request->query->get('category');

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $products = $this->productRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
        $total = $this->productRepository->count($criteria);

        $data = array_map(fn(Product $product) => $this->serializeProduct($product), $products);

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

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/products/{slug}',
        summary: 'Get product by slug',
        description: 'Get detailed information about a specific product',
        tags: ['Products']
    )]
    #[OA\Parameter(
        name: 'slug',
        in: 'path',
        description: 'Product slug',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Product details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', ref: new Model(type: Product::class)),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function show(string $slug): JsonResponse
    {
        $product = $this->productRepository->findOneBy(['slug' => $slug]);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->serializeProduct($product, true)]);
    }

    private function serializeProduct(Product $product, bool $detailed = false): array
    {
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'status' => $product->getStatus()->value,
            'description' => $product->getDescription(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];

        if ($detailed) {
            $variants = [];
            foreach ($product->getVariants() as $variant) {
                $variantData = [
                    'id' => $variant->getId(),
                    'sku' => $variant->getSku(),
                    'price' => [
                        'amount' => $variant->getPriceAmount(),
                        'currency' => $variant->getCurrency(),
                        'formatted' => number_format($variant->getPriceAmount() / 100, 2, '.', ',') . ' ' . $variant->getCurrency(),
                    ],
                    'attributes' => $variant->getAttributes(),
                ];

                // Add stock information if available
                try {
                    $stock = $variant->getStockItem();
                    if ($stock) {
                        $variantData['stock'] = [
                            'onHand' => $stock->getOnHand(),
                            'reserved' => $stock->getReserved(),
                            'available' => $stock->getAvailable(),
                        ];
                    }
                } catch (\Exception $e) {
                    // Stock item might not exist, skip it
                }

                $variants[] = $variantData;
            }

            $data['variants'] = $variants;

            $media = [];
            foreach ($product->getMedia() as $mediaItem) {
                $media[] = [
                    'id' => $mediaItem->getId(),
                    'path' => $mediaItem->getPath(),
                    'alt' => $mediaItem->getAlt(),
                    'sort' => $mediaItem->getSort(),
                ];
            }
            $data['media'] = $media;
        } else {
            // For list view, include price range
            $variants = $product->getVariants();
            if ($variants->count() > 0) {
                $prices = $variants->map(fn($v) => $v->getPriceAmount())->toArray();
                $minPrice = min($prices);
                $maxPrice = max($prices);
                $currency = $variants->first()->getCurrency();

                $data['price'] = [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                    'currency' => $currency,
                    'formatted' => $minPrice === $maxPrice
                        ? number_format($minPrice / 100, 2, '.', ',') . ' ' . $currency
                        : number_format($minPrice / 100, 2, '.', ',') . ' - ' . number_format($maxPrice / 100, 2, '.', ',') . ' ' . $currency,
                ];
            }
        }

        return $data;
    }
}

