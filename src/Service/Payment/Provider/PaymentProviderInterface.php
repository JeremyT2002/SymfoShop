<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

interface PaymentProviderInterface
{
    public function getName(): string;

    /**
     * Start payment for the order. Returns redirect URL and/or client secret + reference ID.
     */
    public function startPayment(Order $order): PaymentResult;

    /**
     * Handle user return from provider (success/cancel URL). Returns resolution or null if not applicable.
     */
    public function handleReturn(Request $request): ?PaymentResolution;

    /**
     * Handle provider webhook. Verify signature, parse event, return resolution or null.
     */
    public function handleWebhook(Request $request): ?PaymentResolution;

    /**
     * Return client secret for an existing reference (e.g. Stripe), or null for redirect-only / dev providers.
     */
    public function getClientSecretForReference(string $referenceId): ?string;
}
