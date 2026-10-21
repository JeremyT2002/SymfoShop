<?php

namespace App\Controller\Dev;

use App\Repository\PaymentRepository;
use App\Service\Payment\OrderPaymentFinalizer;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly OrderPaymentFinalizer $paymentFinalizer,
        private readonly string $kernelEnvironment
    ) {
    }

    #[Route('/simulate/{referenceId}/{outcome}', name: 'simulate', requirements: ['referenceId' => '(dev|paypal|testbank)_[a-f0-9]+', 'outcome' => 'success|failure|pending'])]
    public function simulate(string $referenceId, string $outcome): Response
    {
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            throw $this->createNotFoundException();
        }

        $payment = $this->paymentRepository->findOneByPaymentIntentId($referenceId);
        if (!$payment || !in_array($payment->getProvider(), ['dev', 'paypal', 'testbank'], true)) {
            $this->addFlash('error', 'Payment not found or not a simulated payment.');
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
            $this->paymentFinalizer->markPaid($order, 'dev_simulator');

            return $this->redirectToRoute('checkout_success', ['orderNumber' => $order->getOrderNumber()]);
        }

        if ($outcome === 'failure') {
            $this->paymentFinalizer->cancelUnpaid($order, 'dev_simulator');
            $this->addFlash('error', 'checkout.dev_simulated_failure');

            return $this->redirectToRoute('cart_show');
        }

        $this->addFlash('info', 'checkout.dev_simulated_pending');
        return $this->redirectToRoute('checkout_payment', [
            'orderId' => $order->getId(),
            'paymentIntentId' => $referenceId,
        ]);
    }
}
