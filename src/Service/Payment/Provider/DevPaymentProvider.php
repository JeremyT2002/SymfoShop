<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Development payment provider. Simulates payment without external API.
 * State is changed via the payment simulator page (success/failure/pending).
 * Payment record is created by PaymentService from the returned result.
 */
final class DevPaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'dev';

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        $referenceId = 'dev_' . bin2hex(random_bytes(12));

        return new PaymentResult(
            provider: self::NAME,
            referenceId: $referenceId,
            clientSecret: null,
        );
    }

    public function handleReturn(Request $request): ?PaymentResolution
    {
        $referenceId = $request->query->get('reference_id');
        if (!$referenceId || !str_starts_with($referenceId, 'dev_')) {
            return null;
        }
        $status = $request->query->get('outcome', 'pending');
        if (!in_array($status, [PaymentResolution::STATUS_SUCCEEDED, PaymentResolution::STATUS_FAILED, PaymentResolution::STATUS_PENDING], true)) {
            $status = PaymentResolution::STATUS_PENDING;
        }
        return new PaymentResolution($referenceId, $status, null);
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
