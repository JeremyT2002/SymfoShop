<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

final class TestBankPaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'testbank';

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        $referenceId = 'testbank_' . bin2hex(random_bytes(12));

        return new PaymentResult(
            provider: self::NAME,
            referenceId: $referenceId,
            clientSecret: null,
        );
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

