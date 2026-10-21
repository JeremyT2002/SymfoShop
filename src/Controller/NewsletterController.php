<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Newsletter with double opt-in (sign-up → confirmation mail → confirmed).
 */
#[Route('/newsletter', name: 'newsletter_')]
final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $subscribers,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'limiter.newsletter_limiter')]
        private readonly RateLimiterFactory $newsletterLimiter,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/subscribe', name: 'subscribe', methods: ['POST'])]
    public function subscribe(Request $request, ValidatorInterface $validator): Response
    {
        $back = $this->backUrl($request);

        if (!$this->isCsrfTokenValid('newsletter_subscribe', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_csrf');

            return $this->redirect($back);
        }
        if (!$request->request->getBoolean('consent')) {
            $this->addFlash('error', 'ui.newsletter.consent_required');

            return $this->redirect($back);
        }

        $email = trim((string) $request->request->get('email', ''));
        if ($email === '' || \count($validator->validate($email, new EmailConstraint())) > 0) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_email');

            return $this->redirect($back);
        }

        if (!$this->newsletterLimiter->create($request->getClientIp() ?? 'anonymous')->consume()->isAccepted()) {
            $this->addFlash('error', 'ui.order_status.rate_limited');

            return $this->redirect($back);
        }

        $subscriber = $this->subscribers->findOneByEmail($email);
        if ($subscriber === null) {
            $subscriber = new NewsletterSubscriber($email, $request->getLocale());
            $this->entityManager->persist($subscriber);
        } elseif (!$subscriber->isActive()) {
            $subscriber->restart();
            $subscriber->setLocale($request->getLocale());
        }
        $this->entityManager->flush();

        if (!$subscriber->isActive()) {
            $this->sendConfirmation($subscriber);
        }

        // Same answer whether or not the address was already subscribed
        $this->addFlash('success', 'ui.newsletter.check_inbox');

        return $this->redirect($back);
    }

    #[Route('/confirm/{token}', name: 'confirm', methods: ['GET'], requirements: ['token' => '[a-f0-9]{48}'])]
    public function confirm(string $token): Response
    {
        $subscriber = $this->subscribers->findOneByToken($token);
        if ($subscriber === null) {
            $this->addFlash('error', 'ui.newsletter.invalid_link');

            return $this->redirectToRoute('catalog_home');
        }

        $subscriber->confirm();
        $this->entityManager->flush();
        $this->addFlash('success', 'ui.newsletter.confirmed');

        return $this->redirectToRoute('catalog_home');
    }

    #[Route('/unsubscribe/{token}', name: 'unsubscribe', methods: ['GET'], requirements: ['token' => '[a-f0-9]{48}'])]
    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->subscribers->findOneByToken($token);
        if ($subscriber !== null) {
            $subscriber->unsubscribe();
            $this->entityManager->flush();
        }
        $this->addFlash('success', 'ui.newsletter.unsubscribed');

        return $this->redirectToRoute('catalog_home');
    }

    private function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($subscriber->getEmail())
                    ->subject($this->translator->trans('ui.newsletter.mail_subject', [], null, $subscriber->getLocale()))
                    ->html($this->renderView('emails/newsletter_confirm.html.twig', ['subscriber' => $subscriber]))
            );
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Newsletter confirmation could not be sent', ['error' => $e->getMessage()]);
        }
    }

    /** Return to the page the form was on (same host only) */
    private function backUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        $parts = parse_url($referer);

        return \is_array($parts) && ($parts['host'] ?? null) === $request->getHost()
            ? ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '')
            : $this->generateUrl('catalog_home');
    }
}
