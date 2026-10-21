<?php

namespace App\Controller\Webhook;

use App\Entity\ProcessedWebhookEvent;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Payment\OrderPaymentFinalizer;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\PaymentService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ProcessedWebhookEventRepository $webhookEventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ?string $stripeWebhookSecret,
        private readonly OrderPaymentFinalizer $paymentFinalizer,
    ) {
    }

    #[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        if ($this->stripeWebhookSecret === null || trim((string) $this->stripeWebhookSecret) === '') {
            $this->logger->error('Stripe webhook signing secret is not configured (set STRIPE_WEBHOOK_SECRET)');

            return new Response('Webhook signing not configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

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

        $eventId = $event->id;
        $existing = $this->webhookEventRepository->findOneByEventId($eventId);
        if ($existing instanceof ProcessedWebhookEvent) {
            if ($existing->getStatus() === ProcessedWebhookEvent::STATUS_COMPLETED) {
                $this->logger->info('Stripe webhook event already completed', ['event_id' => $eventId]);

                return new Response('OK', Response::HTTP_OK);
            }

            // Another worker is still processing this event — ask Stripe to retry shortly.
            $this->logger->notice('Stripe webhook event still pending (concurrent delivery)', ['event_id' => $eventId]);

            return new Response('Processing', Response::HTTP_SERVICE_UNAVAILABLE, [
                'Retry-After' => '3',
            ]);
        }

        $claim = new ProcessedWebhookEvent();
        $claim->setEventId($eventId);
        $claim->setStatus(ProcessedWebhookEvent::STATUS_PENDING);
        $this->entityManager->persist($claim);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if (!$this->isUniqueConstraintViolation($e)) {
                throw $e;
            }

            $this->entityManager->clear();
            $race = $this->webhookEventRepository->findOneByEventId($eventId);
            if ($race instanceof ProcessedWebhookEvent && $race->getStatus() === ProcessedWebhookEvent::STATUS_COMPLETED) {
                return new Response('OK', Response::HTTP_OK);
            }

            return new Response('Processing', Response::HTTP_SERVICE_UNAVAILABLE, [
                'Retry-After' => '3',
            ]);
        }

        try {
            $this->handleStripeEvent($event);
            $claim->setStatus(ProcessedWebhookEvent::STATUS_COMPLETED);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Error handling Stripe webhook event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->entityManager->remove($claim);
            $this->entityManager->flush();

            return new Response('Error processing event', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    private function isUniqueConstraintViolation(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if ($t instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
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

        $status = match ($event->type) {
            'payment_intent.succeeded' => PaymentResolution::STATUS_SUCCEEDED,
            'payment_intent.payment_failed', 'payment_intent.canceled' => PaymentResolution::STATUS_FAILED,
            default => null,
        };
        if ($status !== null) {
            $this->paymentService->applyResolution(new PaymentResolution($paymentIntentId, $status, $order->getId()));
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                if ($this->paymentFinalizer->markPaid($order, 'stripe_webhook')) {
                    $this->logger->info('Order payment confirmed via webhook', [
                        'order_number' => $order->getOrderNumber(),
                    ]);
                }
                break;

            case 'payment_intent.payment_failed':
                // A declined attempt is not final: the customer can retry with the same PaymentIntent.
                // Stock stays reserved until the intent is cancelled or the reservation expires.
                $this->logger->info('Stripe payment attempt failed', [
                    'order_number' => $order->getOrderNumber(),
                ]);
                break;

            case 'payment_intent.canceled':
                if ($this->paymentFinalizer->cancelUnpaid($order, 'stripe_webhook')) {
                    $this->logger->info('Order cancelled because its payment intent was cancelled', [
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

