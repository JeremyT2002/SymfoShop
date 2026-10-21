<?php

namespace App\Tests\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->orderRepository = $container->get(OrderRepository::class);
    }

    public function testFindByUserOrdersByCreatedAtDesc(): void
    {
        $user = $this->createUser();
        $email = (string) $user->getEmail();

        $older = $this->makeOrder($email, 'ORD-OLD-' . uniqid(), $user);
        $older->setCreatedAt(new \DateTimeImmutable('-2 hours'));
        $this->entityManager->persist($older);
        $this->entityManager->flush();

        $newer = $this->makeOrder($email, 'ORD-NEW-' . uniqid(), $user);
        $newer->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->persist($newer);
        $this->entityManager->flush();

        $list = $this->orderRepository->findByUser($user, 20);

        $this->assertCount(2, $list);
        $this->assertSame($newer->getId(), $list[0]->getId());
        $this->assertSame($older->getId(), $list[1]->getId());
    }

    public function testFindByUserRespectsLimit(): void
    {
        $user = $this->createUser();
        $email = (string) $user->getEmail();
        for ($i = 0; $i < 3; ++$i) {
            $this->entityManager->persist($this->makeOrder($email, 'ORD-LIM-' . $i . '-' . uniqid(), $user));
        }
        $this->entityManager->flush();

        $list = $this->orderRepository->findByUser($user, 2);
        $this->assertCount(2, $list);
    }

    public function testGetSalesOverTimeReturnsStructure(): void
    {
        $stats = $this->orderRepository->getSalesOverTime(7);

        $this->assertArrayHasKey('labels', $stats);
        $this->assertArrayHasKey('orderCounts', $stats);
        $this->assertArrayHasKey('revenue', $stats);
        $this->assertCount(7, $stats['labels']);
        $this->assertCount(7, $stats['orderCounts']);
        $this->assertCount(7, $stats['revenue']);
    }

    public function testGetOrdersByStatusReturnsRows(): void
    {
        $email = 'repo-status-' . uniqid('', true) . '@example.com';
        $a = $this->makeOrder($email, 'ORD-ST-A-' . uniqid());
        $a->setStatus('new');
        $b = $this->makeOrder($email, 'ORD-ST-B-' . uniqid());
        $b->setStatus('new');
        $c = $this->makeOrder($email, 'ORD-ST-C-' . uniqid());
        $c->setStatus('paid');
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->persist($c);
        $this->entityManager->flush();

        $rows = $this->orderRepository->getOrdersByStatus();
        $this->assertNotEmpty($rows);
        $newRow = null;
        foreach ($rows as $row) {
            if ($row['status'] === 'new') {
                $newRow = $row;
                break;
            }
        }
        $this->assertNotNull($newRow);
        $this->assertGreaterThanOrEqual(2, $newRow['count']);
    }

    private function makeOrder(string $email, string $orderNumber, ?User $user = null): Order
    {
        $order = new Order();
        $order->setOrderNumber($orderNumber);
        $order->setEmail($email);
        $order->setUser($user);
        $order->setCurrency('EUR');
        $order->setStatus('new');
        $order->setSubtotal(100);
        $order->setTaxTotal(20);
        $order->setGrandTotal(120);

        return $order;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('repo-order-' . uniqid('', true) . '@example.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
