<?php

namespace App\Dashboard\Widget;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Renders a widget template with the given data and settings.
 */
class WidgetRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly WidgetRegistry $registry,
    ) {
    }

    /**
     * @param array<string, mixed> $data   Variables to pass to the template (e.g. count, orders)
     * @param array<string, mixed> $settings Widget instance settings (e.g. limit)
     */
    public function render(string $widgetTypeId, array $data = [], array $settings = []): string
    {
        $definition = $this->registry->get($widgetTypeId);
        if ($definition === null) {
            return '';
        }
        $vars = array_merge($definition->defaultSettings, $settings, $data);
        return $this->twig->render($definition->template, $vars);
    }
}
