<?php

namespace App\Controller\Catalog;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    #[Route('/product/{slug}', name: 'catalog_product', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productRepository->findOneBySlug($slug);

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        $variants = $product->getVariants()->toArray();
        $defaultVariant = !empty($variants) ? $variants[0] : null;

        return $this->render('catalog/product/show.html.twig', [
            'product' => $product,
            'variants' => $variants,
            'defaultVariant' => $defaultVariant,
        ]);
    }
}

