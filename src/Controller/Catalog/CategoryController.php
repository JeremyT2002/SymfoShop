<?php

namespace App\Controller\Catalog;

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

        return $this->render('catalog/category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/category/{slug}', name: 'catalog_category', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBySlug($slug);

        if (!$category) {
            throw new NotFoundHttpException('Category not found');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        // TODO: Filter products by category when Product-Category relationship is added
        $products = $this->productRepository->findActiveProducts($offset, $perPage);
        $totalProducts = $this->productRepository->countActiveProducts();
        $totalPages = (int) ceil($totalProducts / $perPage);

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
        ]);
    }
}

