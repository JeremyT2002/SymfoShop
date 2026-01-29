<?php

namespace App\Service\Invoice;

use App\Repository\InvoiceRepository;

class InvoiceNumberGenerator
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    /**
     * Generate year-based invoice number
     * Format: INV-YYYY-NNNN (e.g., INV-2025-0001)
     */
    public function generate(): string
    {
        $year = date('Y');
        $prefix = 'INV-' . $year . '-';

        // Find the highest invoice number for this year
        $lastInvoice = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->getInvoiceNumber(), -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

