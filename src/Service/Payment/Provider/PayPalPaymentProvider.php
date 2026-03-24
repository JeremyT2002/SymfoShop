<?php

declare(strict_types=1);

namespace App\Service\Payment\Provider;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * PayPal Checkout (Orders API v2). Without PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET, runs in stub mode
 * (random reference, simulator UI) for local/testing.
 */
final class PayPalPaymentProvider implements PaymentProviderInterface
{
    public const NAME = 'paypal';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $baseUrl = 'https://api-m.sandbox.paypal.com',
        private readonly ?string $webhookId = null,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function startPayment(Order $order): PaymentResult
    {
        if (trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            $referenceId = 'paypal_' . bin2hex(random_bytes(12));

            return new PaymentResult(
                provider: self::NAME,
                referenceId: $referenceId,
                redirectUrl: null,
                clientSecret: null,
            );
        }

        $returnUrl = $this->urlGenerator->generate('payment_paypal_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->urlGenerator->generate('payment_paypal_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $token = $this->fetchAccessToken();
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'o' . $order->getId(),
                'custom_id' => $order->getOrderNumber(),
                'invoice_id' => $order->getOrderNumber(),
                'amount' => [
                    'currency_code' => strtoupper($order->getCurrency()),
                    'value' => $this->formatMoney($order->getGrandTotal()),
                ],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'json' => $body,
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($status >= 400) {
            $this->logger->error('PayPal create order failed', ['status' => $status, 'body' => $data]);
            throw new \RuntimeException('PayPal: could not create order.');
        }

        $orderId = $data['id'] ?? '';
        $approveUrl = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = $link['href'] ?? null;
                break;
            }
        }

        if ($orderId === '' || $approveUrl === null || $approveUrl === '') {
            throw new \RuntimeException('PayPal: invalid create order response.');
        }

        return new PaymentResult(
            provider: self::NAME,
            referenceId: $orderId,
            redirectUrl: $approveUrl,
            clientSecret: null,
        );
    }

    public function handleReturn(Request $request): ?PaymentResolution
    {
        $token = $request->query->get('token');
        if (!is_string($token) || $token === '') {
            return null;
        }

        if (str_starts_with($token, 'paypal_')) {
            return null;
        }

        if (trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            return null;
        }

        return $this->captureAndResolve($token);
    }

    public function handleWebhook(Request $request): ?PaymentResolution
    {
        if (trim($this->clientId) === '' || trim($this->clientSecret) === '' || $this->webhookId === null || trim($this->webhookId) === '') {
            return null;
        }

        $payload = $request->getContent();
        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return null;
        }

        $transmissionId = $request->headers->get('PayPal-Transmission-Id');
        $transmissionTime = $request->headers->get('PayPal-Transmission-Time');
        $certUrl = $request->headers->get('PayPal-Cert-Url');
        $authAlgo = $request->headers->get('PayPal-Auth-Algo');
        $transmissionSig = $request->headers->get('PayPal-Transmission-Sig');
        if (!$transmissionId || !$transmissionTime || !$certUrl || !$authAlgo || !$transmissionSig) {
            $this->logger->warning('PayPal webhook missing verification headers');

            return null;
        }

        try {
            $accessToken = $this->fetchAccessToken();
            $verifyResponse = $this->httpClient->request('POST', $this->baseUrl . '/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'transmission_id' => $transmissionId,
                    'transmission_time' => $transmissionTime,
                    'cert_url' => $certUrl,
                    'auth_algo' => $authAlgo,
                    'transmission_sig' => $transmissionSig,
                    'webhook_id' => $this->webhookId,
                    'webhook_event' => $json,
                ],
            ]);
            $verify = $verifyResponse->toArray(false);
            if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
                $this->logger->warning('PayPal webhook verification failed', ['verify' => $verify]);

                return null;
            }
        } catch (\Throwable $e) {
            $this->logger->error('PayPal webhook verify request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $eventType = (string) ($json['event_type'] ?? '');
        $resource = $json['resource'] ?? null;
        if (!is_array($resource)) {
            return null;
        }

        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        if (!is_string($paypalOrderId) || $paypalOrderId === '') {
            return null;
        }

        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_SUCCEEDED, null),
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.REFUNDED' => new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_FAILED, null),
            default => null,
        };
    }

    public function getClientSecretForReference(string $referenceId): ?string
    {
        return null;
    }

    private function captureAndResolve(string $paypalOrderId): ?PaymentResolution
    {
        try {
            $accessToken = $this->fetchAccessToken();
            $response = $this->httpClient->request('POST', $this->baseUrl . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation',
                ],
                'body' => '{}',
            ]);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 400) {
                $this->logger->error('PayPal capture failed', ['order_id' => $paypalOrderId, 'body' => $data]);

                return new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_FAILED, null);
            }

            $orderStatus = $data['status'] ?? '';
            if ($orderStatus === 'COMPLETED') {
                return new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_SUCCEEDED, null);
            }

            return new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_PENDING, null);
        } catch (\Throwable $e) {
            $this->logger->error('PayPal capture exception', ['error' => $e->getMessage()]);

            return new PaymentResolution($paypalOrderId, PaymentResolution::STATUS_FAILED, null);
        }
    }

    private function fetchAccessToken(): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/oauth2/token', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
            ],
            'body' => 'grant_type=client_credentials',
            'auth_basic' => [$this->clientId, $this->clientSecret],
        ]);

        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            throw new \RuntimeException('PayPal OAuth failed.');
        }

        return (string) $data['access_token'];
    }

    private function formatMoney(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
