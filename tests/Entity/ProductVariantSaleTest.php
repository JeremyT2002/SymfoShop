<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ProductVariant;
use PHPUnit\Framework\TestCase;

final class ProductVariantSaleTest extends TestCase
{
    public function testVariantIsOnSaleOnlyWhenCompareAtIsHigher(): void
    {
        $variant = (new ProductVariant())->setPriceAmount(1999);

        self::assertFalse($variant->isOnSale());

        $variant->setCompareAtPriceAmount(2699);
        self::assertTrue($variant->isOnSale());
        self::assertSame(26, $variant->getDiscountPercent());

        $variant->setCompareAtPriceAmount(1999);
        self::assertFalse($variant->isOnSale());
        self::assertSame(0, $variant->getDiscountPercent());

        $variant->setCompareAtPriceAmount(0);
        self::assertNull($variant->getCompareAtPriceAmount());
    }
}
