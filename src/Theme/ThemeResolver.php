<?php

namespace App\Theme;

use App\Entity\Shop;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Resolves published theme config for a shop. Cached; bust on publish.
 */
class ThemeResolver
{
    private const CACHE_KEY_PREFIX = 'theme_published_';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly ThemeRepository $themeRepository,
        private readonly ThemeTokensService $themeTokens,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Get resolved theme config for storefront (published theme or defaults).
     * @return array<string, mixed>
     */
    public function resolveConfig(?Shop $shop = null): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . ($shop?->getId() ?? 'global');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($shop) {
            $item->expiresAfter(self::CACHE_TTL);
            $theme = $this->themeRepository->findPublishedByShop($shop);
            if ($theme !== null) {
                return $this->themeTokens->mergeTokens($theme->getConfig());
            }
            return $this->themeTokens->getTokens();
        });
    }

    /**
     * Get the published Theme entity for a shop, or null.
     */
    public function resolveTheme(?Shop $shop = null): ?Theme
    {
        return $this->themeRepository->findPublishedByShop($shop);
    }

    /**
     * Bust theme cache (call after publish).
     */
    public function bustCache(?Shop $shop = null): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX . ($shop?->getId() ?? 'global');
        $this->cache->delete($cacheKey);
    }
}
