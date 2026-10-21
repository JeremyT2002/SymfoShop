<?php

declare(strict_types=1);

namespace App\Service\Payment\Provider;

use App\Entity\Payment;

/**
 * Payment providers that can pay money back through their API.
 * Providers without it are refunded manually (bank transfer) and only get a credit note.
 */
interface RefundingProviderInterface
{
    /**
     * Refund $amount (minor units) of the captured payment.
     *
     * @throws \RuntimeException when the provider rejects the refund
     */
    public function refund(Payment $payment, int $amount): RefundOutcome;
}
