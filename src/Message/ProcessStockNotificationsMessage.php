<?php

namespace App\Message;

class ProcessStockNotificationsMessage
{
    public function __construct(
        private readonly int $variantId
    ) {
    }

    public function getVariantId(): int
    {
        return $this->variantId;
    }
}

