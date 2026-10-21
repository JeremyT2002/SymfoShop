<?php

namespace App\Workflow\Order;

use App\Entity\Order;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

class OrderWorkflowGuard
{
    /** >0 while a verified payment outcome (webhook, provider return) is being applied. */
    private int $systemDepth = 0;

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly bool $checkoutSkipPayment = false,
        private readonly string $environment = 'prod'
    ) {
    }

    /**
     * Run $callback with payment transitions allowed for the system instead of a logged-in admin.
     * Webhooks and provider return URLs have no admin session, yet must be able to mark orders
     * paid or cancel unpaid ones. Only OrderPaymentFinalizer should call this.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function asSystem(callable $callback): mixed
    {
        ++$this->systemDepth;
        try {
            return $callback();
        } finally {
            --$this->systemDepth;
        }
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
        if ($this->systemDepth > 0) {
            return;
        }

        if ($this->checkoutSkipPayment && \in_array($this->environment, ['dev', 'test'], true)) {
            return;
        }

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

        if ($this->systemDepth === 0 && !$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
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

