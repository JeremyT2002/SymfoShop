<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ShippingMethod;
use PHPUnit\Framework\TestCase;

final class ShippingMethodTest extends TestCase
{
    public function testFreeAboveThreshold(): void
    {
        $method = (new ShippingMethod())->setAmountCents(490);
        self::assertSame(490, $method->priceFor(100000), 'no threshold: always charged');

        $method->setFreeAboveCents(5000);
        self::assertSame(490, $method->priceFor(4999));
        self::assertSame(0, $method->priceFor(5000));

        $method->setFreeAboveCents(0);
        self::assertNull($method->getFreeAboveCents());
    }
}
