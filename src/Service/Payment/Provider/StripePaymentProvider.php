<?php

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;

final class StripePaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'stripe';

    public function __construct(
        private readonly StripeClient $stripeClient,
        private readonly ?string $webhookSecret
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        try {
            $paymentIntent = $this->stripeClient->paymentIntents->create([
                'amount' => $order->getGrandTotal(),
                'currency' => strtolower($order->getCurrency()),
                'metadata' => [
                    'order_number' => $order->getOrderNumber(),
                    'order_id' => (string) $order->getId(),
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            return new PaymentResult(
                provider: self::NAME,
                referenceId: $paymentIntent->id,
                clientSecret: $paymentIntent->client_secret,
            );
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to create Stripe payment intent: ' . $e->getMessage(), 0, $e);
        }
    }

    public function handleReturn(Request $request): ?PaymentResolution
    {
        $paymentIntentId = $request->query->get('payment_intent');
        if (!$paymentIntentId) {
            return null;
        }
        try {
            $intent = $this->stripeClient->paymentIntents->retrieve($paymentIntentId);
            $status = match ($intent->status) {
                'succeeded' => PaymentResolution::STATUS_SUCCEEDED,
                'requires_payment_method', 'requires_confirmation', 'requires_action' => PaymentResolution::STATUS_PENDING,
                default => PaymentResolution::STATUS_FAILED,
            };
            $orderId = isset($intent->metadata['order_id']) ? (int) $intent->metadata['order_id'] : null;
            return new PaymentResolution($paymentIntentId, $status, $orderId);
        } catch (\Throwable) {
            return null;
        }
    }

    public function handleWebhook(Request $request): ?PaymentResolution
    {
        if ($this->webhookSecret === null || $this->webhookSecret === '') {
            return null;
        }
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature) {
            return null;
        }
        $payload = $request->getContent();
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (\Throwable) {
            return null;
        }
        $obj = $event->data->object;
        if (!$obj instanceof \Stripe\PaymentIntent) {
            return null;
        }
        $paymentIntentId = $obj->id;
        $status = match ($event->type) {
            'payment_intent.succeeded' => PaymentResolution::STATUS_SUCCEEDED,
            'payment_intent.payment_failed', 'payment_intent.canceled' => PaymentResolution::STATUS_FAILED,
            default => PaymentResolution::STATUS_PENDING,
        };
        $orderId = isset($obj->metadata['order_id']) ? (int) $obj->metadata['order_id'] : null;
        return new PaymentResolution($paymentIntentId, $status, $orderId);
    }

    public function getClientSecretForReference(string $referenceId): ?string
    {
        try {
            $intent = $this->stripeClient->paymentIntents->retrieve($referenceId);
            return $intent->client_secret ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
