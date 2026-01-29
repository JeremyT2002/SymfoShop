<?php

namespace App\Service\Cart;

readonly class CartItem
{
    public function __construct(
        public int $variantId,
        public int $quantity
    ) {
        if ($variantId <= 0) {
            throw new \InvalidArgumentException('Variant ID must be positive');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
    }

    public function withQuantity(int $quantity): self
    {
        return new self($this->variantId, $quantity);
    }
}

