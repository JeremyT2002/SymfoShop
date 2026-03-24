<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Entity\ProcessedWebhookEvent;
use App\Repository\PaymentRepository;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Inventory\InventoryService;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\Provider\PayPalPaymentProvider;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\PaymentService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

final class PayPalWebhookController extends AbstractController implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $paymentRepository,
        private readonly ProcessedWebhookEventRepository $webhookEventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly InventoryService $inventoryService,
        private readonly InvoiceService $invoiceService,
        private readonly PayPalPaymentProvider $payPalPaymentProvider,
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            'state_machine.order' => '?'.WorkflowInterface::class,
        ];
    }

    private function getOrderWorkflow(): WorkflowInterface
    {
        return $this->container->get('state_machine.order');
    }

    #[Route('/webhook/paypal', name: 'webhook_paypal', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return new Response('Invalid JSON', Response::HTTP_BAD_REQUEST);
        }

        $eventId = $json['id'] ?? null;
        if (!is_string($eventId) || $eventId === '') {
            return new Response('Missing event id', Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->webhookEventRepository->findOneByEventId($eventId);
        if ($existing instanceof ProcessedWebhookEvent) {
            if ($existing->getStatus() === ProcessedWebhookEvent::STATUS_COMPLETED) {
                return new Response('OK', Response::HTTP_OK);
            }

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
            $resolution = $this->payPalPaymentProvider->handleWebhook($request);
            if ($resolution === null) {
                $this->entityManager->remove($claim);
                $this->entityManager->flush();

                return new Response('OK', Response::HTTP_OK);
            }

            $payment = $this->paymentRepository->findOneByPaymentIntentId($resolution->referenceId);
            if (!$payment) {
                $this->logger->warning('PayPal webhook: unknown PayPal order id', ['reference' => $resolution->referenceId]);
                $claim->setStatus(ProcessedWebhookEvent::STATUS_COMPLETED);
                $this->entityManager->flush();

                return new Response('OK', Response::HTTP_OK);
            }

            $order = $payment->getOrder();
            $this->paymentService->applyResolution($resolution);

            if ($resolution->status === PaymentResolution::STATUS_SUCCEEDED) {
                try {
                    $this->inventoryService->commit($order);
                } catch (\Exception $e) {
                    $this->logger->error('PayPal webhook: inventory commit failed', ['error' => $e->getMessage()]);
                }

                if ($this->getOrderWorkflow()->can($order, 'confirm_payment')) {
                    $this->getOrderWorkflow()->apply($order, 'confirm_payment');
                    $this->entityManager->flush();
                }

                try {
                    $this->invoiceService->createInvoiceForOrder($order);
                } catch (\Exception $e) {
                    $this->logger->error('PayPal webhook: invoice failed', ['error' => $e->getMessage()]);
                }
            } elseif ($resolution->status === PaymentResolution::STATUS_FAILED) {
                try {
                    $this->inventoryService->release($order);
                } catch (\Exception $e) {
                    $this->logger->error('PayPal webhook: inventory release failed', ['error' => $e->getMessage()]);
                }

                if ($this->getOrderWorkflow()->can($order, 'cancel')) {
                    $this->getOrderWorkflow()->apply($order, 'cancel');
                    $this->entityManager->flush();
                }
            }

            $claim->setStatus(ProcessedWebhookEvent::STATUS_COMPLETED);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('PayPal webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->entityManager->remove($claim);
            $this->entityManager->flush();

            return new Response('Error', Response::HTTP_INTERNAL_SERVER_ERROR);
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
}
