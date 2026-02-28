<?php

namespace App\Twig;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeSectionExtension extends AbstractExtension
{
    private const SECTION_TEMPLATES = [
        'hero' => 'theme/sections/_hero.html.twig',
        'benefits' => 'theme/sections/_benefits.html.twig',
        'featured_products' => 'theme/sections/_featured_products.html.twig',
        'category_grid' => 'theme/sections/_category_grid.html.twig',
        'testimonials' => 'theme/sections/_testimonials.html.twig',
        'newsletter' => 'theme/sections/_newsletter.html.twig',
        'rich_text' => 'theme/sections/_rich_text.html.twig',
    ];

    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_section', [$this, 'renderSection'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array{type: string, id?: string, enabled?: bool, settings?: array<string, mixed>} $section
     * @param array<string, mixed> $context
     */
    public function renderSection(array $section, array $context = []): string
    {
        if (($section['enabled'] ?? true) === false) {
            return '';
        }
        $type = $section['type'] ?? 'rich_text';
        $template = self::SECTION_TEMPLATES[$type] ?? self::SECTION_TEMPLATES['rich_text'];
        $vars = array_merge($context, [
            'section' => $section,
            'settings' => $section['settings'] ?? [],
        ]);
        return $this->twig->render($template, $vars);
    }
}
