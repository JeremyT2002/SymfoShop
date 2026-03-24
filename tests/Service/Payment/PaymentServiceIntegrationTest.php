<?php

namespace App\Tests\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentService;
use App\Service\Payment\Provider\PaymentResolution;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PaymentService $paymentService;
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->paymentService = $container->get(PaymentService::class);
        $this->paymentRepository = $container->get(PaymentRepository::class);
    }

    public function testApplyResolutionReturnsNullWhenUnknownReference(): void
    {
        $resolution = new PaymentResolution('no_such_intent', PaymentResolution::STATUS_SUCCEEDED);

        $this->assertNull($this->paymentService->applyResolution($resolution));
    }

    public function testApplyResolutionUpdatesPaymentStatus(): void
    {
        $order = $this->createOrder();
        $intent = 'pi_apply_' . bin2hex(random_bytes(6));
        $payment = $this->createPayment($order, $intent, 'pending');

        $resolution = new PaymentResolution($intent, PaymentResolution::STATUS_SUCCEEDED);
        $updated = $this->paymentService->applyResolution($resolution);

        $this->assertSame($payment->getId(), $updated?->getId());
        $this->assertSame(PaymentResolution::STATUS_SUCCEEDED, $updated->getStatus());
    }

    public function testGetPaymentByIntentId(): void
    {
        $order = $this->createOrder();
        $intent = 'pi_get_' . bin2hex(random_bytes(6));
        $this->createPayment($order, $intent, 'pending');

        $found = $this->paymentService->getPaymentByIntentId($intent);
        $this->assertNotNull($found);
        $this->assertSame($intent, $found->getPaymentIntentId());
    }

    public function testHandlePaymentSuccessThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment not found');

        $this->paymentService->handlePaymentSuccess('pi_does_not_exist');
    }

    public function testHandlePaymentFailureThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment not found');

        $this->paymentService->handlePaymentFailure('pi_does_not_exist');
    }

    public function testCreatePaymentIntentReusesExistingNonFailedPaymentForSameProvider(): void
    {
        $order = $this->createOrder();
        $first = $this->paymentService->createPaymentIntent($order, 'dev');
        $second = $this->paymentService->createPaymentIntent($order, 'dev');

        $this->assertSame($first['paymentIntentId'], $second['paymentIntentId']);
    }

    public function testGetRegistry(): void
    {
        $this->assertNotEmpty($this->paymentService->getRegistry()->getAvailableNames());
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-PAY-' . uniqid());
        $order->setEmail('pay-svc@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(1000);
        $order->setTaxTotal(200);
        $order->setGrandTotal(1200);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPayment(Order $order, string $intentId, string $status): Payment
    {
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setProvider('stripe');
        $payment->setPaymentIntentId($intentId);
        $payment->setStatus($status);
        $payment->setAmount($order->getGrandTotal());
        $payment->setCurrency($order->getCurrency());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
