<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-locale overrides for text fields, e.g. {"de": {"name": "Kopfhörer"}, "fr": {...}}.
 *
 * The regular columns hold the default-locale (en) content. Stored as JSON text with unescaped
 * unicode so the storefront search can match translated names with LIKE.
 */
trait TranslatableTrait
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $translations = null;

    /**
     * @return array<string, array<string, string>>
     */
    public function getTranslations(): array
    {
        if ($this->translations === null || $this->translations === '') {
            return [];
        }

        $decoded = json_decode($this->translations, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, string|null>> $translations
     */
    public function setTranslations(array $translations): self
    {
        $clean = [];
        foreach ($translations as $locale => $fields) {
            foreach ((array) $fields as $field => $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $clean[$locale][$field] = $value;
                }
            }
        }
        ksort($clean);

        $this->translations = $clean === [] ? null : json_encode($clean, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        return $this;
    }

    public function translationOf(string $locale, string $field): ?string
    {
        return $this->getTranslations()[$locale][$field] ?? null;
    }

    public function putTranslation(string $locale, string $field, ?string $value): self
    {
        $translations = $this->getTranslations();
        $translations[$locale][$field] = $value;

        return $this->setTranslations($translations);
    }

    /**
     * The field in the given locale, falling back to the default-locale value.
     */
    public function translated(string $field, ?string $locale): ?string
    {
        if ($locale !== null && ($value = $this->translationOf($locale, $field)) !== null) {
            return $value;
        }

        // A translated entity without translated SEO texts: let templates fall back to the
        // translated name/description instead of showing the default-locale SEO text.
        if ($locale !== null && str_starts_with($field, 'seo') && isset($this->getTranslations()[$locale])) {
            return null;
        }

        return match ($field) {
            'name' => $this->getName(),
            'description' => method_exists($this, 'getDescription') ? $this->getDescription() : null,
            'seoTitle' => $this->getSeoTitle(),
            'seoDescription' => $this->getSeoDescription(),
            default => throw new \InvalidArgumentException(sprintf('"%s" is not translatable.', $field)),
        };
    }
}
