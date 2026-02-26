<?php

namespace App\Service\Payment\Provider;

/**
 * Result of starting a payment: redirect URL and/or client secret + reference for frontend.
 */
final readonly class PaymentResult
{
    public function __construct(
        public string $provider,
        public string $referenceId,
        public ?string $redirectUrl = null,
        public ?string $clientSecret = null,
    ) {
    }
}
