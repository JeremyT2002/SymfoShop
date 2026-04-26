<?php

namespace App\Message;

class SendAbandonedCartReminderMessage
{
    public function __construct(
        private readonly int $cartId
    ) {
    }

    public function getCartId(): int
    {
        return $this->cartId;
    }
}

