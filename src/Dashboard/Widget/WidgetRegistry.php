<?php

namespace App\Dashboard\Widget;

/**
 * Registry of available dashboard widget types.
 */
class WidgetRegistry
{
    /** @var array<string, WidgetDefinition> */
    private array $definitions = [];

    public function __construct(DefaultWidgetDefinitionsProvider $defaultProvider)
    {
        foreach ($defaultProvider->getDefinitions() as $definition) {
            $this->definitions[$definition->id] = $definition;
        }
    }

    public function register(WidgetDefinition $definition): self
    {
        $this->definitions[$definition->id] = $definition;
        return $this;
    }

    public function get(string $id): ?WidgetDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    /** @return array<string, WidgetDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function getIds(): array
    {
        return array_keys($this->definitions);
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }
}
