<?php

namespace App\DTO\Checkout;

/**
 * Shipping address in the checkout. Mutable on purpose: the checkout form writes the submitted values into it.
 */
class AddressDTO
{
    public function __construct(
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public ?string $state = null
    ) {
    }
}

