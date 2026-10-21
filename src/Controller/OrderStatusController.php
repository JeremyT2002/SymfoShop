<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\Order\OrderNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Order status for guests: order number + e-mail unlock the order for the current session.
 */
final class OrderStatusController extends AbstractController
{
    private const SESSION_KEY = 'order_status_access';

    public function __construct(
        private readonly OrderRepository $orderRepository,
        #[Autowire(service: 'limiter.order_lookup_limiter')]
        private readonly RateLimiterFactory $orderLookupLimiter,
    ) {
    }

    #[Route('/order-status', name: 'order_status_lookup', methods: ['GET', 'POST'])]
    public function lookup(Request $request): Response
    {
        $number = trim((string) $request->get('number', ''));
        $email = trim((string) $request->request->get('email', ''));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('order_status_lookup', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'account.dashboard.flash.invalid_csrf');

                return $this->redirectToRoute('order_status_lookup');
            }

            if (!$this->orderLookupLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
                $this->addFlash('error', 'ui.order_status.rate_limited');

                return $this->redirectToRoute('order_status_lookup');
            }

            $order = $this->orderRepository->findOneBy(['orderNumber' => strtoupper($number)]);
            if ($order instanceof Order && mb_strtolower($order->getEmail()) === mb_strtolower($email)) {
                $session = $request->getSession();
                $session->set(self::SESSION_KEY, array_values(array_unique([...$session->get(self::SESSION_KEY, []), $order->getOrderNumber()])));

                return $this->redirectToRoute('order_status', ['number' => $order->getOrderNumber()]);
            }

            // Same message for unknown numbers and wrong e-mails: no hint which part was wrong
            $this->addFlash('error', 'ui.order_status.not_found');
        }

        return $this->render('order_status/lookup.html.twig', ['number' => $number, 'email' => $email]);
    }

    #[Route('/order-status/{number}', name: 'order_status', methods: ['GET'])]
    public function show(string $number, Request $request): Response
    {
        $order = $this->orderRepository->findOneBy(['orderNumber' => $number]);
        $user = $this->getUser();

        $allowed = $order instanceof Order && (
            \in_array($order->getOrderNumber(), $request->getSession()->get(self::SESSION_KEY, []), true)
            || ($user instanceof User && $order->getUser()?->getId() === $user->getId())
        );
        if (!$allowed) {
            return $this->redirectToRoute('order_status_lookup', ['number' => $number]);
        }

        return $this->render('order_status/show.html.twig', [
            'order' => $order,
            'trackingUrl' => OrderNotifier::trackingUrl($order->getCarrier(), $order->getTrackingNumber()),
        ]);
    }
}
