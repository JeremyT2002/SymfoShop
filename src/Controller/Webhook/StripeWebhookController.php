<?php

namespace App\Controller\Webhook;

use App\Entity\ProcessedWebhookEvent;
use App\Repository\OrderRepository;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Inventory\InventoryService;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class StripeWebhookController extends AbstractController implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderRepository $orderRepository,
        private readonly ProcessedWebhookEventRepository $webhookEventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ?string $stripeWebhookSecret,
        private readonly InventoryService $inventoryService,
        private readonly InvoiceService $invoiceService
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            'workflow.order' => '?Symfony\Component\Workflow\WorkflowInterface',
        ];
    }

    private function getOrderWorkflow(): WorkflowInterface
    {
        return $this->container->get('workflow.order');
    }

    #[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        if (!$signature) {
            $this->logger->warning('Stripe webhook received without signature');
            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        // Verify webhook signature
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->stripeWebhookSecret
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook processing error', [
                'error' => $e->getMessage(),
            ]);
            return new Response('Webhook processing error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Idempotency check
        $eventId = $event->id;
        $processedEvent = $this->webhookEventRepository->findOneByEventId($eventId);

        if ($processedEvent) {
            $this->logger->info('Stripe webhook event already processed', ['event_id' => $eventId]);
            return new Response('Event already processed', Response::HTTP_OK);
        }

        // Mark event as processed
        $processedEvent = new ProcessedWebhookEvent();
        $processedEvent->setEventId($eventId);
        $this->entityManager->persist($processedEvent);
        $this->entityManager->flush();

        // Handle event
        try {
            $this->handleStripeEvent($event);
        } catch (\Exception $e) {
            $this->logger->error('Error handling Stripe webhook event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't remove the processed event record - we want idempotency
            return new Response('Error processing event', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    private function handleStripeEvent(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;

        if (!$paymentIntent instanceof \Stripe\PaymentIntent) {
            $this->logger->info('Stripe webhook event ignored - not a PaymentIntent', [
                'event_type' => $event->type,
            ]);
            return;
        }

        $paymentIntentId = $paymentIntent->id;
        $payment = $this->paymentService->getPaymentByIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger->warning('Stripe webhook received for unknown payment intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $order = $payment->getOrder();

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->paymentService->handlePaymentSuccess($paymentIntentId);

                // Commit inventory (convert reservation to actual stock reduction)
                try {
                    $this->inventoryService->commit($order);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to commit inventory for order', [
                        'order_id' => $order->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                // Transition order to paid
                if ($this->getOrderWorkflow()->can($order, 'confirm_payment')) {
                    $this->getOrderWorkflow()->apply($order, 'confirm_payment');
                    $this->entityManager->flush();
                    $this->logger->info('Order payment confirmed via webhook', [
                        'order_id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                    ]);
                }

                // Generate invoice and send email (only after payment confirmed)
                try {
                    $this->invoiceService->createInvoiceForOrder($order);
                    $this->logger->info('Invoice created and email queued for order', [
                        'order_id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to create invoice for order', [
                        'order_id' => $order->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the webhook if invoice generation fails
                }
                break;

            case 'payment_intent.payment_failed':
            case 'payment_intent.canceled':
                $this->paymentService->handlePaymentFailure($paymentIntentId);

                // Release inventory reservation
                try {
                    $this->inventoryService->release($order);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to release inventory for order', [
                        'order_id' => $order->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                // Cancel order if still in payment_pending or paid state
                if ($this->getOrderWorkflow()->can($order, 'cancel')) {
                    $this->getOrderWorkflow()->apply($order, 'cancel');
                    $this->entityManager->flush();
                    $this->logger->info('Order cancelled due to payment failure', [
                        'order_id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                    ]);
                }
                break;

            default:
                $this->logger->info('Unhandled Stripe webhook event type', [
                    'event_type' => $event->type,
                    'payment_intent_id' => $paymentIntentId,
                ]);
        }
    }
}

