<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_config', [$this, 'getThemeConfig']),
        ];
    }

    /** @return array<string, mixed> */
    public function getThemeConfig(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }
        return $request->attributes->get('_theme_config', []);
    }
}
