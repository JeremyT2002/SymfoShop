<?php

namespace App\Service\Payment\Provider;

/**
 * Result of handling a payment return or webhook.
 */
final readonly class PaymentResolution
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';

    public function __construct(
        public string $referenceId,
        public string $status,
        public ?int $orderId = null,
    ) {
    }
}
