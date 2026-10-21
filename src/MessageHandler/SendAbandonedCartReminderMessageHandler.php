<?php

namespace App\MessageHandler;

use App\Message\SendAbandonedCartReminderMessage;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
class SendAbandonedCartReminderMessageHandler
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom
    ) {
    }

    public function __invoke(SendAbandonedCartReminderMessage $message): void
    {
        $cart = $this->cartRepository->find($message->getCartId());
        if ($cart === null || $cart->getUser() === null || !$cart->getUser()->isMarketingOptIn() || $cart->getReminderSentAt() !== null) {
            return;
        }

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFrom)
                ->to((string) $cart->getUser()->getEmail())
                ->subject($this->translator->trans('email.abandoned_cart.subject'))
                ->html($this->twig->render('emails/abandoned_cart/reminder.html.twig', [
                    'cart' => $cart,
                    'user' => $cart->getUser(),
                    'optOutUrl' => $this->uriSigner->sign($this->urlGenerator->generate(
                        'account_marketing_opt_out',
                        ['id' => $cart->getUser()->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    )),
                ]))
        );

        $cart->setReminderSentAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}

