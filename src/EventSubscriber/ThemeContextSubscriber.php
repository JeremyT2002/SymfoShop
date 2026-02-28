<?php

namespace App\EventSubscriber;

use App\Theme\ShopContextResolver;
use App\Theme\ThemeConfigService;
use App\Theme\ThemeResolver;
use App\Theme\ThemeTokensService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Injects resolved theme config into request for storefront templates.
 * When ?theme_preview=1 and user is admin, uses draft theme instead of published.
 */
class ThemeContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ShopContextResolver $shopContext,
        private readonly ThemeResolver $themeResolver,
        private readonly ThemeConfigService $themeConfig,
        private readonly ThemeTokensService $themeTokens,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->get('_route', '');
        if (str_starts_with($route, 'admin')) {
            return;
        }
        $shop = $this->shopContext->resolve();
        $themeConfig = $this->themeResolver->resolveConfig($shop);

        if ($request->query->getInt('theme_preview') === 1 && $this->auth->isGranted('ROLE_ADMIN')) {
            $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
            $themeConfig = $this->themeTokens->mergeTokens($theme->getConfig());
        }

        $request->attributes->set('_theme_config', $themeConfig);
    }
}
