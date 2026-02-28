<?php

namespace App\Theme;

/**
 * Converts theme tokens to CSS variables and Tailwind-compatible config.
 * Supports fallbacks and accessibility-safe defaults.
 */
class ThemeTokensService
{
    private const CONFIG_PATH = __DIR__ . '/../../config/theme_tokens_default.json';

    /** @var array<string, mixed> */
    private ?array $tokens = null;

    /** @return array<string, mixed> */
    public function getTokens(): array
    {
        if ($this->tokens === null) {
            $this->tokens = $this->loadDefaultTokens();
        }
        return $this->tokens;
    }

    /**
     * Merge custom tokens (e.g. from Theme entity) over defaults.
     * @param array<string, mixed> $custom
     * @return array<string, mixed>
     */
    public function mergeTokens(array $custom): array
    {
        return $this->deepMerge($this->getTokens(), $custom);
    }

    /**
     * Generate CSS custom properties for :root.
     * Maps tokens to --token-* variables for Tailwind arbitrary values.
     */
    public function toCssVariables(array $tokens = null): string
    {
        $tokens ??= $this->getTokens();
        $lines = [':root {'];

        $this->emitColorVariables($tokens['colors'] ?? [], $lines);
        $this->emitTypographyVariables($tokens['typography'] ?? [], $lines);
        $spacing = $tokens['spacing'] ?? $tokens['layout']['spacingScale'] ?? [];
        $radius = $tokens['radius'] ?? $tokens['layout']['radius'] ?? [];
        $this->emitSpacingVariables(is_array($spacing) ? $spacing : [], $lines);
        $this->emitRadiusVariables(is_array($radius) ? $radius : [], $lines);

        $lines[] = '}';
        return implode("\n", $lines);
    }

    /**
     * Generate Tailwind theme.extend config object (JSON) for dynamic config.
     * Use with tailwind.config = { theme: { extend: ... } }
     */
    public function toTailwindConfig(array $tokens = null): array
    {
        $tokens ??= $this->getTokens();
        $config = [];

        if (isset($tokens['colors'])) {
            $config['colors'] = $this->colorsToTailwind($tokens['colors']);
        }
        if (isset($tokens['typography']['fontFamily'])) {
            $config['fontFamily'] = [];
            foreach ($tokens['typography']['fontFamily'] as $key => $stack) {
                $config['fontFamily'][$key] = is_array($stack) ? $stack : [$stack];
            }
        }
        if (isset($tokens['typography']['fontSize'])) {
            $config['fontSize'] = $tokens['typography']['fontSize'];
        }
        if (isset($tokens['typography']['fontWeight'])) {
            $config['fontWeight'] = $tokens['typography']['fontWeight'];
        }
        if (isset($tokens['spacing'])) {
            $config['spacing'] = $tokens['spacing'];
        }
        if (isset($tokens['radius'])) {
            $config['borderRadius'] = $tokens['radius'];
        }

        return $config;
    }

    /**
     * Tailwind config that references CSS variables (for runtime token injection).
     * Use when tokens are output as CSS vars - Tailwind uses var(--*) references.
     */
    public function toTailwindConfigWithVars(array $tokens = null): array
    {
        $tokens ??= $this->getTokens();
        $config = [];

        if (isset($tokens['colors'])) {
            $config['colors'] = $this->colorsToTailwindVars($tokens['colors']);
        }
        if (isset($tokens['typography']['fontFamily'])) {
            $config['fontFamily'] = [];
            foreach ($tokens['typography']['fontFamily'] as $key => $stack) {
                $config['fontFamily'][$key] = ['var(--font-' . $key . ')', ...(is_array($stack) ? array_slice($stack, 1) : ['sans-serif'])];
            }
        }
        if (isset($tokens['radius'])) {
            $config['borderRadius'] = [];
            foreach ($tokens['radius'] as $key => $val) {
                $config['borderRadius'][$key] = 'var(--radius-' . $key . ', ' . $val . ')';
            }
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private function loadDefaultTokens(): array
    {
        if (!is_readable(self::CONFIG_PATH)) {
            return $this->getFallbackTokens();
        }
        $json = file_get_contents(self::CONFIG_PATH);
        $data = json_decode($json, true);
        return is_array($data) ? $data : $this->getFallbackTokens();
    }

    /** @return array<string, mixed> */
    private function getFallbackTokens(): array
    {
        return [
            'colors' => [
                'primary' => ['500' => '#3b82f6', '600' => '#2563eb'],
                'text' => ['primary' => '#111827'],
            ],
            'typography' => [
                'fontFamily' => ['sans' => ['system-ui', 'sans-serif']],
            ],
            'radius' => ['md' => '0.5rem'],
        ];
    }

    /** @param array<string, mixed> $colors */
    private function emitColorVariables(array $colors, array &$lines): void
    {
        foreach ($colors as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $shade => $hex) {
                    $lines[] = sprintf('  --color-%s-%s: %s;', $key, $shade, $hex);
                }
            } else {
                $lines[] = sprintf('  --color-%s: %s;', $key, $value);
            }
        }
    }

    /** @param array<string, mixed> $typo */
    private function emitTypographyVariables(array $typo, array &$lines): void
    {
        if (isset($typo['fontFamily'])) {
            foreach ($typo['fontFamily'] as $key => $stack) {
                $fontStack = is_array($stack) ? implode(', ', $stack) : $stack;
                $lines[] = sprintf('  --font-%s: %s;', $key, $fontStack);
            }
        }
        foreach (['fontSize', 'fontWeight', 'lineHeight'] as $prop) {
            if (isset($typo[$prop])) {
                $varPrefix = match ($prop) {
                    'fontSize' => 'font-size',
                    'fontWeight' => 'font-weight',
                    'lineHeight' => 'line-height',
                    default => $prop,
                };
                foreach ($typo[$prop] as $key => $val) {
                    $lines[] = sprintf('  --%s-%s: %s;', $varPrefix, $key, $val);
                }
            }
        }
    }

    /** @param array<string, string> $spacing */
    private function emitSpacingVariables(array $spacing, array &$lines): void
    {
        foreach ($spacing as $key => $val) {
            $lines[] = sprintf('  --spacing-%s: %s;', $key, $val);
        }
    }

    /** @param array<string, string> $radius */
    private function emitRadiusVariables(array $radius, array &$lines): void
    {
        foreach ($radius as $key => $val) {
            $lines[] = sprintf('  --radius-%s: %s;', $key, $val);
        }
    }

    /** @param array<string, mixed> $colors */
    private function colorsToTailwind(array $colors): array
    {
        $out = [];
        foreach ($colors as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $colors */
    private function colorsToTailwindVars(array $colors): array
    {
        $out = [];
        foreach ($colors as $key => $value) {
            if (is_array($value)) {
                $out[$key] = [];
                foreach ($value as $shade => $hex) {
                    $out[$key][$shade] = 'var(--color-' . $key . '-' . $shade . ', ' . $hex . ')';
                }
            } else {
                $out[$key] = 'var(--color-' . $key . ', ' . $value . ')';
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed> */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
