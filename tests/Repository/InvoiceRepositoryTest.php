<?php

namespace App\Tests\Repository;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InvoiceRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InvoiceRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Invoice::class);
    }

    public function testFindByUserAndFindOneForUser(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $order = new Order();
        $order->setOrderNumber('ORD-INV-' . uniqid());
        $order->setEmail((string) $owner->getEmail());
        $order->setUser($owner);
        $order->setCurrency('EUR');
        $order->setStatus('paid');
        $order->setSubtotal(100);
        $order->setTaxTotal(20);
        $order->setGrandTotal(120);
        $this->em->persist($order);
        $this->em->flush();

        $inv = new Invoice();
        $inv->setOrder($order);
        $inv->setInvoiceNumber('INV-' . uniqid());
        $this->em->persist($inv);
        $this->em->flush();

        $list = $this->repo->findByUser($owner);
        $this->assertCount(1, $list);
        $this->assertSame($inv->getId(), $list[0]->getId());

        $one = $this->repo->findOneForUserByInvoiceNumber($inv->getInvoiceNumber(), $owner);
        $this->assertNotNull($one);
        $this->assertSame($inv->getId(), $one->getId());

        $this->assertNull($this->repo->findOneForUserByInvoiceNumber($inv->getInvoiceNumber(), $stranger));
        $this->assertSame([], $this->repo->findByUser($stranger));

        $byNum = $this->repo->findOneByInvoiceNumber($inv->getInvoiceNumber());
        $this->assertNotNull($byNum);
    }

    public function testGuestOrderWithSameEmailIsNotExposed(): void
    {
        $user = $this->createUser();

        // Guest order placed with the address the account later registered with
        $order = new Order();
        $order->setOrderNumber('ORD-GUEST-' . uniqid());
        $order->setEmail((string) $user->getEmail());
        $order->setCurrency('EUR');
        $order->setStatus('paid');
        $order->setSubtotal(100);
        $order->setTaxTotal(20);
        $order->setGrandTotal(120);
        $this->em->persist($order);

        $inv = new Invoice();
        $inv->setOrder($order);
        $inv->setInvoiceNumber('INV-G-' . uniqid());
        $this->em->persist($inv);
        $this->em->flush();

        $this->assertSame([], $this->repo->findByUser($user));
        $this->assertNull($this->repo->findOneForUserByInvoiceNumber($inv->getInvoiceNumber(), $user));
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('inv-' . uniqid('', true) . '@example.com');
        $user->setPassword('password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
