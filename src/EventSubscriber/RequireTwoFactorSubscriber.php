<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * With ADMIN_REQUIRE_2FA=1, staff without two-factor login are sent to the setup page
 * before they can use the admin area.
 */
final class RequireTwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%app.admin_require_2fa%')]
        private readonly bool $required,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the firewall (priority 8), so the user is known
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->required || !$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/admin') || str_starts_with($path, '/admin/two-factor')) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->isStaff() && !$user->isTotpAuthenticationEnabled()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_two_factor')));
        }
    }
}
