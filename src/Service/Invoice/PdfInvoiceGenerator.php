<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfInvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $invoiceStoragePath
    ) {
    }

    /**
     * Generate PDF invoice and save to storage
     *
     * @return string Path to generated PDF file
     */
    public function generate(Invoice $invoice, Order $order): string
    {
        // Ensure storage directory exists
        if (!is_dir($this->invoiceStoragePath)) {
            mkdir($this->invoiceStoragePath, 0755, true);
        }

        // Render HTML template
        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice' => $invoice,
            'order' => $order,
        ]);

        // Configure PDF options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        // Generate PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF to file
        $filename = 'invoice_' . $invoice->getInvoiceNumber() . '_' . time() . '.pdf';
        $filepath = $this->invoiceStoragePath . '/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }
}

