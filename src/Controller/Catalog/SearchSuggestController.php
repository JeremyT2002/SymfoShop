<?php

declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Media\ResponsiveImages;
use App\Twig\StorefrontExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Search-as-you-type suggestions for the header search.
 */
final class SearchSuggestController extends AbstractController
{
    #[Route('/search/suggest', name: 'search_suggest', methods: ['GET'])]
    public function suggest(
        Request $request,
        ProductRepository $products,
        CategoryRepository $categories,
        ResponsiveImages $images,
        StorefrontExtension $format,
    ): JsonResponse {
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 100);
        if (mb_strlen($query) < 2) {
            return $this->json(['products' => [], 'categories' => []]);
        }

        $productData = array_map(function (Product $product) use ($images, $format): array {
            $prices = $product->getVariants()->map(static fn ($v) => $v->getPriceAmount())->toArray();
            $variant = $product->getVariants()->first() ?: null;
            $image = $product->getMedia()->first() ?: null;

            return [
                'name' => $product->getName(),
                'url' => $this->generateUrl('catalog_product', ['slug' => $product->getSlug()]),
                'price' => $prices !== [] ? $format->formatMoney(min($prices), $variant?->getCurrency()) : null,
                'image' => $image !== null ? $images->url($image->getPath(), 160) : null,
            ];
        }, $products->findActiveProductsForListing(0, 6, $query));

        $needle = mb_strtolower($query);
        $categoryData = array_values(array_map(
            fn (Category $category): array => [
                'name' => $category->getName(),
                'url' => $this->generateUrl('catalog_category', ['slug' => $category->getSlug()]),
            ],
            array_slice(array_filter($categories->findAll(), static fn (Category $c): bool => str_contains(mb_strtolower($c->getName()), $needle)), 0, 3)
        ));

        $response = $this->json(['products' => $productData, 'categories' => $categoryData]);
        // Private: prices are formatted for the visitor's locale
        $response->setPrivate();
        $response->setMaxAge(60);

        return $response;
    }
}
