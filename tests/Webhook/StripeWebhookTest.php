<?php

namespace App\Tests\Webhook;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\ProcessedWebhookEvent;
use App\Repository\PaymentRepository;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

class StripeWebhookTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PaymentRepository $paymentRepository;
    private ProcessedWebhookEventRepository $webhookEventRepository;
    private OrderRepository $orderRepository;
    private WorkflowInterface $workflow;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->paymentRepository = $container->get(PaymentRepository::class);
        $this->webhookEventRepository = $container->get(ProcessedWebhookEventRepository::class);
        $this->workflow = $container->get('state_machine.order');
        $this->paymentService = $container->get(PaymentService::class);

        $token = new UsernamePasswordToken(
            new InMemoryUser('stripe_webhook_test', '', ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN']
        );
        $container->get(TokenStorageInterface::class)->setToken($token);
    }

    public function testWebhookIdempotency(): void
    {
        $order = $this->createTestOrder();
        $this->createTestPayment($order, $this->uniquePaymentIntentId('pi_idem'));

        $eventId = 'evt_test_' . bin2hex(random_bytes(6));
        $processedEvent = new ProcessedWebhookEvent();
        $processedEvent->setEventId($eventId);
        $this->entityManager->persist($processedEvent);
        $this->entityManager->flush();

        $found = $this->webhookEventRepository->findOneByEventId($eventId);
        $this->assertNotNull($found);
        $this->assertEquals($eventId, $found->getEventId());
    }

    public function testPaymentSuccessWebhook(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $intentId = $this->uniquePaymentIntentId('pi_success');
        $payment = $this->createTestPayment($order, $intentId);
        $this->assertEquals('pending', $payment->getStatus());

        $this->paymentService->handlePaymentSuccess($intentId);
        $this->entityManager->refresh($payment);

        $this->assertEquals('succeeded', $payment->getStatus());

        $this->assertTrue($this->workflow->can($order, 'confirm_payment'));
        $this->workflow->apply($order, 'confirm_payment');
        $this->entityManager->flush();
        $this->assertEquals('paid', $order->getStatus());
    }

    public function testPaymentFailureWebhook(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $intentId = $this->uniquePaymentIntentId('pi_failure');
        $payment = $this->createTestPayment($order, $intentId);
        $this->assertEquals('pending', $payment->getStatus());

        $this->paymentService->handlePaymentFailure($intentId);
        $this->entityManager->refresh($payment);

        $this->assertEquals('failed', $payment->getStatus());

        $this->assertTrue($this->workflow->can($order, 'cancel'));
        $this->workflow->apply($order, 'cancel');
        $this->entityManager->flush();
        $this->assertEquals('cancelled', $order->getStatus());
    }

    public function testPaymentIntentCreation(): void
    {
        $order = $this->createTestOrder();
        $order->setGrandTotal(5000);
        $order->setCurrency('EUR');
        $this->entityManager->flush();

        $result = $this->paymentService->createPaymentIntent($order, 'dev');

        $this->assertStringStartsWith('dev_', $result['paymentIntentId']);
        $this->assertSame('', $result['clientSecret']);

        $payment = $this->paymentRepository->findOneByPaymentIntentId($result['paymentIntentId']);
        $this->assertNotNull($payment);
        $this->assertEquals($order->getId(), $payment->getOrder()->getId());
        $this->assertEquals('pending', $payment->getStatus());
        $this->assertEquals(5000, $payment->getAmount());
    }

    private function uniquePaymentIntentId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    private function createTestOrder(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-TEST-' . uniqid());
        $order->setEmail('test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(4000);
        $order->setTaxTotal(800);
        $order->setGrandTotal(4800);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createTestPayment(Order $order, string $paymentIntentId): Payment
    {
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setProvider('stripe');
        $payment->setPaymentIntentId($paymentIntentId);
        $payment->setStatus('pending');
        $payment->setAmount($order->getGrandTotal());
        $payment->setCurrency($order->getCurrency());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
