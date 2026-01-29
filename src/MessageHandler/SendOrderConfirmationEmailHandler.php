<?php

namespace App\MessageHandler;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Message\SendOrderConfirmationEmail;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Email;
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
        private readonly Environment $twig
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

        try {
            $email = (new Email())
                ->from('noreply@symfoshop.local')
                ->to($order->getEmail())
                ->subject('Order Confirmation - ' . $order->getOrderNumber())
                ->html($this->twig->render('email/order_confirmation.html.twig', [
                    'order' => $order,
                    'invoice' => $invoice,
                ]));

            // Attach PDF if available
            if ($invoice->getPdfPath() && file_exists($invoice->getPdfPath())) {
                $email->attachFromPath($invoice->getPdfPath(), 'invoice_' . $invoice->getInvoiceNumber() . '.pdf', 'application/pdf');
            }

            $this->mailer->send($email);

            // Mark invoice as sent
            $invoice->setSentAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Order confirmation email sent', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'invoice_number' => $invoice->getInvoiceNumber(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw as recoverable to allow retry
            throw new RecoverableMessageHandlingException('Email transport failed: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error sending order confirmation email', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't retry on non-recoverable errors
            throw $e;
        }
    }

}

