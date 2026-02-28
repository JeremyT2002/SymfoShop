# Theme Editor – Architecture & Implementation Plan

## A. Assumptions About Current Project and Multi-Tenancy

### Current State
- **Single-shop effectively**: No active shop/tenant resolution. Controllers do not filter by shop.
- **Multi-tenant infrastructure exists**:
  - `Shop` entity (id, name, slug, isActive)
  - `Theme` entity with nullable `shop_id` FK
  - `ThemeRevision` for versioning
  - `ShopRepository::findDefault()` returns first shop
- **Theme tokens**: `ThemeTokensService`, `ThemeTokensExtension`, CSS variables, Tailwind integration already in place.
- **Admin**: Widget-based dashboard, sidebar nav, `/admin` routes.
- **Storefront**: `base.html.twig` with theme tokens, responsive layout, homepage with hero/trust/featured/categories sections.
- **JS**: ES6 modules, `data-js-page` lazy loading, no inline JS in Twig.

### Multi-Tenancy Strategy
- **Phase 1 (MVP)**: Single shop. Use `ShopRepository::findDefault()` or create one default Shop. `Theme::shop_id` can be null for “global” theme.
- **Phase 2**: Add `ShopContextResolver` (request subscriber) resolving shop from subdomain, domain, header, or session. Inject `?Shop $shop` into controllers.
- **Design**: Theme model already supports `shop_id`. No schema change needed for multi-tenant; only add resolver and wire it.

---

## B. Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           ADMIN (Theme Editor)                            │
├─────────────────────────────────────────────────────────────────────────┤
│  ThemeEditorController  →  ThemeConfigService  →  ThemeRepository        │
│         │                         │                      │               │
│         │                    Validation              ThemeRevision       │
│         │                    (whitelist)              (versioning)       │
│         ▼                         ▼                      │               │
│  ThemeEditor UI (tabs)     ThemeConfigValidator          │               │
│  - Brand, Header/Footer    - Schema validation           │               │
│  - Homepage, Catalog       - Sanitization                │               │
│  - Product, Components     - CSS guardrails              │               │
│  - Custom CSS                                              │               │
│         │                                                  │               │
│         ▼                                                  │               │
│  JS: section-builder, theme-preview, media-upload         │               │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Publish (status=published)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        STOREFRONT (Rendering)                            │
├─────────────────────────────────────────────────────────────────────────┤
│  ThemeResolver (cached)  →  loads published Theme for shop                 │
│         │                                                               │
│         ▼                                                               │
│  base.html.twig                                                         │
│  - theme_tokens(theme.config)  →  CSS variables                          │
│  - theme_token('colors.primary.500')                                     │
│  - render_section(sectionConfig)  →  section partials                   │
│         │                                                               │
│         ▼                                                               │
│  Tailwind + CSS vars  →  responsive, accessible storefront              │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## C. Data Model

### Existing Entities (No Change)
- **Shop**: id, name, slug, isActive, createdAt
- **Theme**: id, shop_id (nullable), name, slug, status (draft|published), config (JSON), version, createdAt, updatedAt
- **ThemeRevision**: id, theme_id, config (JSON), version, status, publishedAt, comment, created_by_id, createdAt

### Theme Config JSON Schema (Extended)

```json
{
  "$schema": "theme-config-v1",
  "brand": {
    "logoUrl": "/uploads/theme/logo.png",
    "logoAlt": "Shop Logo",
    "faviconUrl": "/uploads/theme/favicon.ico",
    "siteName": "SymfoShop"
  },
  "colors": {
    "primary": { "50": "#eff6ff", "500": "#3b82f6", "900": "#1e3a8a" },
    "accent": "#22c55e",
    "background": "#f9fafb",
    "surface": "#ffffff",
    "text": { "primary": "#111827", "secondary": "#6b7280" }
  },
  "typography": {
    "fontFamily": { "sans": ["Inter", "system-ui"], "heading": ["Inter"] },
    "fontSize": { "base": "1rem", "2xl": "1.5rem" }
  },
  "layout": {
    "containerMaxWidth": "1280px",
    "spacingScale": { "4": "1rem", "8": "2rem" },
    "radius": { "md": "0.5rem", "lg": "0.75rem" }
  },
  "header": {
    "sticky": true,
    "menuItems": [
      { "label": "Home", "url": "/", "external": false },
      { "label": "Contact", "url": "https://example.com/contact", "external": true }
    ],
    "showCart": true,
    "showLanguageSwitcher": true
  },
  "footer": {
    "columns": [
      { "title": "Shop", "links": [{ "label": "Categories", "url": "/" }] },
      { "title": "Support", "links": [{ "label": "Contact", "url": "/contact" }] }
    ],
    "socialLinks": [
      { "platform": "facebook", "url": "https://facebook.com/..." },
      { "platform": "twitter", "url": "https://twitter.com/..." }
    ],
    "trustBadges": ["secure", "shipping", "returns"],
    "copyright": "© 2025 SymfoShop"
  },
  "homepage": {
    "sections": [
      { "type": "hero", "id": "hero-1", "enabled": true, "settings": { "title": "Welcome", "subtitle": "...", "ctaUrl": "/", "backgroundImage": null } },
      { "type": "featured_products", "id": "fp-1", "enabled": true, "settings": { "limit": 8, "title": "Featured" } },
      { "type": "category_grid", "id": "cg-1", "enabled": true, "settings": { "columns": 4 } },
      { "type": "testimonials", "id": "t-1", "enabled": false },
      { "type": "newsletter", "id": "nl-1", "enabled": true, "settings": { "title": "Subscribe" } }
    ]
  },
  "catalog": {
    "gridColumns": { "mobile": 1, "tablet": 2, "desktop": 4 },
    "showFilters": true,
    "filterPosition": "sidebar",
    "badgeStyle": "pill"
  },
  "product": {
    "galleryStyle": "thumbnails",
    "stickyAddToCart": true,
    "tabsLayout": "accordion"
  },
  "components": {
    "button": { "variant": "rounded", "size": "md" },
    "badge": { "style": "pill" },
    "form": { "inputRadius": "md" }
  },
  "customCss": ""
}
```

Full example: `config/theme_config_example.json`

---

## D. Theme Tokens & CSS Strategy

- **Source**: `ThemeTokensService` reads from `theme.config` (or defaults).
- **Output**: CSS variables on `:root` via `theme_css_vars(theme.config)`.
- **Tailwind**: `tailwind.config` extends theme with `var(--color-primary-500)` etc.
- **Scoped custom CSS**: Wrapped in `.shop-theme { ... }`; sanitized to block `@import`, `url()`, `expression()`, `javascript:`.
- **Constraints**: Hex colors only; font sizes 0.5rem–3rem; radius 0–2rem.

---

## E. Theme Editor UI (Screens + Interactions)

### Admin Route
`/admin/theme` → ThemeEditorController

### Tabs
| Tab | Content | Key Interactions |
|-----|---------|------------------|
| **Brand** | Logo/favicon upload, site name, primary/accent colors, font picker | Color picker, file upload, font dropdown |
| **Header/Footer** | Menu builder, footer columns, social links, trust badges | Add/remove/reorder links, icon picker |
| **Homepage** | Section builder (hero, featured, categories, testimonials, newsletter) | Drag-and-drop reorder, enable/disable, per-section settings |
| **Catalog** | Grid density, filter layout, badge style | Responsive breakpoint inputs |
| **Product** | Gallery style, sticky ATC, tabs layout | Radio/select |
| **Components** | Button/badge/form presets | Preset selector |
| **Custom CSS** | Textarea with guardrails | Syntax hint, reset |

### Interactions
- **Live preview**: iframe or split view; `data-theme-preview` URL loads storefront with `?theme_preview=1` + draft config in session.
- **Device toggles**: Mobile (375px), Tablet (768px), Desktop (1280px).
- **Save draft**: Persist to Theme (status=draft), create ThemeRevision.
- **Publish**: Set status=published, increment version, create revision, bust cache.
- **Reset section**: Restore section defaults from schema.
- **Reset all**: Restore full default config.
- **Export/Import**: JSON download/upload with validation.

---

## F. Frontend Integration (Twig + Tailwind)

### ThemeResolver
- Service: `ThemeResolver::resolve(?Shop $shop): ThemeConfig`
- Returns published Theme config for shop (or default tokens).
- Cached per shop (cache key: `theme_{shop_id}`); bust on publish.

### Twig Globals
- `theme` – resolved ThemeConfig (array) injected into all storefront templates.

### Twig Functions
- `theme_token(path)` – already exists.
- `theme_css_vars(config)` – already exists.
- `render_section(sectionConfig)` – new; renders section by type (hero, featured_products, etc.).

### Refactor Order
1. **base.html.twig**: Use `theme` for logo, site name, header/footer structure.
2. **Homepage** (`catalog/category/index.html.twig`): Replace hardcoded sections with `{% for section in theme.homepage.sections %}{{ render_section(section) }}{% endfor %}`.
3. **Header/Footer**: Partial templates reading from `theme.header`, `theme.footer`.
4. **Product/Category**: Apply `theme.catalog`, `theme.product` for layout options.

---

## G. Performance & Caching

- **ThemeResolver**: Cache resolved config; TTL 3600 or until publish.
- **Cache key**: `theme_published_{shop_id}`.
- **Bust**: On Theme publish (status change to published).
- **CSS**: Single inline block or small generated file; no extra HTTP for tokens.
- **Preview**: No cache; draft config from session.

---

## H. Security & Validation

### Config Validation
- Whitelist all keys via JSON schema / PHP DTO.
- `ThemeConfigValidator`: validate structure, types, allowed values.
- Reject unknown keys.

### Sanitization
- **Rich text**: Allowlist tags (p, strong, em, a, ul, ol, li); strip scripts, events.
- **URLs**: Only `https?`, `mailto:`, `tel:`.
- **Custom CSS**: Block `@import`, `url()`, `expression()`, `javascript:`, `behavior:`; max length 50KB.

### Auth & CSRF
- Theme editor: `ROLE_ADMIN` only.
- All POST/PUT/DELETE: CSRF token.
- Shop-scoped: future check `shop.owner_id == user.id` for multi-tenant.

---

## I. Implementation Steps

### Phase 1 – MVP (Single Shop)
1. ShopContextResolver (returns default shop).
2. ThemeResolver + cache.
3. Theme config schema + validator.
4. ThemeEditorController (Brand tab only).
5. Save draft / Publish.
6. base.html.twig uses theme for logo/colors.

### Phase 2 – Full Editor
7. All tabs (Header/Footer, Homepage, Catalog, Product, Components, Custom CSS).
8. Section builder + render_section.
9. Homepage refactor to use sections.
10. Media upload (logo, favicon, hero images).

### Phase 3 – Polish
11. Live preview + device toggles.
12. Export/Import.
13. Rollback UI.
14. Tests + docs.

---

## J. Commit Plan (Exact Messages + Per-Commit Files)

| # | Message | Files |
|---|---------|-------|
| 1 | `feat(theme): add ShopContextResolver for single-shop MVP` | `ShopContextResolver.php`, `services.yaml` (request listener) |
| 2 | `feat(theme): add ThemeResolver with cache` | `ThemeResolver.php`, `ThemeConfigCacheWarmer` or event subscriber |
| 3 | `feat(theme): extend theme config schema for full editor` | `config/theme_config_schema.json`, `ThemeConfigValidator` updates |
| 4 | `feat(theme): add ThemeConfigService for validation and persistence` | `ThemeConfigService.php` |
| 5 | `feat(admin): add theme editor route and controller stub` | `ThemeEditorController.php`, routes |
| 6 | `feat(admin): add Brand tab UI (logo, colors, typography)` | `theme_editor/brand.html.twig`, form |
| 7 | `feat(theme): wire theme config into base layout` | `base.html.twig`, inject `theme` global |
| 8 | `feat(admin): add Save draft and Publish actions` | Controller methods, flash, cache bust |
| 9 | `feat(admin): add Header/Footer tab` | `theme_editor/header_footer.html.twig` |
| 10 | `feat(theme): add render_section Twig function` | `ThemeSectionExtension.php`, section partials |
| 11 | `feat(admin): add Homepage section builder tab` | `theme_editor/homepage.html.twig`, section types registry |
| 12 | `feat(storefront): refactor homepage to use theme sections` | `catalog/category/index.html.twig`, section partials |
| 13 | `feat(admin): add Catalog and Product tabs` | `theme_editor/catalog.html.twig`, `theme_editor/product.html.twig` |
| 14 | `feat(admin): add Components and Custom CSS tabs` | `theme_editor/components.html.twig`, `theme_editor/custom_css.html.twig` |
| 15 | `feat(ui): add section builder drag-and-drop JS` | `public/js/admin/section-builder.js` |
| 16 | `feat(ui): add theme preview iframe and device toggles` | `public/js/admin/theme-preview.js` |
| 17 | `feat(admin): add media upload for logo and hero images` | `ThemeMediaController`, upload service, validation |
| 18 | `feat(theme): add export/import theme config` | Controller actions, JSON validation |
| 19 | `feat(admin): add rollback to revision UI` | `theme_editor/revisions.html.twig`, rollback action |
| 20 | `feat(theme): add reset section and reset all` | Controller actions |
| 21 | `security(theme): add config whitelist and sanitization` | `ThemeConfigValidator`, `CustomCssSanitizer` |
| 22 | `test: add ThemeConfigValidator and ThemeResolver tests` | `ThemeConfigValidatorTest`, `ThemeResolverTest` |
| 23 | `test: add theme publish and rollback tests` | `ThemeEditorControllerTest` |
| 24 | `docs: add theme editor user guide and developer docs` | `docs/THEME_EDITOR_USER.md`, `docs/THEME_SECTIONS.md` |

---

## JS Module Structure (assets/)

### Admin Theme Editor

```
public/js/
├── admin/
│   ├── theme-editor.js      # Entry: init theme editor, tab switching
│   ├── section-builder.js   # Drag-and-drop sections, add/remove/reorder
│   ├── theme-preview.js     # Iframe preview, device toggles, sync on change
│   ├── media-upload.js      # Logo/favicon/hero upload, progress, validation
│   └── color-picker.js      # Color input binding (optional, or use native)
```

### Hooks (data-* attributes)

| Element | data-attr | Purpose |
|---------|-----------|---------|
| Theme editor container | `data-js="theme-editor"` | Init theme editor |
| Section list | `data-js="section-builder"` | Init drag-and-drop |
| Preview iframe | `data-js="theme-preview"` | Load preview, device resize |
| Upload zone | `data-js="media-upload"` | File drop, upload |
| Form inputs | `data-theme-key="brand.logoUrl"` | Map to config path for preview sync |

### Section Builder Flow
1. Fetch section types from `data-section-types` (JSON).
2. Render section cards with drag handles.
3. On reorder: update hidden input / form array.
4. On add: append new section with default config.
5. On remove: confirm, then remove from DOM and form.

### Preview Sync
1. Form change → debounce 300ms → POST to preview endpoint with draft config.
2. Session stores draft; preview iframe loads storefront with `?theme_preview=1`.
3. Storefront checks `theme_preview` + session, uses draft config instead of published.

---

## K. Tests

### Config Validation
- Valid config passes.
- Unknown keys rejected.
- Invalid color format rejected.
- Invalid URL rejected.
- Custom CSS dangerous constructs rejected.

### Permissions
- Theme editor requires ROLE_ADMIN.
- Unauthenticated redirect to login.

### Publish / Rollback
- Publish updates status, version, creates revision.
- Rollback restores config from revision.
- Cache bust after publish.

---

## L. Docs

### User Guide (`docs/THEME_EDITOR_USER.md`)
- How to access theme editor.
- Each tab explained.
- Save draft vs Publish.
- Preview, export, import, reset.

### Developer Guide (`docs/THEME_SECTIONS.md`)
- How to add a new section type.
- Section schema, default config, Twig partial.
- Register in SectionRegistry.

### Export/Import
- Export: JSON download of full config.
- Import: Upload JSON, validate, merge or replace.
- Security: validate before import.

---

## Security Checklist

| Item | Rule |
|------|------|
| Config keys | Whitelist only; reject unknown |
| Colors | Hex only `#([0-9a-fA-F]{3}){1,2}` |
| URLs | `https?://`, `mailto:`, `tel:` |
| Rich text | Allowlist: p, strong, em, a, ul, ol, li; strip script, style, on* |
| Custom CSS | No @import, url(), expression(), javascript:, behavior:; max 50KB |
| File upload | Images only; max 2MB; store under `/uploads/theme/` |
| CSRF | All mutations protected |
| Auth | ROLE_ADMIN for theme editor |
| Shop scope | Future: verify user owns shop |
