<?php

namespace App\Theme;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the active shop for the current request.
 * Single-shop MVP: returns default shop. Multi-tenant: extend to use subdomain/domain/header.
 */
class ShopContextResolver
{
    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function resolve(): ?Shop
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->shopRepository->findDefault();
        }

        // Multi-tenant: resolve from subdomain, domain, or X-Shop header
        $shopSlug = $request->headers->get('X-Shop-Slug')
            ?? $request->query->get('_shop');
        if ($shopSlug !== null && $shopSlug !== '') {
            $shop = $this->shopRepository->findBySlug((string) $shopSlug);
            if ($shop !== null) {
                return $shop;
            }
        }

        return $this->shopRepository->findDefault();
    }
}
