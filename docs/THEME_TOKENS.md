# Theme Tokens & CSS Variables

Design token system for SymfoShop: color palette, typography, spacing, radius. Tokens map to CSS variables and integrate with Tailwind CSS.

## 1. Token Schema

**Location**: `config/theme_tokens_schema.json`

| Category | Keys | Description |
|----------|------|--------------|
| **colors** | primary (50–900), accent, background, surface, text.*, semantic.* | Hex colors |
| **typography** | fontFamily, fontSize, fontWeight, lineHeight | Font stacks and scales |
| **spacing** | 0, 1, 2, … 16 | Tailwind-compatible scale (1 = 0.25rem) |
| **radius** | none, sm, md, lg, xl, full | Border radius scale |

## 2. CSS Variable Strategy

Tokens are emitted as CSS custom properties on `:root`:

```css
:root {
  --color-primary-500: #3b82f6;
  --color-primary-600: #2563eb;
  --font-sans: Inter, system-ui, sans-serif;
  --font-size-base: 1rem;
  --radius-md: 0.5rem;
  --spacing-4: 1rem;
}
```

**Tailwind integration**: `tailwind.config` extends theme to use these variables:

```js
theme: {
  extend: {
    colors: {
      primary: {
        500: 'var(--color-primary-500)',
        600: 'var(--color-primary-600)',
        // ...
      }
    },
    fontFamily: {
      sans: ['var(--font-sans)', 'system-ui', 'sans-serif'],
    },
    borderRadius: {
      'theme-md': 'var(--radius-md)',
    },
  },
}
```

Templates use standard Tailwind classes: `bg-primary-500`, `text-primary-600`, `rounded-theme-md`.

## 3. Twig Integration

### Functions

| Function | Description |
|----------|-------------|
| `theme_css_vars(custom?)` | Output CSS variables (safe for `<style>`) |
| `theme_tokens(custom?)` | Get merged token array |
| `theme_tailwind_config(custom?)` | Get Tailwind extend config |
| `theme_token('colors.primary.500')` | Get single token by dot path |

### Include tokens in layout

```twig
{% include 'theme/_tokens.css.twig' %}
```

With custom tokens (e.g. from Theme entity):

```twig
{% include 'theme/_tokens.css.twig' with { custom: theme.config } %}
```

### Use token in template

```twig
<div class="bg-primary-500 text-white rounded-theme-md p-4">
  {{ theme_token('colors.primary.500') }}
</div>
```

### Inline style with variable

```twig
<div style="background-color: var(--color-primary-500);">
  Content
</div>
```

## 4. Fallback / Default Tokens

**Location**: `config/theme_tokens_default.json`

- Loaded when no custom theme is set.
- `ThemeTokensService::getFallbackTokens()` used if the JSON file is missing.
- Custom tokens are merged over defaults via `mergeTokens()`.

## 5. Accessibility Considerations

### Color contrast

- **Text on background**: `text.primary` on `background` must meet WCAG AA (4.5:1 for normal text).
- **Primary buttons**: `primary.500` or `primary.600` on white should reach 4.5:1.
- **Semantic colors**: `semantic.success`, `semantic.error`, `semantic.warning` chosen for sufficient contrast.

### Recommendations

1. **Avoid customizing text colors only**: Keep `text.primary` dark (#111827 or similar) on light backgrounds.
2. **Primary palette**: Prefer 500–700 range for interactive elements; 400+ on white for links.
3. **Focus rings**: Use `focus:ring-2 focus:ring-primary-500`; ensure ring color contrasts with background.
4. **Reduce motion**: Respect `prefers-reduced-motion` for animations (handled in Tailwind/JS).
5. **Font size**: Keep `fontSize.base` ≥ 1rem (16px) for body text.

### Validation

When building a theme editor, validate:

- Contrast ratios for text/background pairs.
- Minimum touch target size (44×44px) for interactive elements.
- Semantic colors distinguishable for color-blind users (avoid red/green only).
