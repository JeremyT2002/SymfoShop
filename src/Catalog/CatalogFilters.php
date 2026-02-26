<?php

namespace App\Catalog;

/**
 * Value object for category product filters (price range, attributes, in-stock).
 * Immutable; build from request query parameters.
 */
final readonly class CatalogFilters
{
    /** @param array<string, list<string>> $attributeFilters e.g. ['size' => ['M', 'L'], 'color' => ['Red']] */
    public function __construct(
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public array $attributeFilters = [],
        public bool $inStockOnly = false,
        public string $sort = 'default',
    ) {
    }

    public static function fromRequest(array $queryParams): self
    {
        $minPrice = isset($queryParams['min_price']) ? (int) $queryParams['min_price'] : null;
        $maxPrice = isset($queryParams['max_price']) ? (int) $queryParams['max_price'] : null;
        if ($minPrice !== null && $minPrice < 0) {
            $minPrice = null;
        }
        if ($maxPrice !== null && $maxPrice < 0) {
            $maxPrice = null;
        }

        $attributeFilters = [];
        foreach ($queryParams as $key => $value) {
            if (str_starts_with($key, 'attr_') && is_array($value)) {
                $attr = substr($key, 5);
                $attributeFilters[$attr] = array_map('strval', array_filter($value));
            } elseif (str_starts_with($key, 'attr_') && is_string($value) && $value !== '') {
                $attr = substr($key, 5);
                $attributeFilters[$attr] = [$value];
            }
        }

        $inStockOnly = isset($queryParams['in_stock']) && (bool) $queryParams['in_stock'];
        $sort = isset($queryParams['sort']) && is_string($queryParams['sort'])
            ? $queryParams['sort']
            : 'default';

        return new self($minPrice, $maxPrice, $attributeFilters, $inStockOnly, $sort);
    }

    /** @return array<string, mixed> For building URL query (e.g. pagination, form default) */
    public function toQueryParams(): array
    {
        $params = [];
        if ($this->minPrice !== null) {
            $params['min_price'] = $this->minPrice;
        }
        if ($this->maxPrice !== null) {
            $params['max_price'] = $this->maxPrice;
        }
        if ($this->inStockOnly) {
            $params['in_stock'] = '1';
        }
        if ($this->sort !== 'default') {
            $params['sort'] = $this->sort;
        }
        foreach ($this->attributeFilters as $key => $values) {
            $params['attr_' . $key] = $values;
        }
        return $params;
    }

    public function hasAnyFilter(): bool
    {
        return $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->inStockOnly
            || $this->attributeFilters !== [];
    }
}
