<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductStatus;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Product\ImageUploadService;
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
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ImageUploadService $imageUploadService
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $status = (string) $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $statusEnum = $status !== '' ? ProductStatus::tryFrom($status) : null;

        $products = $this->productRepository->findForAdminList(
            $statusEnum,
            $search !== '' ? $search : null,
            $limit,
            $offset
        );
        $total = $this->productRepository->countForAdminList(
            $statusEnum,
            $search !== '' ? $search : null
        );
        
        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
            'status' => $statusEnum?->value ?? '',
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
            $product->setTaxClass($request->request->get('tax_class', 'standard'));
            
            // Set category if provided
            $categoryId = $request->request->get('category_id');
            if ($categoryId) {
                $category = $this->categoryRepository->find($categoryId);
                if ($category) {
                    $product->setCategory($category);
                }
            }
            
            $this->entityManager->persist($product);
            $this->entityManager->flush();
            
            // Handle image uploads
            $uploadedFiles = $request->files->get('images', []);
            if (!empty($uploadedFiles)) {
                try {
                    $uploadedMedia = $this->imageUploadService->uploadImages($product, $uploadedFiles);
                    foreach ($uploadedMedia as $media) {
                        $this->entityManager->persist($media);
                    }
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Product created with ' . count($uploadedMedia) . ' image(s).');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Product created but image upload failed: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'Product created successfully.');
            }
            
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        
        return $this->render('admin/product/new.html.twig', [
            'product' => $product,
            'statuses' => ProductStatus::cases(),
            'categories' => $this->categoryRepository->findRootCategories(),
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
            $product->setTaxClass($request->request->get('tax_class', $product->getTaxClass()));
            $product->setUpdatedAt(new \DateTimeImmutable());
            
            // Set category if provided
            $categoryId = $request->request->get('category_id');
            if ($categoryId) {
                $category = $this->categoryRepository->find($categoryId);
                $product->setCategory($category);
            } else {
                $product->setCategory(null);
            }
            
            // Handle image uploads
            $uploadedFiles = $request->files->get('images', []);
            if (!empty($uploadedFiles)) {
                try {
                    $uploadedMedia = $this->imageUploadService->uploadImages($product, $uploadedFiles);
                    foreach ($uploadedMedia as $media) {
                        $this->entityManager->persist($media);
                    }
                    $this->addFlash('success', 'Product updated with ' . count($uploadedMedia) . ' new image(s).');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Product updated but image upload failed: ' . $e->getMessage());
                }
            }
            
            $this->entityManager->flush();
            
            if (empty($uploadedFiles)) {
                $this->addFlash('success', 'Product updated successfully.');
            }
            
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        
        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'statuses' => ProductStatus::cases(),
            'categories' => $this->categoryRepository->findRootCategories(),
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
            // Delete associated media files
            foreach ($product->getMedia() as $media) {
                $this->imageUploadService->deleteMediaFile($media);
            }
            
            $this->entityManager->remove($product);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Product deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_products_index');
    }

    #[Route('/{id}/media/{mediaId}/delete', name: 'media_delete', methods: ['POST'], requirements: ['id' => '\d+', 'mediaId' => '\d+'])]
    public function deleteMedia(int $id, int $mediaId, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        
        $media = null;
        foreach ($product->getMedia() as $m) {
            if ($m->getId() === $mediaId) {
                $media = $m;
                break;
            }
        }
        
        if (!$media) {
            throw $this->createNotFoundException('Media not found');
        }
        
        if ($this->isCsrfTokenValid('delete_media_' . $mediaId, $request->request->get('_token'))) {
            $this->imageUploadService->deleteMediaFile($media);
            $this->entityManager->remove($media);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Image deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }
        
        return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
    }
}

