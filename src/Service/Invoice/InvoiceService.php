<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Message\SendOrderConfirmationEmail;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceNumberGenerator $invoiceNumberGenerator,
        private readonly PdfInvoiceGenerator $pdfInvoiceGenerator,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * Create invoice and generate PDF for a paid order
     */
    public function createInvoiceForOrder(Order $order): Invoice
    {
        // Check if invoice already exists
        $existingInvoice = $this->invoiceRepository->findOneByOrderId($order->getId());
        if ($existingInvoice) {
            return $existingInvoice;
        }

        // Only create invoice for paid orders
        if ($order->getStatus() !== 'paid') {
            throw new \RuntimeException('Invoice can only be created for paid orders');
        }

        $this->entityManager->beginTransaction();

        try {
            // Create invoice
            $invoice = new Invoice();
            $invoice->setOrder($order);
            $invoice->setInvoiceNumber($this->invoiceNumberGenerator->generate());

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            // Generate PDF
            $pdfPath = $this->pdfInvoiceGenerator->generate($invoice, $order);
            $invoice->setPdfPath($pdfPath);

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Dispatch email message asynchronously
            $this->messageBus->dispatch(new SendOrderConfirmationEmail(
                $order->getId(),
                $invoice->getInvoiceNumber()
            ));

            return $invoice;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw new \RuntimeException('Failed to create invoice: ' . $e->getMessage(), 0, $e);
        }
    }
}

