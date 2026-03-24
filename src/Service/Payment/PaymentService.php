<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentMethodRepository;
use App\Repository\PaymentRepository;
use App\Service\Payment\Provider\PaymentProviderInterface;
use App\Service\Payment\Provider\PaymentResolution;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    /**
     * Create payment for order using the given provider (or default). Persists Payment and returns intent data.
     *
     * @return array{paymentIntentId: string, clientSecret: string|null}
     */
    public function createPaymentIntent(Order $order, ?string $providerName = null): array
    {
        $resolvedProviderName = $providerName;
        if ($resolvedProviderName === null || trim($resolvedProviderName) === '') {
            $defaultMethod = $this->paymentMethodRepository->findDefaultActive();
            if ($defaultMethod !== null) {
                $resolvedProviderName = $defaultMethod->getCode();
            }
        }

        $provider = $resolvedProviderName
            ? $this->registry->get($resolvedProviderName)
            : $this->registry->getDefault();

        $existingPayment = $this->paymentRepository->findOneBy(
            ['order' => $order],
            ['id' => 'DESC']
        );
        if ($existingPayment && $existingPayment->getStatus() !== 'failed' && $existingPayment->getProvider() === $provider->getName()) {
            $clientSecret = $provider->getClientSecretForReference($existingPayment->getPaymentIntentId());
            return [
                'paymentIntentId' => $existingPayment->getPaymentIntentId(),
                'clientSecret' => $clientSecret ?? '',
            ];
        }

        $result = $provider->startPayment($order);

        if ($existingPayment) {
            $payment = $existingPayment;
        } else {
            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setProvider($result->provider);
        }
        $payment->setPaymentIntentId($result->referenceId);
        $payment->setStatus('pending');
        $payment->setAmount($order->getGrandTotal());
        $payment->setCurrency($order->getCurrency());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return [
            'paymentIntentId' => $result->referenceId,
            'clientSecret' => $result->clientSecret ?? $provider->getClientSecretForReference($result->referenceId) ?? '',
        ];
    }

    /**
     * Apply a payment resolution (from webhook or return). Updates Payment and returns the Payment entity.
     */
    public function applyResolution(PaymentResolution $resolution): ?Payment
    {
        $payment = $this->paymentRepository->findOneByPaymentIntentId($resolution->referenceId);
        if (!$payment) {
            return null;
        }
        $payment->setStatus($resolution->status);
        $this->entityManager->flush();
        return $payment;
    }

    /**
     * Handle successful payment (legacy; prefer applyResolution).
     */
    public function handlePaymentSuccess(string $paymentIntentId): void
    {
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
        if (!$payment) {
            throw new \RuntimeException('Payment not found for payment intent: ' . $paymentIntentId);
        }
        $payment->setStatus('succeeded');
        $this->entityManager->flush();
    }

    /**
     * Handle failed payment (legacy; prefer applyResolution).
     */
    public function handlePaymentFailure(string $paymentIntentId): void
    {
        $payment = $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
        if (!$payment) {
            throw new \RuntimeException('Payment not found for payment intent: ' . $paymentIntentId);
        }
        $payment->setStatus('failed');
        $this->entityManager->flush();
    }

    public function getPaymentByIntentId(string $paymentIntentId): ?Payment
    {
        return $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
    }

    public function getRegistry(): PaymentProviderRegistry
    {
        return $this->registry;
    }
}
