<?php

namespace App\Controller\Checkout;

use App\DTO\Checkout\AddressDTO;
use App\DTO\Checkout\CustomerInfoDTO;
use App\Entity\Order;
use App\Entity\User;
use App\Form\Checkout\AddressType;
use App\Form\Checkout\CustomerInfoType;
use App\Repository\PaymentMethodRepository;
use App\Repository\ShippingMethodRepository;
use App\Service\Cart\CartService;
use App\Service\Checkout\CheckoutService;
use App\Service\Payment\OrderPaymentFinalizer;
use App\Service\Payment\PaymentService;
use App\Service\Payment\Provider\PaymentResolution;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $orderWorkflow,
        private readonly OrderPaymentFinalizer $paymentFinalizer,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly LoggerInterface $logger,
        private readonly bool $checkoutSkipPayment = false,
        private readonly string $kernelEnvironment = 'prod',
        private readonly string $paypalClientId = '',
    ) {
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        // Validate cart has items
        $validation = $this->checkoutService->validateCart();
        if (!$validation['valid']) {
            $this->addFlash('error', 'checkout.flash.cart_empty');
            return $this->redirectToRoute('cart_show');
        }

        $activePaymentMethods = $this->paymentMethodRepository->findActiveOrdered();
        $paymentMethodCodes = array_map(
            static fn ($method) => $method->getCode(),
            $activePaymentMethods
        );
        $defaultPaymentMethod = $this->paymentMethodRepository->findDefaultActive();
        $selectedPaymentMethod = (string) ($request->request->get('payment_method') ?: ($defaultPaymentMethod?->getCode() ?? ''));

        $shippingMethods = $this->shippingMethodRepository->findActiveOrdered();
        $shippingCodes = array_map(
            static fn ($m) => $m->getCode(),
            $shippingMethods
        );
        $defaultShippingMethod = $this->shippingMethodRepository->findFirstActive();
        $selectedShippingMethod = (string) ($request->request->get('shipping_method') ?: ($defaultShippingMethod?->getCode() ?? ''));

        $customerInfo = new CustomerInfoDTO('', '', '');
        $shippingAddress = new AddressDTO('', '', '', '');

        $user = $this->getUser();
        if ($user instanceof User) {
            $customerInfo = new CustomerInfoDTO(
                $user->getEmail() ?? '',
                $user->getFirstName() ?? '',
                $user->getLastName() ?? '',
                $user->getPhone()
            );
            $streetParts = array_filter([$user->getAddressLine1(), $user->getAddressLine2()]);
            $street = $streetParts !== [] ? implode(', ', $streetParts) : '';
            $shippingAddress = new AddressDTO(
                $street,
                $user->getCity() ?? '',
                $user->getPostalCode() ?? '',
                $user->getCountryCode() ?? '',
                $user->getState()
            );
        }

        $customerForm = $this->createForm(CustomerInfoType::class, $customerInfo);
        $addressForm = $this->createForm(AddressType::class, $shippingAddress);

        if ($request->isMethod('POST')) {
            $customerForm->handleRequest($request);
            $addressForm->handleRequest($request);
        }

        $customerInfo = $customerForm->getData();
        $shippingAddress = $addressForm->getData();
        $countryForTax = trim($shippingAddress->country) !== '' ? trim($shippingAddress->country) : null;

        $totals = $this->checkoutService->calculateTotals(
            $selectedShippingMethod !== '' ? $selectedShippingMethod : null,
            $countryForTax,
            $user instanceof User ? $user : null
        );

        if ($request->isMethod('POST')
            && $customerForm->isSubmitted() && $customerForm->isValid()
            && $addressForm->isSubmitted() && $addressForm->isValid()
        ) {
            // Reject unknown methods before an order (and its stock reservation) exists.
            if ($selectedShippingMethod !== '' && $shippingCodes !== [] && !in_array($selectedShippingMethod, $shippingCodes, true)) {
                $this->addFlash('error', 'checkout.flash.shipping_method_unavailable');
            } elseif ($selectedPaymentMethod !== '' && !in_array($selectedPaymentMethod, $paymentMethodCodes, true)) {
                $this->addFlash('error', 'checkout.flash.payment_method_unavailable');
            } else {
                $response = $this->placeOrder(
                    $customerInfo,
                    $shippingAddress,
                    $selectedShippingMethod,
                    $selectedPaymentMethod,
                    $user instanceof User ? $user : null
                );
                if ($response !== null) {
                    return $response;
                }
            }
        }

        return $this->render('checkout/index.html.twig', [
            'customerForm' => $customerForm,
            'addressForm' => $addressForm,
            'totals' => $totals,
            'paymentMethods' => $activePaymentMethods,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'shippingMethods' => $shippingMethods,
            'selectedShippingMethod' => $selectedShippingMethod,
        ]);
    }

    #[Route('/checkout/payment/{orderId}/{paymentIntentId}', name: 'checkout_payment', methods: ['GET'])]
    public function payment(int $orderId, string $paymentIntentId): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw $this->createNotFoundException('checkout.not_found.order');
        }

        if ($order->getStatus() !== 'payment_pending') {
            $this->addFlash('error', 'checkout.flash.order_not_pending');
            return $this->redirectToRoute('cart_show');
        }

        $payment = $this->paymentService->getPaymentByIntentId($paymentIntentId);
        if (!$payment || $payment->getOrder()->getId() !== $order->getId()) {
            throw $this->createNotFoundException('checkout.not_found.payment');
        }

        $provider = $this->paymentService->getRegistry()->get($payment->getProvider());
        $clientSecret = $provider->getClientSecretForReference($paymentIntentId);

        return $this->render('checkout/payment.html.twig', [
            'order' => $order,
            'payment' => $payment,
            'paymentIntentId' => $paymentIntentId,
            'clientSecret' => $clientSecret,
            'paymentProvider' => $payment->getProvider(),
            'stripePublishableKey' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? 'pk_test_placeholder',
            'simulatePaymentUi' => $this->isSimulatedPaymentUi($payment->getProvider()),
        ]);
    }

    #[Route('/checkout/success/{orderNumber}', name: 'checkout_success', methods: ['GET'])]
    public function success(string $orderNumber): Response
    {
        return $this->render('checkout/success.html.twig', [
            'orderNumber' => $orderNumber,
        ]);
    }

    /**
     * Creates the order and starts the payment. Returns null (with a flash message) when that fails.
     */
    private function placeOrder(
        CustomerInfoDTO $customerInfo,
        AddressDTO $shippingAddress,
        string $shippingMethod,
        string $paymentMethod,
        ?User $user,
    ): ?Response {
        try {
            $order = $this->checkoutService->createOrder(
                $customerInfo,
                $shippingAddress,
                $shippingMethod !== '' ? $shippingMethod : null,
                $user
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Checkout: order could not be created', ['error' => $e->getMessage()]);
            $this->addFlash('error', str_contains($e->getMessage(), 'Insufficient stock')
                ? 'checkout.flash.out_of_stock'
                : 'checkout.flash.order_failed');

            return null;
        }

        try {
            if ($this->checkoutSkipPayment && \in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
                $paymentIntent = $this->paymentService->createPaymentIntent($order, 'dev');
                $this->submitPayment($order);
                $this->paymentService->applyResolution(new PaymentResolution(
                    $paymentIntent['paymentIntentId'],
                    PaymentResolution::STATUS_SUCCEEDED,
                    $order->getId()
                ));
                $this->paymentFinalizer->markPaid($order, 'checkout_skip_payment');
                $this->addFlash('info', 'checkout.skip_payment_dev_notice');

                return $this->redirectToRoute('checkout_success', [
                    'orderNumber' => $order->getOrderNumber(),
                ]);
            }

            $paymentIntent = $this->paymentService->createPaymentIntent($order, $paymentMethod !== '' ? $paymentMethod : null);
            $this->submitPayment($order);
        } catch (\Throwable $e) {
            // The order exists but no payment could be started: give the stock back.
            $this->logger->error('Checkout: payment could not be started', [
                'order_number' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
            $this->paymentFinalizer->cancelUnpaid($order, 'checkout_payment_start_failed');
            $this->addFlash('error', 'checkout.flash.payment_start_failed');

            return null;
        }

        if (!empty($paymentIntent['redirectUrl'])) {
            return $this->redirect($paymentIntent['redirectUrl']);
        }

        return $this->redirectToRoute('checkout_payment', [
            'orderId' => $order->getId(),
            'paymentIntentId' => $paymentIntent['paymentIntentId'],
        ]);
    }

    private function submitPayment(Order $order): void
    {
        if ($this->orderWorkflow->can($order, 'submit_payment')) {
            $this->orderWorkflow->apply($order, 'submit_payment');
            $this->entityManager->flush();
        }
    }

    private function isSimulatedPaymentUi(string $provider): bool
    {
        if (in_array($provider, ['dev', 'testbank'], true)) {
            return true;
        }

        return $provider === 'paypal' && trim($this->paypalClientId) === '';
    }
}
