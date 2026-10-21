<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\WishlistRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Presentation helpers shared by storefront and admin templates.
 */
final class StorefrontExtension extends AbstractExtension
{
    /** @var list<Category>|null */
    private ?array $navCategories = null;

    /** @var array<int, true>|null */
    private ?array $wishlistProductIds = null;

    /** @var array<string, \NumberFormatter> */
    private array $formatters = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly WishlistRepository $wishlistRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->formatMoney(...)),
            new TwigFilter('public_name', $this->publicName(...)),
            new TwigFilter('country_name', $this->countryName(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('in_wishlist', $this->isInWishlist(...)),
            new TwigFunction('nav_categories', $this->navCategories(...)),
        ];
    }

    /**
     * Formats an amount in minor units (cents) for the current locale, e.g. 1299 → "12,99 €" (de) / "€12.99" (en).
     */
    public function formatMoney(int|float|null $minorUnits, ?string $currency = 'EUR', ?string $locale = null): string
    {
        $amount = ((float) ($minorUnits ?? 0)) / 100;
        $currency = strtoupper($currency ?: 'EUR');
        $locale ??= $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';

        if (class_exists(\NumberFormatter::class)) {
            $formatter = $this->formatters[$locale] ??= new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }

    /**
     * "DE" → "Deutschland" in the current locale (falls back to the code).
     */
    public function countryName(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';

        return Countries::exists($code) ? Countries::getName($code, $locale) : $code;
    }

    /**
     * Name shown next to public content such as reviews: "Jane D." — never the e-mail address.
     */
    public function publicName(?User $user, string $fallback = ''): string
    {
        $first = trim((string) $user?->getFirstName());
        $last = trim((string) $user?->getLastName());

        if ($first !== '') {
            return $last !== '' ? sprintf('%s %s.', $first, mb_substr($last, 0, 1)) : $first;
        }

        return $last !== '' ? $last : $fallback;
    }

    /**
     * Top-level categories for the header navigation (one query per request).
     *
     * @return list<Category>
     */
    public function navCategories(int $limit = 6): array
    {
        $this->navCategories ??= $this->categoryRepository->findRootCategories();

        return \array_slice($this->navCategories, 0, $limit);
    }

    /**
     * Whether the logged-in customer has the product on their wishlist.
     * Loads all ids once per request, so product grids cost a single query.
     */
    public function isInWishlist(?int $productId): bool
    {
        if ($productId === null) {
            return false;
        }

        if ($this->wishlistProductIds === null) {
            $user = $this->security->getUser();
            $this->wishlistProductIds = $user instanceof User
                ? array_fill_keys($this->wishlistRepository->findProductIdsByUser($user), true)
                : [];
        }

        return isset($this->wishlistProductIds[$productId]);
    }
}
