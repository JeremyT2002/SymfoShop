<?php

namespace App\Tests\Workflow;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Covers OrderWorkflowGuard via real workflow + security token (GuardEvent is final).
 */
class OrderWorkflowAuthorizationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->workflow = $container->get('state_machine.order');
    }

    public function testNonAdminCannotConfirmPayment(): void
    {
        $this->setToken(new InMemoryUser('u', '', ['ROLE_USER']));
        $order = $this->persistOrder('payment_pending');

        $this->assertFalse($this->workflow->can($order, 'confirm_payment'));
    }

    public function testNonAdminCannotStartProcessing(): void
    {
        $this->setToken(new InMemoryUser('u', '', ['ROLE_USER']));
        $order = $this->persistOrder('paid');

        $this->assertFalse($this->workflow->can($order, 'start_processing'));
    }

    public function testNonAdminCannotShip(): void
    {
        $this->setToken(new InMemoryUser('u', '', ['ROLE_USER']));
        $order = $this->persistOrder('processing');

        $this->assertFalse($this->workflow->can($order, 'ship'));
    }

    public function testNonAdminCannotComplete(): void
    {
        $this->setToken(new InMemoryUser('u', '', ['ROLE_USER']));
        $order = $this->persistOrder('shipped');

        $this->assertFalse($this->workflow->can($order, 'complete'));
    }

    public function testNonAdminCannotCancel(): void
    {
        $this->setToken(new InMemoryUser('u', '', ['ROLE_USER']));
        $order = $this->persistOrder('new');

        $this->assertFalse($this->workflow->can($order, 'cancel'));
    }

    public function testAdminCannotCancelShippedOrder(): void
    {
        $this->setToken(new InMemoryUser('admin', '', ['ROLE_ADMIN']));
        $order = $this->persistOrder('shipped');

        $this->assertFalse($this->workflow->can($order, 'cancel'));
    }

    public function testAdminCannotCancelCompletedOrder(): void
    {
        $this->setToken(new InMemoryUser('admin', '', ['ROLE_ADMIN']));
        $order = $this->persistOrder('completed');

        $this->assertFalse($this->workflow->can($order, 'cancel'));
    }

    public function testAdminCanCancelFromPaid(): void
    {
        $this->setToken(new InMemoryUser('admin', '', ['ROLE_ADMIN']));
        $order = $this->persistOrder('paid');

        $this->assertTrue($this->workflow->can($order, 'cancel'));
    }

    private function setToken(InMemoryUser $user): void
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        static::getContainer()->get(TokenStorageInterface::class)->setToken($token);
    }

    private function persistOrder(string $status): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-AUTH-' . uniqid());
        $order->setEmail('auth-test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus($status);
        $order->setSubtotal(1000);
        $order->setTaxTotal(200);
        $order->setGrandTotal(1200);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(TokenStorageInterface::class)->setToken(null);
        parent::tearDown();
        $this->entityManager->close();
    }
}
