<?php

declare(strict_types=1);

namespace App\Tests\Webhook;

/**
 * Builds a Stripe-Signature header compatible with \Stripe\Webhook::constructEvent().
 */
final class StripeWebhookSignatureHelper
{
    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't=' . $timestamp . ',v1=' . $signature;
    }
}
