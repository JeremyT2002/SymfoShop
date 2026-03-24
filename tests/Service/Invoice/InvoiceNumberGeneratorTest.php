<?php

namespace App\Tests\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Service\Invoice\InvoiceNumberGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InvoiceNumberGeneratorTest extends KernelTestCase
{
    public function testGenerateIncrementsWithinYear(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var InvoiceNumberGenerator $gen */
        $gen = $container->get(InvoiceNumberGenerator::class);

        $order = new Order();
        $order->setOrderNumber('ORD-ING-' . uniqid());
        $order->setEmail('inv-gen@example.com');
        $order->setCurrency('EUR');
        $order->setStatus('paid');
        $order->setSubtotal(100);
        $order->setTaxTotal(20);
        $order->setGrandTotal(120);
        $em->persist($order);
        $em->flush();

        $year = date('Y');
        $prefix = 'INV-' . $year . '-';
        $conn = $em->getConnection();
        $rawMax = $conn->fetchOne(
            'SELECT MAX(CAST(SUBSTR(invoice_number, -4) AS INTEGER)) FROM invoice WHERE invoice_number LIKE ?',
            [$prefix . '%']
        );
        $lastSeq = $rawMax !== null && $rawMax !== false ? (int) $rawMax : 0;
        $seedSeq = $lastSeq + 1;

        $inv = new Invoice();
        $inv->setOrder($order);
        $inv->setInvoiceNumber($prefix . str_pad((string) $seedSeq, 4, '0', STR_PAD_LEFT));
        $em->persist($inv);
        $em->flush();

        $next = $gen->generate();
        $expected = $prefix . str_pad((string) ($seedSeq + 1), 4, '0', STR_PAD_LEFT);
        $this->assertSame($expected, $next);

        $em->close();
    }
}
