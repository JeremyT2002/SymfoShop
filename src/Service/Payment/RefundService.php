<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Refund;
use App\Entity\ReturnRequest;
use App\Repository\RefundRepository;
use App\Service\Inventory\InventoryService;
use App\Service\Order\OrderNotifier;
use App\Service\Payment\Provider\RefundingProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Pays money back for an order: through the payment provider when it supports refunds,
 * otherwise recorded as a manual refund (bank transfer). Every refund gets a credit note.
 */
final class RefundService
{
    /** Order states in which money has been received. */
    public const REFUNDABLE_STATUSES = ['paid', 'processing', 'shipped', 'completed'];

    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly RefundRepository $refunds,
        private readonly InventoryService $inventory,
        private readonly OrderNotifier $notifier,
        private readonly WorkflowInterface $orderStateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Amount (minor units) that can still be refunded. */
    public function refundableAmount(Order $order): int
    {
        if (!\in_array($order->getStatus(), self::REFUNDABLE_STATUSES, true)) {
            return 0;
        }

        return max(0, $order->getGrandTotal() - $this->refunds->sumForOrder($order));
    }

    /** Whether the provider of the order's payment pays back automatically. */
    public function refundsAutomatically(Order $order): bool
    {
        $payment = $this->capturedPayment($order);

        return $payment !== null && $this->refundingProvider($payment) !== null;
    }

    /**
     * @throws \DomainException  for invalid amounts
     * @throws \RuntimeException when the payment provider rejects the refund
     */
    public function refund(
        Order $order,
        int $amount,
        string $reason,
        bool $restock = false,
        ?ReturnRequest $returnRequest = null,
        ?string $actor = null,
    ): Refund {
        $refundable = $this->refundableAmount($order);
        if ($amount <= 0 || $amount > $refundable) {
            throw new \DomainException(sprintf('The refund amount must be between 0.01 and %s.', number_format($refundable / 100, 2)));
        }

        $refund = (new Refund())
            ->setOrder($order)
            ->setReturnRequest($returnRequest)
            ->setAmount($amount)
            ->setTaxAmount($this->taxShare($order, $amount))
            ->setCurrency($order->getCurrency())
            ->setReason(trim($reason))
            ->setCreatedBy($actor)
            ->setProvider(Refund::PROVIDER_MANUAL);

        // Money first: if the provider refuses, nothing is recorded.
        $payment = $this->capturedPayment($order);
        $provider = $payment !== null ? $this->refundingProvider($payment) : null;
        if ($payment !== null && $provider !== null) {
            $outcome = $provider->refund($payment, $amount);
            $refund->setProvider($payment->getProvider())
                ->setProviderReference($outcome->reference)
                ->setStatus($outcome->status);
        }

        try {
            $refund->setCreditNoteNumber($this->refunds->nextCreditNoteNumber());

            if ($restock && !$this->alreadyRestocked($order)) {
                $this->inventory->restock($order);
                $refund->setRestocked(true);
            }

            $this->entityManager->persist($refund);
            $returnRequest?->setStatus(ReturnRequest::STATUS_REFUNDED);

            if ($amount === $refundable && $this->orderStateMachine->can($order, 'refund')) {
                $this->orderStateMachine->apply($order, 'refund');
            }

            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->critical('Refund was paid out but could not be recorded', [
                'order_number' => $order->getOrderNumber(),
                'amount' => $amount,
                'provider_reference' => $refund->getProviderReference(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->notifier->orderRefunded($refund);

        return $refund;
    }

    private function capturedPayment(Order $order): ?Payment
    {
        $succeeded = $order->getPayments()->filter(static fn (Payment $p): bool => $p->getStatus() === 'succeeded');

        return $succeeded->last() ?: null;
    }

    private function refundingProvider(Payment $payment): ?RefundingProviderInterface
    {
        try {
            $provider = $this->providers->get($payment->getProvider());
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $provider instanceof RefundingProviderInterface ? $provider : null;
    }

    /** VAT contained in a partial amount, in the same proportion as on the invoice. */
    private function taxShare(Order $order, int $amount): int
    {
        if ($order->getGrandTotal() <= 0) {
            return 0;
        }

        return (int) round($amount * $order->getTaxTotal() / $order->getGrandTotal());
    }

    private function alreadyRestocked(Order $order): bool
    {
        foreach ($this->refunds->findByOrder($order) as $refund) {
            if ($refund->isRestocked()) {
                return true;
            }
        }

        return false;
    }
}
