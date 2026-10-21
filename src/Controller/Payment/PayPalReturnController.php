<?php

declare(strict_types=1);

namespace App\Controller\Payment;

use App\Repository\PaymentRepository;
use App\Service\Payment\OrderPaymentFinalizer;
use App\Service\Payment\Provider\PayPalPaymentProvider;
use App\Service\Payment\Provider\PaymentResolution;
use App\Service\Payment\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment/paypal', name: 'payment_paypal_')]
final class PayPalReturnController extends AbstractController
{
    public function __construct(
        private readonly PayPalPaymentProvider $payPalPaymentProvider,
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderPaymentFinalizer $paymentFinalizer,
    ) {
    }

    #[Route('/return', name: 'return', methods: ['GET'])]
    public function return(Request $request): Response
    {
        $resolution = $this->payPalPaymentProvider->handleReturn($request);
        if ($resolution === null) {
            $this->addFlash('error', 'payment.paypal.flash.return_invalid');
            return $this->redirectToRoute('cart_show');
        }

        $payment = $this->paymentRepository->findOneByPaymentIntentId($resolution->referenceId);
        if (!$payment || $payment->getProvider() !== PayPalPaymentProvider::NAME) {
            $this->addFlash('error', 'payment.paypal.flash.payment_not_found');
            return $this->redirectToRoute('cart_show');
        }

        $order = $payment->getOrder();
        $this->paymentService->applyResolution($resolution);

        if ($resolution->status === PaymentResolution::STATUS_SUCCEEDED) {
            $this->paymentFinalizer->markPaid($order, 'paypal_return');

            return $this->redirectToRoute('checkout_success', [
                'orderNumber' => $order->getOrderNumber(),
            ]);
        }

        if ($resolution->status === PaymentResolution::STATUS_FAILED) {
            $this->paymentFinalizer->cancelUnpaid($order, 'paypal_return');
            $this->addFlash('error', 'payment.paypal.flash.capture_failed');

            return $this->redirectToRoute('cart_show');
        }

        return $this->redirectToRoute('checkout_payment', [
            'orderId' => $order->getId(),
            'paymentIntentId' => $resolution->referenceId,
        ]);
    }

    #[Route('/cancel', name: 'cancel', methods: ['GET'])]
    public function cancel(Request $request): Response
    {
        $token = $request->query->get('token');
        if (is_string($token) && $token !== '' && !str_starts_with($token, 'paypal_')) {
            $payment = $this->paymentRepository->findOneByPaymentIntentId($token);
            if ($payment && $payment->getProvider() === PayPalPaymentProvider::NAME) {
                // Only unpaid orders are cancelled; revisiting this URL after paying is harmless.
                $this->paymentFinalizer->cancelUnpaid($payment->getOrder(), 'paypal_cancel');
            }
        }

        $this->addFlash('warning', 'payment.paypal.flash.cancelled');

        return $this->redirectToRoute('cart_show');
    }
}
