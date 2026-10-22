<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Service\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/verify-email/{id}', name: 'verify_email', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function verify(int $id, Request $request): Response
    {
        $user = $this->emailVerifier->verify($request, $id);
        if ($user === null) {
            $this->addFlash('error', 'ui.verify.flash_invalid');

            return $this->redirectToRoute($this->getUser() ? 'account_dashboard' : 'login');
        }

        $this->addFlash('success', 'ui.verify.flash_verified');

        return $this->redirectToRoute($this->getUser() ? 'account_dashboard' : 'login');
    }

    #[Route('/verify-email/resend', name: 'verify_email_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resend(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('verify_email_resend', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_csrf');

            return $this->redirectToRoute('account_dashboard');
        }

        if (!$user->isVerified()) {
            $this->emailVerifier->sendVerification($user);
            $this->addFlash('success', 'ui.verify.flash_sent');
        }

        return $this->redirectToRoute('account_dashboard');
    }
}
