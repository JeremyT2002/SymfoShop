<?php

declare(strict_types=1);

namespace App\Tests\Service\Tax;

use App\Service\Tax\TaxCalculator;
use PHPUnit\Framework\TestCase;

final class TaxCalculatorTest extends TestCase
{
    private const RATES = ['DE' => [19, 7], 'FR' => [20, 5.5], 'AT' => [20, 10]];

    public function testHomeRatesApplyToAllEuCountriesWithoutOss(): void
    {
        $tax = new TaxCalculator('DE', false, self::RATES);

        self::assertSame(0.19, $tax->rateFor('standard', 'DE'));
        self::assertSame(0.19, $tax->rateFor('standard', 'FR'));
        self::assertSame(0.07, $tax->rateFor('reduced', 'AT'));
        self::assertSame(0.19, $tax->rateFor('standard', null));
    }

    public function testDestinationRatesWithOss(): void
    {
        $tax = new TaxCalculator('DE', true, self::RATES);

        self::assertSame(0.20, $tax->rateFor('standard', 'FR'));
        self::assertSame(0.055, $tax->rateFor('reduced', 'fr'));
        self::assertSame(0.19, $tax->rateFor('standard', 'DE'));
    }

    public function testZeroAndExemptClassesCarryNoVat(): void
    {
        $tax = new TaxCalculator('DE', false, self::RATES);

        self::assertSame(0.0, $tax->rateFor('zero', 'DE'));
        self::assertSame(0.0, $tax->rateFor('exempt', 'DE'));
    }

    public function testExportsAreChargedNet(): void
    {
        $tax = new TaxCalculator('DE', false, self::RATES);

        self::assertTrue($tax->isExport('US'));
        self::assertFalse($tax->isExport('DE'));
        self::assertSame(0.0, $tax->rateFor('standard', 'CH'));
        // 11.90 gross incl. 19 % → 10.00 net
        self::assertSame(1000, $tax->chargeableAmount(1190, 'standard', 'US'));
        self::assertSame(1070, $tax->chargeableAmount(1070, 'reduced', 'DE'));
    }

    public function testIncludedTax(): void
    {
        $tax = new TaxCalculator('DE', false, self::RATES);

        self::assertSame(190, $tax->includedTax(1190, 0.19));
        self::assertSame(70, $tax->includedTax(1070, 0.07));
        self::assertSame(0, $tax->includedTax(1000, 0.0));
    }
}
