<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Order;
use App\Service\Cart\CouponService;
use App\Service\Inventory\InventoryService;
use App\Service\Invoice\InvoiceService;
use App\Workflow\Order\OrderWorkflowGuard;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Single place that moves an order forward once a payment provider reports an outcome.
 *
 * Used by the checkout (skip-payment mode), provider return URLs, webhooks and the dev simulator,
 * so every path applies the same rules:
 *  - success is only applied once (payment_pending → paid), inventory and invoice follow it;
 *  - failure/cancel never touches an order that is already paid or further along.
 *
 * Callers must only pass outcomes they verified with the provider (signed webhook, captured order).
 */
final class OrderPaymentFinalizer
{
    /** Order states in which a failed/cancelled payment may still cancel the order. */
    private const CANCELLABLE_ON_FAILURE = ['new', 'payment_pending'];

    public function __construct(
        private readonly WorkflowInterface $orderStateMachine,
        private readonly OrderWorkflowGuard $workflowGuard,
        private readonly InventoryService $inventoryService,
        private readonly InvoiceService $invoiceService,
        private readonly CouponService $couponService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool true when this call moved the order to "paid"
     */
    public function markPaid(Order $order, string $source): bool
    {
        if (!$this->workflowGuard->asSystem(fn () => $this->orderStateMachine->can($order, 'confirm_payment'))) {
            if ($order->getStatus() === 'cancelled') {
                // Money arrived for an order we already gave up on — needs a manual refund or re-open.
                $this->logger->critical('Payment succeeded for a cancelled order', [
                    'order_number' => $order->getOrderNumber(),
                    'source' => $source,
                ]);
            }

            return false;
        }

        try {
            $this->inventoryService->commit($order);
        } catch (\Throwable $e) {
            $this->logger->error('Inventory commit failed after payment', [
                'order_number' => $order->getOrderNumber(),
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }

        $this->workflowGuard->asSystem(fn () => $this->orderStateMachine->apply($order, 'confirm_payment'));
        $this->entityManager->flush();

        $this->couponService->recordOrderUsage($order);

        try {
            $this->invoiceService->createInvoiceForOrder($order);
        } catch (\Throwable $e) {
            $this->logger->error('Invoice creation failed after payment', [
                'order_number' => $order->getOrderNumber(),
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Releases reserved stock and cancels the order — only while it is still unpaid.
     *
     * @return bool true when the order was cancelled by this call
     */
    public function cancelUnpaid(Order $order, string $source): bool
    {
        if (!\in_array($order->getStatus(), self::CANCELLABLE_ON_FAILURE, true)) {
            $this->logger->notice('Ignoring payment failure for an order that is no longer unpaid', [
                'order_number' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'source' => $source,
            ]);

            return false;
        }

        try {
            $this->inventoryService->release($order);
        } catch (\Throwable $e) {
            $this->logger->error('Inventory release failed after payment failure', [
                'order_number' => $order->getOrderNumber(),
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->workflowGuard->asSystem(function () use ($order): bool {
            if (!$this->orderStateMachine->can($order, 'cancel')) {
                return false;
            }

            $this->orderStateMachine->apply($order, 'cancel');
            $this->entityManager->flush();

            return true;
        });
    }
}
