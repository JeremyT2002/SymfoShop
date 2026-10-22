<?php

declare(strict_types=1);

namespace App\Service\Payment\Provider;

use App\Entity\Refund;

final readonly class RefundOutcome
{
    /**
     * @param Refund::STATUS_* $status
     */
    public function __construct(
        public string $reference,
        public string $status = Refund::STATUS_SUCCEEDED,
    ) {
    }
}
