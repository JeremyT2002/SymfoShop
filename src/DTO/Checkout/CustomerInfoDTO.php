<?php

namespace App\DTO\Checkout;

readonly class CustomerInfoDTO
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phone = null
    ) {
    }
}

