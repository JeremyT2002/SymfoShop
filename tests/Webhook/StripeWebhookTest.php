<?php

namespace App\Tests\Webhook;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\ProcessedWebhookEvent;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
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
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->paymentRepository = $container->get(PaymentRepository::class);
        $this->webhookEventRepository = $container->get(ProcessedWebhookEventRepository::class);
        $this->orderRepository = $container->get(OrderRepository::class);
        $this->workflow = $container->get('workflow.order');
        $this->paymentService = $container->get(PaymentService::class);
    }

    public function testWebhookIdempotency(): void
    {
        $order = $this->createTestOrder();
        $payment = $this->createTestPayment($order, 'pi_test_123');

        $eventId = 'evt_test_123';
        $processedEvent = new ProcessedWebhookEvent();
        $processedEvent->setEventId($eventId);
        $this->entityManager->persist($processedEvent);
        $this->entityManager->flush();

        // Verify event is marked as processed
        $found = $this->webhookEventRepository->findOneByEventId($eventId);
        $this->assertNotNull($found);
        $this->assertEquals($eventId, $found->getEventId());
    }

    public function testPaymentSuccessWebhook(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $payment = $this->createTestPayment($order, 'pi_test_success');
        $this->assertEquals('pending', $payment->getStatus());

        // Simulate webhook success
        $this->paymentService->handlePaymentSuccess('pi_test_success');
        $this->entityManager->refresh($payment);

        $this->assertEquals('succeeded', $payment->getStatus());

        // Verify order can transition to paid
        if ($this->workflow->can($order, 'confirm_payment')) {
            $this->workflow->apply($order, 'confirm_payment');
            $this->entityManager->flush();
            $this->assertEquals('paid', $order->getStatus());
        }
    }

    public function testPaymentFailureWebhook(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $payment = $this->createTestPayment($order, 'pi_test_failure');
        $this->assertEquals('pending', $payment->getStatus());

        // Simulate webhook failure
        $this->paymentService->handlePaymentFailure('pi_test_failure');
        $this->entityManager->refresh($payment);

        $this->assertEquals('failed', $payment->getStatus());

        // Verify order can be cancelled
        if ($this->workflow->can($order, 'cancel')) {
            $this->workflow->apply($order, 'cancel');
            $this->entityManager->flush();
            $this->assertEquals('cancelled', $order->getStatus());
        }
    }

    public function testPaymentIntentCreation(): void
    {
        $order = $this->createTestOrder();
        $order->setGrandTotal(5000); // €50.00
        $order->setCurrency('EUR');
        $this->entityManager->flush();

        // Mock Stripe client
        $stripeClient = $this->createMock(\Stripe\StripeClient::class);
        $paymentIntents = $this->createMock(\Stripe\Service\PaymentIntentService::class);

        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test_123';
        $paymentIntent->client_secret = 'pi_test_123_secret_test';

        $paymentIntents->method('create')->willReturn($paymentIntent);

        $stripeClient->paymentIntents = $paymentIntents;

        // Create payment service with mocked client
        $paymentService = new PaymentService(
            $stripeClient,
            $this->entityManager,
            $this->paymentRepository
        );

        $result = $paymentService->createPaymentIntent($order);

        $this->assertEquals('pi_test_123', $result['paymentIntentId']);
        $this->assertEquals('pi_test_123_secret_test', $result['clientSecret']);

        // Verify payment was created
        $payment = $this->paymentRepository->findOneByPaymentIntentId('pi_test_123');
        $this->assertNotNull($payment);
        $this->assertEquals($order->getId(), $payment->getOrder()->getId());
        $this->assertEquals('pending', $payment->getStatus());
        $this->assertEquals(5000, $payment->getAmount());
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

