<?php

namespace App\Dashboard\Widget;

/**
 * Defines a dashboard widget type (id, template, default size, optional settings schema).
 */
final readonly class WidgetDefinition
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $template,
        public int $defaultW = 2,
        public int $defaultH = 1,
        /** @var array<string, mixed> */
        public array $defaultSettings = [],
        /** @var array<string, array{type?: string, label?: string, default?: mixed}> */
        public array $settingsSchema = [],
    ) {
    }
}
