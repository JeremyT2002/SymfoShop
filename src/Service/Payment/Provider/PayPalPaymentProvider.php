<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub PayPal payment provider. Architecture placeholder for future API integration.
 */
final class PayPalPaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'paypal';

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        throw new \RuntimeException('PayPal provider is not implemented. Set PAYMENT_PROVIDER=stripe or dev.');
    }

    public function handleReturn(Request $request): ?PaymentResolution
    {
        return null;
    }

    public function handleWebhook(Request $request): ?PaymentResolution
    {
        return null;
    }

    public function getClientSecretForReference(string $referenceId): ?string
    {
        return null;
    }
}
