<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Staff members turn two-factor login (TOTP app) on and off for their own account.
 */
#[Route('/admin/two-factor', name: 'admin_two_factor')]
#[IsGranted('ROLE_STAFF')]
final class TwoFactorController extends AbstractController
{
    private const SESSION_SECRET = 'two_factor_setup_secret';

    public function __construct(
        private readonly TotpAuthenticatorInterface $totp,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $user = $this->currentUser();
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->render('admin/two_factor/show.html.twig', ['enabled' => true]);
        }

        // New secret per setup attempt, kept in the session until a code confirms it
        $secret = $request->getSession()->get(self::SESSION_SECRET) ?? $this->totp->generateSecret();
        $request->getSession()->set(self::SESSION_SECRET, $secret);

        $provisioningUri = $this->withSecret($user, $secret, fn (): string => $this->totp->getQRContent($user));
        $qrCode = (new Builder(writer: new SvgWriter(), data: $provisioningUri, size: 220, margin: 0))->build();

        return $this->render('admin/two_factor/show.html.twig', [
            'enabled' => false,
            'secret' => $secret,
            'qrCode' => $qrCode->getDataUri(),
        ]);
    }

    #[Route('/enable', name: '_enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        $user = $this->currentUser();
        $secret = $request->getSession()->get(self::SESSION_SECRET);
        if (!$this->isCsrfTokenValid('two_factor_enable', (string) $request->request->get('_token')) || !\is_string($secret) || $user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('admin_two_factor');
        }

        $code = preg_replace('/\s+/', '', (string) $request->request->get('code')) ?? '';
        if (!$this->withSecret($user, $secret, fn (): bool => $this->totp->checkCode($user, $code))) {
            $this->addFlash('error', $this->translator->trans('ui.two_factor.invalid_code'));

            return $this->redirectToRoute('admin_two_factor');
        }

        $user->setTotpSecret($secret);
        $this->entityManager->flush();
        $request->getSession()->remove(self::SESSION_SECRET);
        $this->addFlash('success', $this->translator->trans('ui.two_factor.enabled'));

        return $this->redirectToRoute('admin_two_factor');
    }

    #[Route('/disable', name: '_disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('two_factor_disable', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_two_factor');
        }

        // A current code is required, so a forgotten open session cannot switch it off
        $code = preg_replace('/\s+/', '', (string) $request->request->get('code')) ?? '';
        if (!$user->isTotpAuthenticationEnabled() || !$this->totp->checkCode($user, $code)) {
            $this->addFlash('error', $this->translator->trans('ui.two_factor.invalid_code'));

            return $this->redirectToRoute('admin_two_factor');
        }

        $user->setTotpSecret(null);
        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans('ui.two_factor.disabled'));

        return $this->redirectToRoute('admin_two_factor');
    }

    /**
     * Runs $callback with the not yet confirmed secret on the user, without persisting it.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSecret(User $user, string $secret, callable $callback): mixed
    {
        $user->setTotpSecret($secret);
        try {
            return $callback();
        } finally {
            $user->setTotpSecret(null);
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
