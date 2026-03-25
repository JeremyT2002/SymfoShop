<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security as DocSecurity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/products', name: 'api_products_')]
#[OA\Tag(name: 'Products')]
#[DocSecurity(name: 'BearerAuth')]
class ProductApiController extends AbstractController
{
    use ApiResponderTrait;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/products',
        summary: 'List products',
        description: 'Paginated product list with optional filters.',
        tags: ['Products']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)
    )]
    #[OA\Parameter(
        name: 'status',
        in: 'query',
        schema: new OA\Schema(type: 'string', enum: ['active', 'draft', 'archived'])
    )]
    #[OA\Parameter(
        name: 'category',
        in: 'query',
        description: 'Filter by category slug',
        schema: new OA\Schema(type: 'string')
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
    #[OA\Response(response: 400, description: 'Invalid query', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 404, description: 'Unknown category slug', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $criteria = [];

        $status = $request->query->get('status');
        if (null !== $status && '' !== $status) {
            $statusEnum = ProductStatus::tryFrom((string) $status);
            if (null === $statusEnum) {
                return $this->apiError('Invalid status; use active, draft, or archived', Response::HTTP_BAD_REQUEST, 'INVALID_QUERY');
            }
            $criteria['status'] = $statusEnum;
        }

        $categorySlug = $request->query->get('category');
        if (null !== $categorySlug && '' !== trim((string) $categorySlug)) {
            $categorySlug = trim((string) $categorySlug);
            $category = $this->categoryRepository->findOneBy(['slug' => $categorySlug]);
            if (!$category) {
                return $this->apiError('Category not found', Response::HTTP_NOT_FOUND);
            }
            $criteria['category'] = $category;
        }

        $products = $this->productRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
        $total = $this->productRepository->count($criteria);

        $data = array_map(fn (Product $product) => $this->serializeProduct($product), $products);

        return $this->apiCollection(
            $data,
            [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ]
        );
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/products/{slug}',
        summary: 'Get product by slug',
        tags: ['Products']
    )]
    #[OA\Parameter(
        name: 'slug',
        in: 'path',
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
    #[OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function show(string $slug): JsonResponse
    {
        $product = $this->productRepository->findOneBy(['slug' => $slug]);

        if (!$product) {
            return $this->apiError('Product not found', Response::HTTP_NOT_FOUND);
        }

        return $this->apiData($this->serializeProduct($product, true));
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
                        'formatted' => number_format($variant->getPriceAmount() / 100, 2, '.', ',').' '.$variant->getCurrency(),
                    ],
                    'attributes' => $variant->getAttributes(),
                ];

                try {
                    $stock = $variant->getStockItem();
                    if ($stock) {
                        $variantData['stock'] = [
                            'onHand' => $stock->getOnHand(),
                            'reserved' => $stock->getReserved(),
                            'available' => $stock->getAvailable(),
                        ];
                    }
                } catch (\Exception) {
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
            $variants = $product->getVariants();
            if ($variants->count() > 0) {
                $prices = $variants->map(fn ($v) => $v->getPriceAmount())->toArray();
                $minPrice = min($prices);
                $maxPrice = max($prices);
                $currency = $variants->first()->getCurrency();

                $data['price'] = [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                    'currency' => $currency,
                    'formatted' => $minPrice === $maxPrice
                        ? number_format($minPrice / 100, 2, '.', ',').' '.$currency
                        : number_format($minPrice / 100, 2, '.', ',').' - '.number_format($maxPrice / 100, 2, '.', ',').' '.$currency,
                ];
            }
        }

        return $data;
    }
}
