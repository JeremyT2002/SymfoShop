<?php

namespace App\MessageHandler;

use App\Message\SendOrderConfirmationEmail;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
class SendOrderConfirmationEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function __invoke(SendOrderConfirmationEmail $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());

        if (!$order) {
            $this->logger->error('Order not found for email sending', [
                'order_id' => $message->getOrderId(),
            ]);

            return;
        }

        $invoice = $this->invoiceRepository->findOneByInvoiceNumber($message->getInvoiceNumber());

        if (!$invoice) {
            $this->logger->error('Invoice not found for email sending', [
                'invoice_number' => $message->getInvoiceNumber(),
            ]);

            return;
        }

        $locale = $order->getLocale();
        if ($locale === '') {
            $locale = $this->defaultLocale;
        }

        try {
            $this->localeSwitcher->runWithLocale($locale, function () use ($order, $invoice, $locale): void {
                $subject = $this->translator->trans('email.order_confirmation.subject', [
                    '%order_number%' => $order->getOrderNumber(),
                ]);
                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($order->getEmail())
                    ->subject($subject)
                    ->html($this->twig->render('email/order_confirmation.html.twig', [
                        'order' => $order,
                        'invoice' => $invoice,
                    ]));

                if ($invoice->getPdfPath() && file_exists($invoice->getPdfPath())) {
                    $attachName = $this->translator->trans('email.order_confirmation.attachment_prefix')
                        . '_' . $invoice->getInvoiceNumber() . '.pdf';
                    $email->attachFromPath($invoice->getPdfPath(), $attachName, 'application/pdf');
                }

                $this->mailer->send($email);

                $invoice->setSentAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->logger->info('Order confirmation email sent', [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'invoice_number' => $invoice->getInvoiceNumber(),
                    'locale' => $locale,
                ]);
            });
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);

            throw new RecoverableMessageHandlingException('Email transport failed: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error sending order confirmation email', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
