<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Order;
use App\Entity\Refund;
use App\Repository\InvoiceRepository;
use App\Service\Invoice\PdfInvoiceGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Customer e-mails for order status changes (the confirmation is sent with the invoice).
 */
final class OrderNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly PdfInvoiceGenerator $pdfGenerator,
        private readonly InvoiceRepository $invoices,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Marks the order as shipped now and tells the customer (with tracking link when known).
     */
    public function orderShipped(Order $order): void
    {
        $order->setShippedAt($order->getShippedAt() ?? new \DateTimeImmutable());

        $subject = $this->translator->trans('ui.order_mail.shipped_subject', ['%number%' => $order->getOrderNumber()], null, $order->getLocale());

        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($order->getEmail())
                    ->subject($subject)
                    ->html($this->twig->render('emails/order_shipped.html.twig', [
                        'order' => $order,
                        'locale' => $order->getLocale(),
                        'trackingUrl' => self::trackingUrl($order->getCarrier(), $order->getTrackingNumber()),
                    ]))
            );
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Shipping notification could not be sent', [
                'order_number' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tells the customer about a refund, with the credit note attached.
     */
    public function orderRefunded(Refund $refund): void
    {
        $order = $refund->getOrder();
        $locale = $order->getLocale();

        try {
            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($order->getEmail())
                ->subject($this->translator->trans('ui.refund.mail_subject', ['%number%' => $order->getOrderNumber()], null, $locale))
                ->html($this->twig->render('emails/order_refunded.html.twig', [
                    'order' => $order,
                    'refund' => $refund,
                    'locale' => $locale,
                ]));

            $pdf = $this->pdfGenerator->generateCreditNote($refund, $this->invoices->findOneByOrderId((int) $order->getId()), $locale);
            $email->attach((string) file_get_contents($pdf), 'credit_note_' . $refund->getCreditNoteNumber() . '.pdf', 'application/pdf');
            @unlink($pdf);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Refund notification could not be sent', [
                'order_number' => $order->getOrderNumber(),
                'credit_note' => $refund->getCreditNoteNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tracking page of common carriers, or null if the carrier is unknown.
     */
    public static function trackingUrl(?string $carrier, ?string $trackingNumber): ?string
    {
        if ($trackingNumber === null || trim($trackingNumber) === '') {
            return null;
        }

        $number = rawurlencode(trim($trackingNumber));

        return match (mb_strtolower(trim((string) $carrier))) {
            'dhl' => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . $number,
            'dpd' => 'https://tracking.dpd.de/status/de_DE/parcel/' . $number,
            'ups' => 'https://www.ups.com/track?tracknum=' . $number,
            'hermes' => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#' . $number,
            'gls' => 'https://gls-group.com/DE/de/paketverfolgung?match=' . $number,
            'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=' . $number,
            default => null,
        };
    }
}
