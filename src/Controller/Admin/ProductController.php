<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/products', name: 'admin_products_')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        
        $criteria = [];
        if ($status) {
            $criteria['status'] = ProductStatus::from($status);
        }
        
        $products = $this->productRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
        
        // Apply search filter if provided
        if ($search) {
            $products = array_filter($products, function(Product $product) use ($search) {
                return stripos($product->getName(), $search) !== false 
                    || stripos($product->getSlug(), $search) !== false;
            });
        }
        
        $total = $this->productRepository->count($criteria);
        
        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        
        if ($request->isMethod('POST')) {
            $product->setName($request->request->get('name'));
            $product->setSlug($this->slugger->slug($product->getName())->lower());
            $product->setDescription($request->request->get('description', ''));
            $product->setStatus(ProductStatus::from($request->request->get('status', 'draft')));
            
            $this->entityManager->persist($product);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Product created successfully.');
            
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        
        return $this->render('admin/product/new.html.twig', [
            'product' => $product,
            'statuses' => ProductStatus::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        if ($request->isMethod('POST')) {
            $product->setName($request->request->get('name'));
            $product->setSlug($this->slugger->slug($product->getName())->lower());
            $product->setDescription($request->request->get('description', ''));
            $product->setStatus(ProductStatus::from($request->request->get('status')));
            $product->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Product updated successfully.');
            
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        
        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'statuses' => ProductStatus::cases(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        if ($this->isCsrfTokenValid('delete_product_' . $product->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($product);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Product deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_products_index');
    }
}

