<?php

namespace App\Tests\Service\Payment\Provider;

use App\Entity\Order;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\Provider\StripePaymentProvider;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Request;

final class StripePaymentProviderTest extends TestCase
{
    public function testGetName(): void
    {
        $p = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), null);
        $this->assertSame('stripe', $p->getName());
    }

    public function testStartPaymentSuccess(): void
    {
        $order = $this->order(5000, 'EUR');
        $stub = new PaymentIntentsStub();
        $p = new StripePaymentProvider($this->stubClient($stub), null);

        $r = $p->startPayment($order);

        $this->assertSame('stripe', $r->provider);
        $this->assertSame('pi_stub', $r->referenceId);
        $this->assertSame('cs_test', $r->clientSecret);
    }

    public function testStartPaymentWrapsStripeApiError(): void
    {
        $order = $this->order(100, 'EUR');
        $stub = new PaymentIntentsStub();
        $stub->createException = InvalidRequestException::factory('declined', 402);
        $p = new StripePaymentProvider($this->stubClient($stub), null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Stripe payment intent');
        $p->startPayment($order);
    }

    public function testHandleReturnWithoutPaymentIntent(): void
    {
        $p = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), null);
        $this->assertNull($p->handleReturn(Request::create('/')));
    }

    public function testHandleReturnSucceeded(): void
    {
        $stub = new PaymentIntentsStub();
        $stub->retrieveStatus = 'succeeded';
        $p = new StripePaymentProvider($this->stubClient($stub), null);
        $req = Request::create('/', 'GET', ['payment_intent' => 'pi_x']);

        $res = $p->handleReturn($req);
        $this->assertNotNull($res);
        $this->assertSame(PaymentResolution::STATUS_SUCCEEDED, $res->status);
        $this->assertSame(7, $res->orderId);
    }

    public function testHandleReturnPendingStatuses(): void
    {
        foreach (['requires_payment_method', 'requires_confirmation', 'requires_action'] as $status) {
            $stub = new PaymentIntentsStub();
            $stub->retrieveStatus = $status;
            $p = new StripePaymentProvider($this->stubClient($stub), null);
            $req = Request::create('/', 'GET', ['payment_intent' => 'pi_p']);

            $res = $p->handleReturn($req);
            $this->assertNotNull($res);
            $this->assertSame(PaymentResolution::STATUS_PENDING, $res->status);
        }
    }

    public function testHandleReturnFailedForOtherStatus(): void
    {
        $stub = new PaymentIntentsStub();
        $stub->retrieveStatus = 'canceled';
        $p = new StripePaymentProvider($this->stubClient($stub), null);
        $req = Request::create('/', 'GET', ['payment_intent' => 'pi_f']);

        $res = $p->handleReturn($req);
        $this->assertNotNull($res);
        $this->assertSame(PaymentResolution::STATUS_FAILED, $res->status);
    }

    public function testHandleReturnRetrieveFailureReturnsNull(): void
    {
        $stub = new PaymentIntentsStub();
        $stub->retrieveThrows = true;
        $p = new StripePaymentProvider($this->stubClient($stub), null);
        $req = Request::create('/', 'GET', ['payment_intent' => 'pi_bad']);

        $this->assertNull($p->handleReturn($req));
    }

    public function testHandleWebhookWithoutSecretReturnsNull(): void
    {
        $p = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), null);
        $this->assertNull($p->handleWebhook(Request::create('/', 'POST', [], [], [], [], '{}')));

        $p2 = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), '');
        $this->assertNull($p2->handleWebhook(Request::create('/', 'POST', [], [], [], [], '{}')));
    }

    public function testHandleWebhookWithoutSignatureReturnsNull(): void
    {
        $p = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), 'whsec_x');
        $this->assertNull($p->handleWebhook(Request::create('/', 'POST', [], [], [], [], '{}')));
    }

    public function testHandleWebhookInvalidPayloadReturnsNull(): void
    {
        $p = new StripePaymentProvider($this->stubClient(new PaymentIntentsStub()), 'whsec_test_secret');
        $req = Request::create('/', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'bad'], 'not-json');

        $this->assertNull($p->handleWebhook($req));
    }

    public function testGetClientSecretForReference(): void
    {
        $stub = new PaymentIntentsStub();
        $p = new StripePaymentProvider($this->stubClient($stub), null);
        $this->assertSame('cs_test', $p->getClientSecretForReference('pi_ref'));

        $stub->retrieveThrows = true;
        $this->assertNull($p->getClientSecretForReference('pi_missing'));
    }

    public function testGetClientSecretNullWhenMissingOnIntent(): void
    {
        $stub = new PaymentIntentsStub();
        $stub->clientSecret = null;
        $p = new StripePaymentProvider($this->stubClient($stub), null);
        $this->assertNull($p->getClientSecretForReference('pi_nosecret'));
    }

    private function order(int $grandTotal, string $currency): Order
    {
        $o = new Order();
        $o->setOrderNumber('ORD-ST-' . uniqid());
        $o->setEmail('s@example.com');
        $o->setCurrency($currency);
        $o->setStatus('new');
        $o->setSubtotal($grandTotal);
        $o->setTaxTotal(0);
        $o->setGrandTotal($grandTotal);

        return $o;
    }

    private function stubClient(object $paymentIntentsService): StripeClient
    {
        return new class ($paymentIntentsService) extends StripeClient {
            public function __construct(private readonly object $paymentIntentsService)
            {
                parent::__construct(['api_key' => 'sk_test_stub']);
            }

            public function __get($name)
            {
                if ($name === 'paymentIntents') {
                    return $this->paymentIntentsService;
                }

                return parent::__get($name);
            }
        };
    }
}

final class PaymentIntentsStub
{
    public ?\Throwable $createException = null;

    public string $retrieveStatus = 'succeeded';

    public bool $retrieveThrows = false;

    public ?string $clientSecret = 'cs_test';

    public function create(array $params, $requestOptions = null): object
    {
        if ($this->createException) {
            throw $this->createException;
        }

        return (object) [
            'id' => 'pi_stub',
            'client_secret' => $this->clientSecret,
        ];
    }

    public function retrieve(string $id, $requestOptions = null): object
    {
        if ($this->retrieveThrows) {
            throw new \RuntimeException('retrieve failed');
        }

        $o = new \stdClass();
        $o->status = $this->retrieveStatus;
        $o->metadata = ['order_id' => '7'];
        $o->id = $id;
        $o->client_secret = $this->clientSecret;

        return $o;
    }
}
