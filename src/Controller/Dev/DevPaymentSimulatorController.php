<?php

namespace App\Controller\Dev;

use App\Repository\PaymentRepository;
use App\Service\Inventory\InventoryService;
use App\Service\Invoice\InvoiceService;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Development-only controller to simulate payment outcomes (success/failure/pending).
 * Only available when APP_ENV=dev or test.
 */
#[Route('/_dev/payment', name: 'dev_payment_', methods: ['GET'])]
class DevPaymentSimulatorController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $orderWorkflow,
        private readonly InventoryService $inventoryService,
        private readonly InvoiceService $invoiceService,
        private readonly LoggerInterface $logger,
        private readonly string $kernelEnvironment
    ) {
    }

    #[Route('/simulate/{referenceId}/{outcome}', name: 'simulate', requirements: ['referenceId' => 'dev_[a-f0-9]+', 'outcome' => 'success|failure|pending'])]
    public function simulate(string $referenceId, string $outcome): Response
    {
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            throw $this->createNotFoundException();
        }

        $payment = $this->paymentRepository->findOneByPaymentIntentId($referenceId);
        if (!$payment || $payment->getProvider() !== 'dev') {
            $this->addFlash('error', 'Payment not found or not a dev payment.');
            return $this->redirectToRoute('cart_show');
        }

        $order = $payment->getOrder();
        $status = match ($outcome) {
            'success' => PaymentResolution::STATUS_SUCCEEDED,
            'failure' => PaymentResolution::STATUS_FAILED,
            default => PaymentResolution::STATUS_PENDING,
        };

        $resolution = new PaymentResolution($referenceId, $status, $order->getId());
        $this->paymentService->applyResolution($resolution);

        if ($outcome === 'success') {
            try {
                $this->inventoryService->commit($order);
            } catch (\Exception $e) {
                $this->logger->error('Dev simulator: failed to commit inventory', ['error' => $e->getMessage()]);
            }
            if ($this->orderWorkflow->can($order, 'confirm_payment')) {
                $this->orderWorkflow->apply($order, 'confirm_payment');
                $this->entityManager->flush();
            }
            try {
                $this->invoiceService->createInvoiceForOrder($order);
            } catch (\Exception $e) {
                $this->logger->error('Dev simulator: failed to create invoice', ['error' => $e->getMessage()]);
            }
            return $this->redirectToRoute('checkout_success', ['orderNumber' => $order->getOrderNumber()]);
        }

        if ($outcome === 'failure') {
            try {
                $this->inventoryService->release($order);
            } catch (\Exception $e) {
                $this->logger->error('Dev simulator: failed to release inventory', ['error' => $e->getMessage()]);
            }
            if ($this->orderWorkflow->can($order, 'cancel')) {
                $this->orderWorkflow->apply($order, 'cancel');
                $this->entityManager->flush();
            }
            $this->addFlash('error', 'Payment was simulated as failed.');
            return $this->redirectToRoute('cart_show');
        }

        $this->addFlash('info', 'Payment left as pending.');
        return $this->redirectToRoute('checkout_payment', [
            'orderId' => $order->getId(),
            'paymentIntentId' => $referenceId,
        ]);
    }
}
