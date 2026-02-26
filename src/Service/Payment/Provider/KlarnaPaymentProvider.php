<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub Klarna payment provider. Architecture placeholder for future API integration.
 */
final class KlarnaPaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'klarna';

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        throw new \RuntimeException('Klarna provider is not implemented. Set PAYMENT_PROVIDER=stripe or dev.');
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
