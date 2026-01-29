<?php

namespace App\Controller\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Form\Checkout\AddressType;
use App\Form\Checkout\CustomerInfoType;
use App\Service\Cart\CartService;
use App\Service\Checkout\CheckoutService;
use App\Service\Payment\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class CheckoutController extends AbstractController implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            'workflow.order' => '?Symfony\Component\Workflow\WorkflowInterface',
            \Stripe\StripeClient::class => '?Stripe\StripeClient',
        ];
    }

    private function getOrderWorkflow(): WorkflowInterface
    {
        return $this->container->get('workflow.order');
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        // Validate cart has items
        $validation = $this->checkoutService->validateCart();
        if (!$validation['valid']) {
            $this->addFlash('error', 'Your cart is empty. Please add items before checkout.');
            return $this->redirectToRoute('cart_show');
        }

        $totals = $this->checkoutService->calculateTotals();

        $customerInfo = new CustomerInfoDTO('', '', '');
        $shippingAddress = new AddressDTO('', '', '', '');

        $customerForm = $this->createForm(CustomerInfoType::class, $customerInfo);
        $addressForm = $this->createForm(AddressType::class, $shippingAddress);

        if ($request->isMethod('POST')) {
            $customerForm->handleRequest($request);
            $addressForm->handleRequest($request);

            if ($customerForm->isSubmitted() && $customerForm->isValid() &&
                $addressForm->isSubmitted() && $addressForm->isValid()) {
                $customerInfo = $customerForm->getData();
                $shippingAddress = $addressForm->getData();

                try {
                    $order = $this->checkoutService->createOrder($customerInfo, $shippingAddress);

                    // Create payment intent
                    $paymentIntent = $this->paymentService->createPaymentIntent($order);

                    // Transition order to payment_pending
                    if ($this->getOrderWorkflow()->can($order, 'submit_payment')) {
                        $this->getOrderWorkflow()->apply($order, 'submit_payment');
                        $this->entityManager->flush();
                    }

                    return $this->redirectToRoute('checkout_payment', [
                        'orderId' => $order->getId(),
                        'paymentIntentId' => $paymentIntent['paymentIntentId'],
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'An error occurred while creating your order: ' . $e->getMessage());
                }
            }
        }

        return $this->render('checkout/index.html.twig', [
            'customerForm' => $customerForm,
            'addressForm' => $addressForm,
            'totals' => $totals,
        ]);
    }

    #[Route('/checkout/payment/{orderId}/{paymentIntentId}', name: 'checkout_payment', methods: ['GET'])]
    public function payment(int $orderId, string $paymentIntentId): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if ($order->getStatus() !== 'payment_pending') {
            $this->addFlash('error', 'Order is not in payment pending status');
            return $this->redirectToRoute('cart_show');
        }

        // Get payment intent client secret
        $payment = $this->paymentService->getPaymentByIntentId($paymentIntentId);
        if (!$payment || $payment->getOrder()->getId() !== $order->getId()) {
            throw $this->createNotFoundException('Payment not found');
        }

        // Retrieve client secret from Stripe
        try {
            $stripeClient = $this->container->get(\Stripe\StripeClient::class);
            $paymentIntent = $stripeClient->paymentIntents->retrieve($paymentIntentId);
            $clientSecret = $paymentIntent->client_secret;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error retrieving payment information');
            return $this->redirectToRoute('cart_show');
        }

        return $this->render('checkout/payment.html.twig', [
            'order' => $order,
            'paymentIntentId' => $paymentIntentId,
            'clientSecret' => $clientSecret,
            'stripePublishableKey' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? 'pk_test_placeholder',
        ]);
    }

    #[Route('/checkout/success/{orderNumber}', name: 'checkout_success', methods: ['GET'])]
    public function success(string $orderNumber): Response
    {
        return $this->render('checkout/success.html.twig', [
            'orderNumber' => $orderNumber,
        ]);
    }
}
