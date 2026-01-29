<?php

namespace App\Message;

class SendOrderConfirmationEmail
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $invoiceNumber
    ) {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }
}

