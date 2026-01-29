<?php

namespace App\Workflow\Order;

use App\Entity\Order;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

class OrderWorkflowGuard
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    public function guardSubmitPayment(GuardEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        // Allow if admin or order is in 'new' state
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN') && $order->getStatus() !== 'new') {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can submit payment for non-new orders',
                '0'
            ));
        }
    }

    public function guardConfirmPayment(GuardEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can confirm payment',
                '0'
            ));
        }
    }

    public function guardStartProcessing(GuardEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can start processing',
                '0'
            ));
        }
    }

    public function guardShip(GuardEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can ship orders',
                '0'
            ));
        }
    }

    public function guardComplete(GuardEvent $event): void
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can complete orders',
                '0'
            ));
        }
    }

    public function guardCancel(GuardEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Only admins can cancel orders',
                '0'
            ));
            return;
        }

        // Prevent cancelling completed or already cancelled orders
        if (in_array($order->getStatus(), ['completed', 'cancelled', 'shipped'], true)) {
            $event->addTransitionBlocker(new TransitionBlocker(
                'Cannot cancel order in ' . $order->getStatus() . ' state',
                '0'
            ));
        }
    }
}

