<?php

namespace App\Tests\Repository;

use App\Entity\Invoice;
use App\Entity\Order;
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

    public function testFindByOrderEmailAndFindOneForUser(): void
    {
        $email = 'inv-' . uniqid() . '@example.com';
        $order = new Order();
        $order->setOrderNumber('ORD-INV-' . uniqid());
        $order->setEmail($email);
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

        $list = $this->repo->findByOrderEmail($email);
        $this->assertCount(1, $list);
        $this->assertSame($inv->getId(), $list[0]->getId());

        $one = $this->repo->findOneForUserByInvoiceNumber($inv->getInvoiceNumber(), $email);
        $this->assertNotNull($one);
        $this->assertSame($inv->getId(), $one->getId());

        $this->assertNull($this->repo->findOneForUserByInvoiceNumber($inv->getInvoiceNumber(), 'other@example.com'));

        $byNum = $this->repo->findOneByInvoiceNumber($inv->getInvoiceNumber());
        $this->assertNotNull($byNum);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
