<?php

namespace App\Controller\Catalog;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductListController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('/products', name: 'catalog_products', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query->get('q', '')), 0, 100);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $totalProducts = $this->productRepository->countActiveProducts($search);
        $products = $this->productRepository->findActiveProductsForListing($offset, $perPage, $search);
        $totalPages = (int) ceil($totalProducts / $perPage) ?: 1;

        return $this->render('catalog/product/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
        ]);
    }
}
