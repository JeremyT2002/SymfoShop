<?php

namespace App\Service\Catalog;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class RecentlyViewedService
{
    private const SESSION_KEY = 'recently_viewed_product_ids';
    private const MAX_ITEMS = 10;
    private const CONSENT_COOKIE = 'symfoshop_cookie_consent';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepository
    ) {
    }

    public function addProduct(Product $product): void
    {
        if (!$this->isFunctionalConsentGranted()) {
            return;
        }

        $productId = $product->getId();
        if ($productId === null) {
            return;
        }

        $ids = $this->getProductIds();
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $productId));
        array_unshift($ids, $productId);
        $ids = array_slice($ids, 0, self::MAX_ITEMS);
        $this->requestStack->getSession()->set(self::SESSION_KEY, $ids);
    }

    /**
     * @return list<Product>
     */
    public function getRecentlyViewedProducts(?int $excludeProductId = null, int $limit = self::MAX_ITEMS): array
    {
        if (!$this->isFunctionalConsentGranted()) {
            return [];
        }

        $ids = $this->getProductIds();
        if ($excludeProductId !== null) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludeProductId));
        }
        $ids = array_slice($ids, 0, max(1, $limit));
        if ($ids === []) {
            return [];
        }

        $products = $this->productRepository->findBy(['id' => $ids]);
        $byId = [];
        foreach ($products as $product) {
            $id = $product->getId();
            if ($id !== null) {
                $byId[$id] = $product;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return list<int>
     */
    public function getProductIds(): array
    {
        $raw = $this->requestStack->getSession()->get(self::SESSION_KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($raw, static fn ($id): bool => is_numeric($id))));
    }

    private function isFunctionalConsentGranted(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return false;
        }

        return $request->cookies->get(self::CONSENT_COOKIE) === 'all';
    }
}

