<?php

namespace App\Tests\Service\Payment\Provider;

use App\Entity\Order;
use App\Service\Payment\Provider\KlarnaPaymentProvider;
use App\Service\Payment\Provider\PayPalPaymentProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class StubPaymentProvidersTest extends TestCase
{
    public function testPayPalStub(): void
    {
        $p = new PayPalPaymentProvider();
        $this->assertSame('paypal', $p->getName());
        $this->assertNull($p->handleReturn(Request::create('/')));
        $this->assertNull($p->handleWebhook(Request::create('/')));
        $this->assertNull($p->getClientSecretForReference('x'));

        $result = $p->startPayment($this->minimalOrder());
        $this->assertSame('paypal', $result->provider);
        $this->assertStringStartsWith('paypal_', $result->referenceId);
        $this->assertNull($result->clientSecret);
    }

    public function testKlarnaStub(): void
    {
        $p = new KlarnaPaymentProvider();
        $this->assertSame('klarna', $p->getName());
        $this->assertNull($p->handleReturn(Request::create('/')));
        $this->assertNull($p->handleWebhook(Request::create('/')));
        $this->assertNull($p->getClientSecretForReference('x'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Klarna provider is not implemented');
        $p->startPayment($this->minimalOrder());
    }

    private function minimalOrder(): Order
    {
        $o = new Order();
        $o->setOrderNumber('ORD-P-' . uniqid());
        $o->setEmail('p@example.com');
        $o->setCurrency('EUR');
        $o->setStatus('new');
        $o->setSubtotal(1);
        $o->setTaxTotal(0);
        $o->setGrandTotal(1);

        return $o;
    }
}
