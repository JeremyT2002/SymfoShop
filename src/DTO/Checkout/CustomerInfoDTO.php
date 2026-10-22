<?php

namespace App\DTO\Checkout;

/**
 * Checkout customer data. Mutable on purpose: the checkout form writes the submitted values into it.
 */
class CustomerInfoDTO
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phone = null
    ) {
    }
}

