<?php

namespace App\Controller\Catalog;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Catalog\RecentlyViewedService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly RecentlyViewedService $recentlyViewedService
    ) {
    }

    #[Route('/product/{slug}', name: 'catalog_product', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productRepository->findOneBySlug($slug);

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        $this->recentlyViewedService->addProduct($product);
        $variants = $product->getVariants()->toArray();
        $defaultVariant = !empty($variants) ? $variants[0] : null;

        return $this->render('catalog/product/show.html.twig', [
            'product' => $product,
            'variants' => $variants,
            'defaultVariant' => $defaultVariant,
            'recentlyViewedProducts' => $this->recentlyViewedService->getRecentlyViewedProducts($product->getId(), 8),
        ]);
    }
}

