<?php

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Service\Payment\Provider\DevPaymentProvider;
use App\Service\Payment\Provider\PaymentResolution;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DevPaymentProviderTest extends TestCase
{
    public function testStartPaymentReturnsDevReference(): void
    {
        $provider = new DevPaymentProvider();
        $order = new Order();
        $order->setGrandTotal(1000);
        $order->setCurrency('EUR');

        $result = $provider->startPayment($order);

        $this->assertSame(DevPaymentProvider::NAME, $result->provider);
        $this->assertStringStartsWith('dev_', $result->referenceId);
        $this->assertNull($result->clientSecret);
    }

    public function testHandleReturnWithValidReference(): void
    {
        $provider = new DevPaymentProvider();
        $request = Request::create('/', 'GET', [
            'reference_id' => 'dev_abc123',
            'outcome' => PaymentResolution::STATUS_SUCCEEDED,
        ]);

        $resolution = $provider->handleReturn($request);

        $this->assertNotNull($resolution);
        $this->assertSame('dev_abc123', $resolution->referenceId);
        $this->assertSame(PaymentResolution::STATUS_SUCCEEDED, $resolution->status);
    }

    public function testHandleReturnIgnoresInvalidReference(): void
    {
        $provider = new DevPaymentProvider();
        $request = Request::create('/', 'GET', ['reference_id' => 'pi_stripe']);

        $this->assertNull($provider->handleReturn($request));
    }

    public function testHandleReturnIgnoresMissingReference(): void
    {
        $provider = new DevPaymentProvider();
        $request = Request::create('/');

        $this->assertNull($provider->handleReturn($request));
    }

    public function testHandleReturnNormalizesUnknownOutcomeToPending(): void
    {
        $provider = new DevPaymentProvider();
        $request = Request::create('/', 'GET', [
            'reference_id' => 'dev_xyz',
            'outcome' => 'not-a-status',
        ]);

        $resolution = $provider->handleReturn($request);
        $this->assertSame(PaymentResolution::STATUS_PENDING, $resolution->status);
    }

    public function testHandleWebhookReturnsNull(): void
    {
        $provider = new DevPaymentProvider();
        $this->assertNull($provider->handleWebhook(Request::create('/')));
    }

    public function testGetClientSecretForReferenceReturnsNull(): void
    {
        $provider = new DevPaymentProvider();
        $this->assertNull($provider->getClientSecretForReference('dev_any'));
    }
}
