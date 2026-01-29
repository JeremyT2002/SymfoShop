<?php

namespace App\DTO\Checkout;

readonly class AddressDTO
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

