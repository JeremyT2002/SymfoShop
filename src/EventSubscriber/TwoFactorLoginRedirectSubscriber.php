<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * After the password step of a two-factor account, go straight to the code form –
 * otherwise the user lands on the (public) start page and looks logged out.
 */
final class TwoFactorLoginRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the login's own success handler has set its response
        return [LoginSuccessEvent::class => ['onLoginSuccess', -64]];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('2fa_login')));
        }
    }
}
