<?php

namespace App\Twig;

use App\Theme\ThemeTokensService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class ThemeTokensExtension extends AbstractExtension
{
    public function __construct(
        private readonly ThemeTokensService $themeTokens,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_css_vars', [$this, 'getCssVariables'], ['is_safe' => ['css']]),
            new TwigFunction('theme_tokens', [$this, 'getTokens']),
            new TwigFunction('theme_tailwind_config', [$this, 'getTailwindConfig']),
            new TwigFunction('theme_token', [$this, 'getToken']),
        ];
    }

    public function getCssVariables(?array $customTokens = null): string
    {
        $tokens = $customTokens !== null
            ? $this->themeTokens->mergeTokens($customTokens)
            : $this->themeTokens->getTokens();
        return $this->themeTokens->toCssVariables($tokens);
    }

    /** @return array<string, mixed> */
    public function getTokens(?array $customTokens = null): array
    {
        return $customTokens !== null
            ? $this->themeTokens->mergeTokens($customTokens)
            : $this->themeTokens->getTokens();
    }

    /** @return array<string, mixed> */
    public function getTailwindConfig(?array $customTokens = null): array
    {
        $tokens = $customTokens !== null
            ? $this->themeTokens->mergeTokens($customTokens)
            : $this->themeTokens->getTokens();
        return $this->themeTokens->toTailwindConfig($tokens);
    }

    /**
     * Get a single token value by path, e.g. theme_token('colors.primary.500')
     */
    public function getToken(string $path, ?array $customTokens = null): mixed
    {
        $tokens = $this->getTokens($customTokens);
        $keys = explode('.', $path);
        $current = $tokens;
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }
}
