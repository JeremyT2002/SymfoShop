<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Demo\DemoAccounts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Sign in as admin / customer" buttons on the login page of a public demo (DEMO_MODE=1).
 */
final class DemoLoginController extends AbstractController
{
    public function __construct(
        private readonly DemoAccounts $demoAccounts,
        private readonly Security $security,
    ) {
    }

    #[Route('/demo-login/{account}', name: 'demo_login', requirements: ['account' => '[a-z]+'], methods: ['POST'])]
    public function __invoke(Request $request, string $account): RedirectResponse
    {
        if (!$this->demoAccounts->has($account)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('demo-login', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->demoAccounts->ensure($account);
        $this->security->login($user, 'form_login', 'main');

        return $this->redirectToRoute($this->demoAccounts->targetRoute($account));
    }
}
