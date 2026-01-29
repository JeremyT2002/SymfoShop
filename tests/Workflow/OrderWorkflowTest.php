<?php

namespace App\Tests\Workflow;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductStatus;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

class OrderWorkflowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->workflow = $container->get('workflow.order');
    }

    public function testInitialStateIsNew(): void
    {
        $order = $this->createTestOrder();
        $this->assertEquals('new', $order->getStatus());
    }

    public function testSubmitPaymentTransition(): void
    {
        $order = $this->createTestOrder();
        $this->assertEquals('new', $order->getStatus());

        $this->assertTrue($this->workflow->can($order, 'submit_payment'));
        $this->workflow->apply($order, 'submit_payment');
        $this->entityManager->flush();

        $this->assertEquals('payment_pending', $order->getStatus());
    }

    public function testConfirmPaymentTransition(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'confirm_payment'));
        $this->workflow->apply($order, 'confirm_payment');
        $this->entityManager->flush();

        $this->assertEquals('paid', $order->getStatus());
    }

    public function testStartProcessingTransition(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('paid');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'start_processing'));
        $this->workflow->apply($order, 'start_processing');
        $this->entityManager->flush();

        $this->assertEquals('processing', $order->getStatus());
    }

    public function testShipTransition(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('processing');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'ship'));
        $this->workflow->apply($order, 'ship');
        $this->entityManager->flush();

        $this->assertEquals('shipped', $order->getStatus());
    }

    public function testCompleteTransition(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('shipped');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'complete'));
        $this->workflow->apply($order, 'complete');
        $this->entityManager->flush();

        $this->assertEquals('completed', $order->getStatus());
    }

    public function testCancelTransitionFromNew(): void
    {
        $order = $this->createTestOrder();
        $this->assertEquals('new', $order->getStatus());

        $this->assertTrue($this->workflow->can($order, 'cancel'));
        $this->workflow->apply($order, 'cancel');
        $this->entityManager->flush();

        $this->assertEquals('cancelled', $order->getStatus());
    }

    public function testCancelTransitionFromPaymentPending(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('payment_pending');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'cancel'));
        $this->workflow->apply($order, 'cancel');
        $this->entityManager->flush();

        $this->assertEquals('cancelled', $order->getStatus());
    }

    public function testCancelTransitionFromPaid(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('paid');
        $this->entityManager->flush();

        $this->assertTrue($this->workflow->can($order, 'cancel'));
        $this->workflow->apply($order, 'cancel');
        $this->entityManager->flush();

        $this->assertEquals('cancelled', $order->getStatus());
    }

    public function testCannotCancelFromCompleted(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('completed');
        $this->entityManager->flush();

        $this->assertFalse($this->workflow->can($order, 'cancel'));
    }

    public function testCannotCancelFromShipped(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('shipped');
        $this->entityManager->flush();

        $this->assertFalse($this->workflow->can($order, 'cancel'));
    }

    public function testFullWorkflowPath(): void
    {
        $order = $this->createTestOrder();
        $this->assertEquals('new', $order->getStatus());

        // Submit payment
        $this->workflow->apply($order, 'submit_payment');
        $this->entityManager->flush();
        $this->assertEquals('payment_pending', $order->getStatus());

        // Confirm payment
        $this->workflow->apply($order, 'confirm_payment');
        $this->entityManager->flush();
        $this->assertEquals('paid', $order->getStatus());

        // Start processing
        $this->workflow->apply($order, 'start_processing');
        $this->entityManager->flush();
        $this->assertEquals('processing', $order->getStatus());

        // Ship
        $this->workflow->apply($order, 'ship');
        $this->entityManager->flush();
        $this->assertEquals('shipped', $order->getStatus());

        // Complete
        $this->workflow->apply($order, 'complete');
        $this->entityManager->flush();
        $this->assertEquals('completed', $order->getStatus());
    }

    public function testInvalidTransitions(): void
    {
        $order = $this->createTestOrder();

        // Cannot confirm payment from 'new'
        $this->assertFalse($this->workflow->can($order, 'confirm_payment'));

        // Cannot start processing from 'new'
        $this->assertFalse($this->workflow->can($order, 'start_processing'));

        // Cannot ship from 'new'
        $this->assertFalse($this->workflow->can($order, 'ship'));

        // Cannot complete from 'new'
        $this->assertFalse($this->workflow->can($order, 'complete'));
    }

    private function createTestOrder(): Order
    {
        $product = new Product();
        $product->setName('Test Product');
        $product->setSlug('test-product-' . uniqid());
        $product->setStatus(ProductStatus::ACTIVE);
        $product->setTaxClass('standard');

        $variant = new ProductVariant();
        $variant->setProduct($product);
        $variant->setSku('SKU-' . uniqid());
        $variant->setPriceAmount(1000);
        $variant->setCurrency('EUR');
        $variant->setAttributes([]);

        $order = new Order();
        $order->setOrderNumber('ORD-TEST-' . uniqid());
        $order->setEmail('test@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(1000);
        $order->setTaxTotal(200);
        $order->setGrandTotal(1200);

        $orderItem = new OrderItem();
        $orderItem->setSku($variant->getSku());
        $orderItem->setNameSnapshot($product->getName());
        $orderItem->setQuantity(1);
        $orderItem->setUnitPriceAmount(1000);
        $orderItem->setTaxRate('0.2000');
        $orderItem->setTotalAmount(1000);
        $orderItem->setOrder($order);

        $this->entityManager->persist($product);
        $this->entityManager->persist($variant);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}

