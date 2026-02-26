<?php

namespace App\Controller\Catalog;

use App\Catalog\CatalogFilters;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository
    ) {
    }

    #[Route('/', name: 'catalog_home', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findRootCategories();
        $featuredProducts = $this->productRepository->findActiveProducts(0, 8);

        return $this->render('catalog/category/index.html.twig', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    #[Route('/category/{slug}', name: 'catalog_category', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBySlug($slug);

        if (!$category) {
            throw new NotFoundHttpException('Category not found');
        }

        $queryParams = $request->query->all();
        $filters = CatalogFilters::fromRequest($queryParams);

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $products = $this->productRepository->findFilteredByCategory($category, $filters, $offset, $perPage);
        $totalProducts = $this->productRepository->countFilteredByCategory($category, $filters);
        $totalPages = (int) ceil($totalProducts / $perPage) ?: 1;

        $filterOptions = $this->productRepository->getFilterOptionsForCategory($category);

        $routeParams = array_merge(['slug' => $category->getSlug()], $filters->toQueryParams());
        $attributeBadges = $this->buildAttributeBadges($category->getSlug(), $filters->attributeFilters, $routeParams);

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'routeParams' => $routeParams,
            'attributeBadges' => $attributeBadges,
        ]);
    }

    /**
     * @param array<string, list<string>> $attributeFilters
     * @return list<array{label: string, removeUrl: string}>
     */
    private function buildAttributeBadges(string $slug, array $attributeFilters, array $routeParams): array
    {
        $badges = [];
        foreach ($attributeFilters as $attrKey => $values) {
            foreach ($values as $val) {
                $rest = array_values(array_filter($values, fn (string $v) => $v !== $val));
                $params = $routeParams;
                unset($params['page']);
                $params['attr_' . $attrKey] = $rest;
                if ($params['attr_' . $attrKey] === []) {
                    unset($params['attr_' . $attrKey]);
                }
                $badges[] = [
                    'label' => ucfirst($attrKey) . ': ' . $val,
                    'removeUrl' => $this->generateUrl('catalog_category', $params),
                ];
            }
        }
        return $badges;
    }
}
