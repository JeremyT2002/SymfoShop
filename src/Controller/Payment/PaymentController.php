<?php

namespace App\Controller\Payment;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\Payment\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderRepository $orderRepository
    ) {
    }

    #[Route('/payment/create-intent/{orderId}', name: 'payment_create_intent', methods: ['POST'])]
    public function createIntent(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() !== 'new') {
            return new JsonResponse(['error' => 'Order is not in new status'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->paymentService->createPaymentIntent($order);

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

