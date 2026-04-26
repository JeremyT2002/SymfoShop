<?php

namespace App\Controller\Catalog;

use App\Entity\StockNotification;
use App\Entity\User;
use App\Repository\ProductVariantRepository;
use App\Repository\StockNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class StockNotificationController extends AbstractController
{
    public function __construct(
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly StockNotificationRepository $stockNotificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire(service: 'limiter.stock_notification_guest_limiter')]
        private readonly RateLimiterFactory $guestLimiter
    ) {
    }

    #[Route('/stock-notifications/subscribe', name: 'stock_notification_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): RedirectResponse
    {
        $variantId = $request->request->getInt('variant_id');
        $variant = $this->productVariantRepository->find($variantId);
        if ($variant === null) {
            throw $this->createNotFoundException('Variant not found');
        }

        if (!$this->isCsrfTokenValid('stock_notification_' . $variantId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'stock_notification.flash.invalid_csrf');
            return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
        }

        $user = $this->getUser();
        $email = $user instanceof User ? ($user->getEmail() ?? '') : trim((string) $request->request->get('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'stock_notification.flash.invalid_email');
            return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
        }
        if ($this->stockNotificationRepository->existsOpenForVariantAndEmail($variant, $email)) {
            $this->addFlash('info', 'stock_notification.flash.already_subscribed');
            return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
        }

        $notification = (new StockNotification())
            ->setProductVariant($variant)
            ->setEmail($email);

        if ($user instanceof User) {
            $notification->setUser($user)
                ->setConfirmedAt(new \DateTimeImmutable());
        } else {
            if (!$request->request->getBoolean('privacy_consent')) {
                $this->addFlash('error', 'stock_notification.flash.privacy_required');
                return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
            }
            $ip = $request->getClientIp() ?? 'unknown';
            $limit = $this->guestLimiter->create($ip)->consume(1);
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'stock_notification.flash.rate_limited');
                return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
            }
            $token = bin2hex(random_bytes(32));
            $notification->setConfirmationToken($token)
                ->setTokenExpiresAt((new \DateTimeImmutable())->modify('+1 day'));

            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to($email)
                    ->subject($this->translator->trans('email.stock_notification.confirm_title'))
                    ->html($this->twig->render('emails/stock_notification/confirm.html.twig', [
                        'confirmUrl' => $this->generateUrl('stock_notification_confirm', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
                        'product' => $variant->getProduct(),
                    ]))
            );
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        $this->addFlash('success', $user instanceof User ? 'stock_notification.flash.subscribed' : 'stock_notification.flash.confirm_email_sent');
        return $this->redirectToRoute('catalog_product', ['slug' => $variant->getProduct()->getSlug()]);
    }

    #[Route('/stock-notifications/confirm/{token}', name: 'stock_notification_confirm', methods: ['GET'])]
    public function confirm(string $token): RedirectResponse
    {
        $notification = $this->stockNotificationRepository->findOneByConfirmationToken($token);
        if ($notification === null || $notification->getTokenExpiresAt() === null || $notification->getTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'stock_notification.flash.invalid_token');
            return $this->redirectToRoute('catalog_home');
        }

        $notification->setConfirmedAt(new \DateTimeImmutable())
            ->setConfirmationToken(null)
            ->setTokenExpiresAt(null);
        $this->entityManager->flush();
        $this->addFlash('success', 'stock_notification.flash.confirmed');

        return $this->redirectToRoute('catalog_product', ['slug' => $notification->getProductVariant()?->getProduct()->getSlug()]);
    }
}

