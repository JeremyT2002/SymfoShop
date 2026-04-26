<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use App\Entity\StockItem;
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
        $limit = in_array((int) $request->query->get('perPage', 25), [25, 50, 100], true)
            ? (int) $request->query->get('perPage', 25)
            : 25;
        $offset = ($page - 1) * $limit;
        $sortBy = (string) $request->query->get('sortBy', 'createdAt');
        $sortDir = strtoupper((string) $request->query->get('sortDir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $status = (string) $request->query->get('status', '');
        $search = trim((string) $request->query->get('search', ''));

        $statusEnum = $status !== '' ? ProductStatus::tryFrom($status) : null;

        $products = $this->productRepository->findForAdminList(
            $statusEnum,
            $search !== '' ? $search : null,
            $limit,
            $offset,
            $sortBy,
            $sortDir
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
            'perPage' => $limit,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
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

            $seoTitle = trim((string) $request->request->get('seo_title', ''));
            $product->setSeoTitle($seoTitle !== '' ? $seoTitle : null);

            $seoDescription = trim((string) $request->request->get('seo_description', ''));
            $product->setSeoDescription($seoDescription !== '' ? $seoDescription : null);

            $product->setSeoNoIndex($request->request->getBoolean('seo_noindex'));
            
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
        $product = $this->findProductOr404($id);
        
        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/variants', name: 'variants', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function variants(int $id, Request $request): Response
    {
        $product = $this->findProductOr404($id);

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        if (!$this->isCsrfTokenValid('create_variant_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

            $sku = trim((string) $request->request->get('sku', ''));
            $priceAmount = (int) $request->request->get('price_amount', 0);
            $currency = strtoupper(trim((string) $request->request->get('currency', 'EUR')));
            $onHand = max(0, (int) $request->request->get('on_hand', 0));
            $reserved = max(0, (int) $request->request->get('reserved', 0));
            $attributesRaw = (string) $request->request->get('attributes', '');

        if ($sku === '') {
            $this->addFlash('error', 'SKU is required.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if ($priceAmount < 0) {
            $this->addFlash('error', 'Price must be 0 or higher.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->addFlash('error', 'Currency must be a 3-letter code (e.g. EUR).');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if ($reserved > $onHand) {
            $this->addFlash('error', 'Reserved stock cannot be higher than on hand.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if ($this->entityManager->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]) !== null) {
            $this->addFlash('error', 'SKU already exists.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setSku($sku);
            $variant->setPriceAmount($priceAmount);
            $variant->setCurrency($currency);
            $variant->setAttributes($this->parseAttributesInput($attributesRaw));
            $variant->setUpdatedAt(new \DateTimeImmutable());

            $stockItem = new StockItem();
            $stockItem->setVariant($variant);
            $stockItem->setOnHand($onHand);
            $stockItem->setReserved($reserved);
            $variant->setStockItem($stockItem);

            $this->entityManager->persist($variant);
            $this->entityManager->persist($stockItem);
            $this->entityManager->flush();

        $this->addFlash('success', 'Variant created.');
        return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
    }

    #[Route('/{id}/variants/{variantId}/edit', name: 'variant_edit', methods: ['POST'], requirements: ['id' => '\d+', 'variantId' => '\d+'])]
    public function editVariant(int $id, int $variantId, Request $request): Response
    {
        $product = $this->findProductOr404($id);
        $variant = $this->findVariantForProductOr404($product, $variantId);

        if (!$this->isCsrfTokenValid('edit_variant_' . $variant->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $sku = trim((string) $request->request->get('sku', ''));
        $priceAmount = (int) $request->request->get('price_amount', 0);
        $currency = strtoupper(trim((string) $request->request->get('currency', 'EUR')));
        $onHand = max(0, (int) $request->request->get('on_hand', 0));
        $reserved = max(0, (int) $request->request->get('reserved', 0));
        $attributesRaw = (string) $request->request->get('attributes', '');

        if ($sku === '') {
            $this->addFlash('error', 'SKU is required.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if ($priceAmount < 0) {
            $this->addFlash('error', 'Price must be 0 or higher.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->addFlash('error', 'Currency must be a 3-letter code (e.g. EUR).');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }
        if ($reserved > $onHand) {
            $this->addFlash('error', 'Reserved stock cannot be higher than on hand.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $existing = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['sku' => $sku]);
        if ($existing !== null && $existing->getId() !== $variant->getId()) {
            $this->addFlash('error', 'SKU already exists.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $variant->setSku($sku);
        $variant->setPriceAmount($priceAmount);
        $variant->setCurrency($currency);
        $variant->setAttributes($this->parseAttributesInput($attributesRaw));
        $variant->setUpdatedAt(new \DateTimeImmutable());

        $stockItem = $variant->getStockItem() ?? (new StockItem())->setVariant($variant);
        $stockItem->setOnHand($onHand);
        $stockItem->setReserved($reserved);
        $variant->setStockItem($stockItem);

        $this->entityManager->persist($variant);
        $this->entityManager->persist($stockItem);
        $this->entityManager->flush();

        $this->addFlash('success', 'Variant updated.');
        return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
    }

    #[Route('/{id}/variants/{variantId}/delete', name: 'variant_delete', methods: ['POST'], requirements: ['id' => '\d+', 'variantId' => '\d+'])]
    public function deleteVariant(int $id, int $variantId, Request $request): Response
    {
        $product = $this->findProductOr404($id);
        $variant = $this->findVariantForProductOr404($product, $variantId);

        if (!$this->isCsrfTokenValid('delete_variant_' . $variant->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $this->entityManager->remove($variant);
        $this->entityManager->flush();

        $this->addFlash('success', 'Variant deleted.');
        return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
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

            $seoTitle = trim((string) $request->request->get('seo_title', ''));
            $product->setSeoTitle($seoTitle !== '' ? $seoTitle : null);

            $seoDescription = trim((string) $request->request->get('seo_description', ''));
            $product->setSeoDescription($seoDescription !== '' ? $seoDescription : null);

            $product->setSeoNoIndex($request->request->getBoolean('seo_noindex'));

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

    #[Route('/{id}/status', name: 'update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(int $id, Request $request): Response
    {
        $product = $this->findProductOr404($id);

        if (!$this->isCsrfTokenValid('update_product_status_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $status = ProductStatus::tryFrom((string) $request->request->get('status', ''));
        if ($status === null) {
            $this->addFlash('error', 'Invalid status value.');

            return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
        }

        $product->setStatus($status);
        $product->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', 'Product status updated.');

        return $this->redirectToRoute('admin_products_show', ['id' => $product->getId()]);
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

    #[Route('/bulk/status', name: 'bulk_status', methods: ['POST'])]
    public function bulkStatus(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_products_status', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_products_index');
        }

        $ids = $this->parseBulkIds((string) $request->request->get('ids', ''));
        $status = ProductStatus::tryFrom((string) $request->request->get('status', ''));
        if ($ids === [] || $status === null) {
            $this->addFlash('error', 'Invalid bulk action payload.');

            return $this->redirectToRoute('admin_products_index');
        }

        $products = $this->productRepository->findBy(['id' => $ids]);
        foreach ($products as $product) {
            $product->setStatus($status);
            $product->setUpdatedAt(new \DateTimeImmutable());
        }
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Updated %d product(s).', count($products)));

        return $this->redirectToRoute('admin_products_index');
    }

    private function findProductOr404(int $id): Product
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        return $product;
    }

    private function findVariantForProductOr404(Product $product, int $variantId): ProductVariant
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant->getId() === $variantId) {
                return $variant;
            }
        }
        throw $this->createNotFoundException('Variant not found');
    }

    /** @return array<string, string> */
    private function parseAttributesInput(string $input): array
    {
        $attributes = [];
        $trimmed = trim($input);
        if ($trimmed === '') {
            return $attributes;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                $k = trim((string) $key);
                $v = trim((string) $value);
                if ($k !== '' && $v !== '') {
                    $attributes[$k] = $v;
                }
            }
            return $attributes;
        }

        foreach (preg_split('/\R/', $trimmed) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            if ($k !== '' && $v !== '') {
                $attributes[$k] = $v;
            }
        }

        return $attributes;
    }

    /**
     * @return list<int>
     */
    private function parseBulkIds(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== '');

        return array_values(array_map('intval', $parts));
    }
}

