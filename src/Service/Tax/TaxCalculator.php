<?php

declare(strict_types=1);

namespace App\Service\Tax;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * VAT rules for gross prices (see config/packages/tax.yaml).
 *
 * Tax classes: "standard", "reduced", "zero" and "exempt" (the latter two carry no VAT).
 */
final class TaxCalculator
{
    public const CLASSES = ['standard', 'reduced', 'zero', 'exempt'];

    /**
     * @param array<string, array{0: int|float, 1: int|float}> $euRates
     */
    public function __construct(
        #[Autowire('%app.tax.home_country%')]
        private readonly string $homeCountry,
        #[Autowire('%app.tax.oss%')]
        private readonly bool $oss,
        #[Autowire('%app.tax.eu_rates%')]
        private readonly array $euRates,
    ) {
    }

    public function homeCountry(): string
    {
        return strtoupper($this->homeCountry);
    }

    /**
     * Deliveries outside the EU are exports: no VAT is charged.
     */
    public function isExport(?string $countryCode): bool
    {
        $country = $this->normalize($countryCode);

        return !isset($this->euRates[$country]);
    }

    /**
     * Rate (e.g. 0.19) that applies to a delivery of this tax class to the given country.
     */
    public function rateFor(string $taxClass, ?string $countryCode): float
    {
        if ($this->isExport($countryCode)) {
            return 0.0;
        }

        $country = $this->oss ? $this->normalize($countryCode) : $this->homeCountry();

        return $this->rateIn($country, $taxClass);
    }

    /**
     * Rate included in the entered gross prices (home country).
     */
    public function homeRate(string $taxClass): float
    {
        return $this->rateIn($this->homeCountry(), $taxClass);
    }

    /**
     * Amount the customer pays for a gross price: unchanged inside the EU, home VAT removed for exports.
     */
    public function chargeableAmount(int $grossAmount, string $taxClass, ?string $countryCode): int
    {
        if (!$this->isExport($countryCode)) {
            return $grossAmount;
        }

        return (int) round($grossAmount / (1 + $this->homeRate($taxClass)));
    }

    /**
     * VAT contained in a gross amount.
     */
    public function includedTax(int $grossAmount, float $rate): int
    {
        if ($rate <= 0.0) {
            return 0;
        }

        return (int) round($grossAmount - $grossAmount / (1 + $rate));
    }

    private function rateIn(string $country, string $taxClass): float
    {
        $rates = $this->euRates[$country] ?? $this->euRates[$this->homeCountry()] ?? [0, 0];

        return match ($taxClass) {
            'reduced' => ((float) $rates[1]) / 100,
            'zero', 'exempt' => 0.0,
            default => ((float) $rates[0]) / 100,
        };
    }

    private function normalize(?string $countryCode): string
    {
        $country = strtoupper(trim((string) $countryCode));

        return $country !== '' ? $country : $this->homeCountry();
    }
}
