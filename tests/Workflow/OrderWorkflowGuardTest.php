<?php

namespace App\Tests\Workflow;

use App\Entity\Order;
use App\Workflow\Order\OrderWorkflowGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * Direct unit coverage for OrderWorkflowGuard (GuardEvent is constructed manually).
 */
final class OrderWorkflowGuardTest extends TestCase
{
    public function testGuardSubmitPaymentBlocksNonAdminWhenOrderNotNew(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        $order = new Order();
        $order->setStatus('paid');
        $event = $this->guardEvent($order, 'payment_pending', 'submit_payment');

        $guard->guardSubmitPayment($event);
        $this->assertTrue($event->isBlocked());
    }

    public function testGuardSubmitPaymentAllowsNonAdminWhenOrderNew(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        $order = new Order();
        $order->setStatus('new');
        $event = $this->guardEvent($order, 'new', 'submit_payment');

        $guard->guardSubmitPayment($event);
        $this->assertFalse($event->isBlocked());
    }

    public function testGuardSubmitPaymentAllowsAdminRegardlessOfStatus(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        $order = new Order();
        $order->setStatus('paid');
        $event = $this->guardEvent($order, 'paid', 'submit_payment');

        $guard->guardSubmitPayment($event);
        $this->assertFalse($event->isBlocked());
    }

    public function testGuardConfirmPaymentSkipsCheckWhenFlagInTestEnv(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects($this->never())->method('isGranted');
        $guard = new OrderWorkflowGuard($checker, true, 'test');

        $event = $this->guardEvent(new Order(), 'payment_pending', 'confirm_payment');
        $guard->guardConfirmPayment($event);
        $this->assertFalse($event->isBlocked());
    }

    public function testGuardConfirmPaymentBlocksNonAdminWhenNotSkipping(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $guard = new OrderWorkflowGuard($checker, false, 'test');

        $event = $this->guardEvent(new Order(), 'payment_pending', 'confirm_payment');
        $guard->guardConfirmPayment($event);
        $this->assertTrue($event->isBlocked());
    }

    public function testGuardStartProcessingShipCompleteBlockNonAdmin(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        $order = new Order();
        $e1 = $this->guardEvent($order, 'paid', 'start_processing');
        $guard->guardStartProcessing($e1);
        $this->assertTrue($e1->isBlocked());

        $e2 = $this->guardEvent($order, 'processing', 'ship');
        $guard->guardShip($e2);
        $this->assertTrue($e2->isBlocked());

        $e3 = $this->guardEvent($order, 'shipped', 'complete');
        $guard->guardComplete($e3);
        $this->assertTrue($e3->isBlocked());
    }

    public function testGuardCancelNonAdminBlocked(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        $order = new Order();
        $order->setStatus('new');
        $event = $this->guardEvent($order, 'new', 'cancel');
        $guard->guardCancel($event);
        $this->assertTrue($event->isBlocked());
    }

    public function testGuardCancelAdminBlocksTerminalStates(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);
        $guard = new OrderWorkflowGuard($checker, false, 'prod');

        foreach (['completed', 'cancelled', 'shipped'] as $status) {
            $order = new Order();
            $order->setStatus($status);
            $event = $this->guardEvent($order, $status, 'cancel');
            $guard->guardCancel($event);
            $this->assertTrue($event->isBlocked(), 'expected block for status ' . $status);
        }
    }

    private function guardEvent(Order $subject, string $from, string $transitionName): GuardEvent
    {
        $t = new Transition($transitionName, $from, 'next_place');

        return new GuardEvent($subject, new Marking([$from => 1]), $t);
    }
}
