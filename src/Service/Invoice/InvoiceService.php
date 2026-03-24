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
        private readonly MessageBusInterface $messageBus,
        private readonly string $invoiceStoragePath,
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

    /**
     * Render the invoice PDF again (e.g. after template/theme changes) and update storage path.
     * Removes the previous file only if it lives under the configured invoice directory.
     */
    public function regenerateInvoicePdf(Invoice $invoice): void
    {
        $order = $invoice->getOrder();
        $oldPath = $invoice->getPdfPath();

        $newPath = $this->pdfInvoiceGenerator->generate($invoice, $order);

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $newPath && is_file($oldPath) && $this->isPathUnderInvoiceStorage($oldPath)) {
            @unlink($oldPath);
        }

        $invoice->setPdfPath($newPath);
        $this->entityManager->flush();
    }

    /**
     * Regenerate PDF for every stored invoice (same numbers, new files).
     *
     * @return array{success: int, failed: int, errors: list<string>}
     */
    public function regenerateAllInvoicePdfs(): array
    {
        $invoices = $this->invoiceRepository->findBy([], ['id' => 'ASC']);
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($invoices as $invoice) {
            try {
                $this->regenerateInvoicePdf($invoice);
                ++$success;
            } catch (\Throwable $e) {
                ++$failed;
                $errors[] = $invoice->getInvoiceNumber() . ': ' . $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function isPathUnderInvoiceStorage(string $path): bool
    {
        $base = realpath($this->invoiceStoragePath);
        $file = realpath($path);
        if ($base === false || $file === false) {
            return false;
        }
        $base = str_replace('\\', '/', $base);
        $file = str_replace('\\', '/', $file);

        return $file === $base || str_starts_with($file, $base . '/');
    }
}

