<?php

namespace App\MessageHandler;

use App\Message\ProcessStockNotificationsMessage;
use App\Repository\ProductVariantRepository;
use App\Repository\StockNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
class ProcessStockNotificationsMessageHandler
{
    public function __construct(
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly StockNotificationRepository $stockNotificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom
    ) {
    }

    public function __invoke(ProcessStockNotificationsMessage $message): void
    {
        $variant = $this->productVariantRepository->find($message->getVariantId());
        if ($variant === null) {
            return;
        }

        $notifications = $this->stockNotificationRepository->findOpenForVariant($variant);
        foreach ($notifications as $notification) {
            try {
                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($notification->getEmail())
                    ->subject($this->translator->trans('email.stock_notification.available_subject', [
                        '%product%' => $variant->getProduct()->getName(),
                    ]))
                    ->html($this->twig->render('emails/stock_notification/available.html.twig', [
                        'variant' => $variant,
                        'product' => $variant->getProduct(),
                    ]));
                $this->mailer->send($email);
                $notification->setNotifiedAt(new \DateTimeImmutable());
            } catch (\Throwable $e) {
                $this->logger->error('Failed sending stock notification', ['error' => $e->getMessage()]);
            }
        }

        $this->entityManager->flush();
    }
}

