<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * E-mail address verification via signed, expiring links.
 *
 * The link contains a fingerprint of the address it was sent to, so it stops working once the
 * customer changes their e-mail. Verifying links earlier guest orders placed with that address.
 */
final class EmailVerifier
{
    private const LINK_LIFETIME = '+48 hours';

    public function __construct(
        private readonly UriSigner $uriSigner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendVerification(User $user): void
    {
        $url = $this->uriSigner->sign(
            $this->urlGenerator->generate('verify_email', [
                'id' => $user->getId(),
                'fp' => $this->fingerprint((string) $user->getEmail()),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateTimeImmutable(self::LINK_LIFETIME)
        );

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFrom)
                ->to((string) $user->getEmail())
                ->subject($this->translator->trans('ui.verify.mail_subject'))
                ->html($this->twig->render('emails/verify_email.html.twig', ['user' => $user, 'verifyUrl' => $url]))
        );
    }

    /**
     * @return User|null the verified user, or null if the link is invalid, expired or outdated
     */
    public function verify(Request $request, int $userId): ?User
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return null;
        }

        $user = $this->userRepository->find($userId);
        if (!$user instanceof User || !hash_equals($this->fingerprint((string) $user->getEmail()), (string) $request->query->get('fp'))) {
            return null;
        }

        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $this->entityManager->flush();
        }
        $this->orderRepository->assignGuestOrdersToUser($user);

        return $user;
    }

    private function fingerprint(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }
}
