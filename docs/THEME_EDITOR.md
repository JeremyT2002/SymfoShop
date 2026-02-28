# Theme Editor - Data Model & Architecture

## Overview

The Theme Editor stores theme configuration as JSON, supports draft/published states, versioning with rollback, and is prepared for multi-tenant use (multiple shops).

## 1. Doctrine Entities & Relations

```
Shop (1) ──────< (N) Theme
                      │
                      └──────< (N) ThemeRevision
```

### Shop
- **Purpose**: Multi-tenant root. One shop per tenant; `shop_id` nullable for single-tenant (global themes).
- **Fields**: `id`, `name`, `slug`, `is_active`, `created_at`
- **Relations**: OneToMany → Theme

### Theme
- **Purpose**: Active theme configuration for a shop.
- **Fields**:
  - `shop_id` (nullable) – FK to Shop
  - `name`, `slug` – unique per shop via `(shop_id, slug)`
  - `status` – `draft` | `published`
  - `config` – JSON (colors, typography, layout, components, customCss)
  - `version` – integer, incremented on publish
  - `created_at`, `updated_at`
- **Relations**: ManyToOne → Shop, OneToMany → ThemeRevision

### ThemeRevision
- **Purpose**: Immutable snapshot for versioning and rollback.
- **Fields**:
  - `theme_id` – FK to Theme
  - `config` – JSON snapshot
  - `version` – revision number
  - `status` – `draft` | `published`
  - `published_at` – when published (nullable for drafts)
  - `comment` – optional change note
  - `created_by_id` – FK to User (nullable)
  - `created_at`
- **Relations**: ManyToOne → Theme, ManyToOne → User

## 2. Example Theme Config JSON

See `config/theme_example.json`:

```json
{
  "meta": { "schemaVersion": "1.0", "name": "Default Theme" },
  "colors": {
    "primary": { "50": "#eff6ff", "500": "#3b82f6", "900": "#1e3a8a" },
    "accent": "#22c55e",
    "background": "#f9fafb",
    "text": { "primary": "#111827", "secondary": "#6b7280" }
  },
  "typography": {
    "fontFamily": { "sans": ["Inter", "system-ui", "sans-serif"] },
    "fontSize": { "base": "1rem", "2xl": "1.5rem" }
  },
  "layout": {
    "container": { "maxWidth": "1280px" },
    "header": { "sticky": true }
  },
  "components": {
    "button": { "borderRadius": "0.5rem" },
    "card": { "borderRadius": "0.75rem" }
  },
  "customCss": ""
}
```

## 3. Migration Strategy

- **Migration**: `Version20260226120000` creates `shop`, `theme`, `theme_revision`.
- **SQLite**: Uses `CLOB` for JSON columns.
- **PostgreSQL**: Uses `JSON` type.
- **Single-tenant**: Create one default Shop; themes use `shop_id` = that shop.
- **Multi-tenant**: One Shop per tenant; themes scoped by `shop_id`.

## 4. Revision Storage & Rollback

**Creating a revision** (on save/publish):

1. Create `ThemeRevision` with current `Theme::config`, `Theme::version`, `Theme::status`.
2. Set `created_by` to current user.
3. Add to `Theme::revisions` collection.

**Rollback**:

1. Load `ThemeRevision` by `theme_id` and `version`.
2. Copy `ThemeRevision::config` to `Theme::config`.
3. Create a new `ThemeRevision` (rollback is a new revision).
4. Increment `Theme::version`.

**Query revisions**:

```php
$revisions = $themeRevisionRepository->findByTheme($theme, limit: 20);
```

## 5. Validation Strategy

- **Constraint**: `App\Theme\ThemeConfig` on `Theme::$config`.
- **Validator**: `ThemeConfigValidator` – checks:
  - Value is array
  - Top-level keys in allowed list: `meta`, `colors`, `typography`, `layout`, `components`, `customCss`
  - `customCss` is string if present
- **Usage**: `#[ThemeConfig]` on `Theme::$config` property.
- **Extend**: Add more rules in `ThemeConfigValidator` or a dedicated DTO with Symfony constraints.

## Workflow

1. **Draft**: Edit `Theme::config`, save. Create `ThemeRevision` on each save.
2. **Publish**: Set `status` = `published`, increment `version`, create `ThemeRevision` with `published_at`.
3. **Rollback**: Load revision by version, apply config, create new revision.
4. **Multi-tenant**: Filter by `Theme::shop` (or `shop_id` null for global).
