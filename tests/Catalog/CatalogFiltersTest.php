<?php

namespace App\Tests\Catalog;

use App\Catalog\CatalogFilters;
use PHPUnit\Framework\TestCase;

final class CatalogFiltersTest extends TestCase
{
    public function testFromRequestParsesPricesAttributesSortAndInStock(): void
    {
        $f = CatalogFilters::fromRequest([
            'min_price' => '100',
            'max_price' => '500',
            'in_stock' => '1',
            'sort' => 'price-asc',
            'attr_color' => ['Red', 'Blue'],
            'attr_size' => 'M',
        ]);

        $this->assertSame(100, $f->minPrice);
        $this->assertSame(500, $f->maxPrice);
        $this->assertTrue($f->inStockOnly);
        $this->assertSame('price-asc', $f->sort);
        $this->assertSame(['Red', 'Blue'], $f->attributeFilters['color']);
        $this->assertSame(['M'], $f->attributeFilters['size']);
    }

    public function testFromRequestDropsNegativePrices(): void
    {
        $f = CatalogFilters::fromRequest(['min_price' => '-1', 'max_price' => '-5']);
        $this->assertNull($f->minPrice);
        $this->assertNull($f->maxPrice);
    }

    public function testToQueryParamsRoundTrip(): void
    {
        $f = new CatalogFilters(10, 99, ['c' => ['x']], true, 'name-desc');
        $q = $f->toQueryParams();

        $this->assertSame(10, $q['min_price']);
        $this->assertSame(99, $q['max_price']);
        $this->assertSame('1', $q['in_stock']);
        $this->assertSame('name-desc', $q['sort']);
        $this->assertSame(['x'], $q['attr_c']);
    }

    public function testHasAnyFilter(): void
    {
        $this->assertFalse((new CatalogFilters())->hasAnyFilter());
        $this->assertTrue((new CatalogFilters(minPrice: 1))->hasAnyFilter());
        $this->assertTrue((new CatalogFilters(maxPrice: 1))->hasAnyFilter());
        $this->assertTrue((new CatalogFilters(inStockOnly: true))->hasAnyFilter());
        $this->assertTrue((new CatalogFilters(attributeFilters: ['a' => ['b']]))->hasAnyFilter());
    }
}
