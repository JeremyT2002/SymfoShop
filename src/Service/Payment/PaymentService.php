<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class PaymentService
{
    private const PROVIDER_STRIPE = 'stripe';

    public function __construct(
        private readonly StripeClient $stripeClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $paymentRepository
    ) {
    }

    /**
     * Create a Stripe PaymentIntent for an order
     *
     * @return array{paymentIntentId: string, clientSecret: string}
     */
    public function createPaymentIntent(Order $order): array
    {
        // Check if payment already exists for this order
        $existingPayments = $this->paymentRepository->findBy(['order' => $order]);
        $existingPayment = !empty($existingPayments) ? $existingPayments[0] : null;
        if ($existingPayment && $existingPayment->getStatus() !== 'failed') {
            // Return existing payment intent
            try {
                $paymentIntent = $this->stripeClient->paymentIntents->retrieve($existingPayment->getPaymentIntentId());
                return [
                    'paymentIntentId' => $paymentIntent->id,
                    'clientSecret' => $paymentIntent->client_secret,
                ];
            } catch (ApiErrorException $e) {
                // If retrieval fails, create new one
            }
        }

        try {
            $paymentIntent = $this->stripeClient->paymentIntents->create([
                'amount' => $order->getGrandTotal(),
                'currency' => strtolower($order->getCurrency()),
                'metadata' => [
                    'order_number' => $order->getOrderNumber(),
                    'order_id' => $order->getId(),
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            // Create or update payment record
            if ($existingPayment) {
                $payment = $existingPayment;
            } else {
                $payment = new Payment();
                $payment->setOrder($order);
                $payment->setProvider(self::PROVIDER_STRIPE);
            }

            $payment->setPaymentIntentId($paymentIntent->id);
            $payment->setStatus('pending');
            $payment->setAmount($order->getGrandTotal());
            $payment->setCurrency($order->getCurrency());

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            return [
                'paymentIntentId' => $paymentIntent->id,
                'clientSecret' => $paymentIntent->client_secret,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to create payment intent: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handle successful payment webhook
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
     * Handle failed payment webhook
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

    /**
     * Get payment by payment intent ID
     */
    public function getPaymentByIntentId(string $paymentIntentId): ?Payment
    {
        return $this->paymentRepository->findOneByPaymentIntentId($paymentIntentId);
    }
}

