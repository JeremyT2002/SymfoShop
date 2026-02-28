# Customizable Admin Dashboard – Architecture

**Implementation status:** Phase 1 done: entity, migration, widget registry, KPI + recent_orders widgets, config service (merge global/user/default), dashboard controller and grid template. Pending: customize mode UI, save-config API, JS drag-drop, nav customization, voter, tests, docs.

---

## A. Assumptions about current admin area

- **Routes**: `/admin` (dashboard), `/admin/products`, `/admin/categories`, `/admin/orders`, `/admin/coupons`, `/admin/users`, `/admin/api-keys`, `/admin/api-docs`. Single role: `ROLE_ADMIN`; no role hierarchy.
- **Layout**: `templates/admin/base.html.twig` – sidebar nav (hardcoded), header, main content block. Tailwind + Font Awesome; `asset('js/app.js')` for JS.
- **Dashboard**: `DashboardController::index()` renders `admin/dashboard.html.twig` with fixed stats (total products/orders/users) and recent orders table. No persistence of layout or widgets.
- **Stack**: No Symfony UX/Stimulus; JS in `public/js/` with `data-*` hooks. Existing dashboard is static (no customize mode).
- **Integration**: New dashboard system lives alongside current admin; we add an `AdminDashboardConfig` entity and a widget registry, then switch the dashboard page to render from config. Nav stays in base template initially; nav customization can be stored in the same config and rendered from a partial.

---

## B. Architecture

- **Widget registry**: Central registry of widget types. Each type is a `WidgetDefinition` (id, title, description, defaultSize, template, settingsSchema). Registry is built from tagged services or a single registry service that holds definitions.
- **Widget renderer**: Given a widget instance (type + settings from config), resolves the definition, loads data (via optional DataProvider per type), renders the Twig partial/template. Stateless.
- **Dashboard config**: Stored in DB as JSON per owner (global = null, or User id). Config contains: list of widget instances (id, type, position, size, settings), optional nav items array (id, label, icon, route, visible, order). Version field for optimistic locking or schema evolution.
- **Permissions**: Only users with a dedicated role (e.g. `ROLE_ADMIN_DASHBOARD_EDIT`) or super-admin can edit **global** config. Any `ROLE_ADMIN` can edit **own** dashboard config. Reading dashboard/nav uses merged config (user overrides + global default).
- **Customize mode**: Front-end “Customize Dashboard” toggle. In customize mode: show toolbar (add widget, reset), widget library panel, draggable/sortable widget grid, per-widget settings (modal). Saving sends AJAX to persist config; no full page reload.

---

## C. Data model & configuration schema

**Entity: AdminDashboardConfig**

| Field        | Type        | Description |
|-------------|-------------|-------------|
| id          | int         | PK |
| owner_id    | int, nullable | FK to User; null = global default |
| config_json | text/json   | JSON payload (see below) |
| version     | int, default 1 | For future migrations |
| updated_at  | datetime    | Last update |

**config_json schema (minimal):**

```json
{
  "widgets": [
    { "id": "w1", "type": "kpi_products", "x": 0, "y": 0, "w": 2, "h": 1, "settings": {} },
    { "id": "w2", "type": "recent_orders", "x": 2, "y": 0, "w": 4, "h": 2, "settings": { "limit": 5 } }
  ],
  "nav": [
    { "id": "dashboard", "route": "admin", "label": "Dashboard", "icon": "home", "visible": true, "order": 0 }
  ]
}
```

- **widgets**: array of { id, type, x, y, w, h, settings }. Grid: 6 columns; w/h in grid units.
- **nav**: optional; if present overrides/defaults sidebar items. Same structure as current nav (route, label, icon, visible, order).

**Default config (seed)**: One global row (owner_id = null) with a sensible default layout (e.g. 3 KPI cards + recent orders table).

---

## D. UI/UX (screens + interactions)

- **Dashboard (normal mode)**: Main content is a responsive grid of widgets (CSS Grid or flex). Each widget is a card (title, content). Desktop: multi-column; mobile: single column stack.
- **Customize mode**: Toolbar at top: [Customize Dashboard] → [Add widget] [Reset to default] [Done]. “Add widget” opens a panel/sidebar with widget library (list of definitions); click to add. Grid becomes draggable; each widget has a [settings] icon opening a modal (time range, limit, etc.). “Done” saves and exits customize mode.
- **Nav customization**: Separate page “Customize menu” under admin (or section in a settings area). List of nav items with drag handle, visibility toggle, label edit, optional icon picker. Save updates config (global or user).
- **Quick settings**: Optional block or page for shop name/logo/currency toggles; can be a single “Settings” widget or a separate admin page. Stored in config or a separate settings entity; scope TBD in implementation.

---

## E. Implementation steps (minimal)

1. **Entity + migration**: Add `AdminDashboardConfig` (owner_id nullable, config_json, version, updated_at). Migration and unique index on (owner_id).
2. **Widget definitions**: Define `WidgetDefinition` (value object or class) and a `WidgetRegistry` (array of definitions). Implement 2–3 widgets: e.g. `kpi_products`, `kpi_orders`, `recent_orders` with templates and optional data providers.
3. **Config service**: `AdminDashboardConfigService`: getConfigForUser(User), getEffectiveConfig(User), saveConfig(owner, config), getDefaultConfig(). Merge user config over global.
4. **Renderer**: `WidgetRenderer`: renderWidget(definition, instanceSettings, data?) → HTML string or Twig fragment. Use Twig for each widget type.
5. **Dashboard controller**: Dashboard index loads effective config, for each widget instance loads definition + data, passes to template. Template renders grid from config.
6. **Customize API**: Endpoints (e.g. POST admin/dashboard/config, GET admin/dashboard/widget-types) with CSRF and auth. Controller validates JSON and persists.
7. **Twig**: Dashboard grid partial, widget card partial, customize toolbar, widget library panel, widget settings modal. Use `data-js-*` for hooks.
8. **JS**: `admin/dashboard.js`: customize mode toggle, drag-and-drop (SortableJS or native), add widget from library, open settings modal, submit settings, save layout (fetch POST). No inline JS in Twig.
9. **Nav**: Nav items from config (if present); else fallback to current hardcoded nav. Nav customization page to edit config.nav.
10. **Permissions**: Voter or inline check: canEditGlobalConfig() → ROLE_SUPER_ADMIN or new role; canEditOwnConfig() → ROLE_ADMIN.
11. **Validation**: Validate config_json (allowed widget types, max widgets, w/h bounds) in a dedicated validator or in the controller/service.
12. **Tests**: Unit tests for config merge, validation; integration test for dashboard and customize endpoints; permission tests.
13. **Docs**: How to add a widget (definition + template + optional data provider), config schema, env/feature flags if any.

---

## F. Commit plan (messages + per-commit file list)

| # | Commit message | Files |
|---|----------------|--------|
| 1 | docs(admin): add dashboard customization architecture | docs/ADMIN_DASHBOARD_ARCHITECTURE.md |
| 2 | feat(admin): add AdminDashboardConfig entity and migration | Entity, Repository, migration |
| 3 | feat(dashboard): add WidgetDefinition and WidgetRegistry | WidgetDefinition, WidgetRegistry, services.yaml |
| 4 | feat(dashboard): add KPI and recent-orders widget definitions and templates | Definitions, templates/admin/widgets/*.twig |
| 5 | feat(dashboard): add AdminDashboardConfigService and default config fixture | ConfigService, default config in fixture or migration |
| 6 | feat(dashboard): dashboard controller uses config and WidgetRenderer | DashboardController, WidgetRenderer, dashboard.html.twig |
| 7 | feat(admin): customize mode toolbar and widget grid Twig | dashboard.html.twig, partials |
| 8 | feat(admin): widget library panel and widget settings modal Twig | Partials, modal |
| 9 | feat(admin): dashboard customize API (get/save config, get widget types) | Controller, routes, CSRF |
| 10 | feat(ui): admin dashboard JS (customize mode, drag-drop, save layout) | public/js/admin/dashboard.js |
| 11 | feat(admin): nav config in dashboard config and sidebar from config | base.html.twig, nav partial |
| 12 | feat(admin): nav customization page | Controller, template |
| 13 | security(admin): voter for dashboard config edit (global vs own) | Voter, controller checks |
| 14 | fix: validate dashboard config JSON (allowed types, size limits) | Validator or service |
| 15 | test: dashboard config service and permission tests | Tests |
| 16 | docs(admin): how to add a widget and config schema | docs/ADMIN_DASHBOARD_ARCHITECTURE.md, README |

---

## G. JS structure

- **Entry**: Admin pages can set `data-js-page="admin-dashboard"`; `app.js` loads `admin/dashboard.js` when that’s set (or when `body.admin` and route is admin).
- **admin/dashboard.js**: 
  - Init: read `data-js-page`, if admin-dashboard then init.
  - Customize mode: toggle class on container, show/hide toolbar, “Add widget” opens panel (widget list from data or fetch).
  - Grid: use a small drag library (e.g. Sortable) on the grid container; on drag end, compute new order/positions and PATCH/POST config.
  - Widget settings: click [settings] → open modal with form (time range, limit); submit → PATCH widget in config, re-render or update DOM.
  - Save: “Done” or auto-save on change → POST to admin/dashboard/config with JSON body and CSRF token.
- **No inline JS in Twig**: all hooks via `data-js-*`, URLs via `data-*-url`, widget types from `data-widget-types` or fetched from API.

---

## H. Tests

- **Unit**: `AdminDashboardConfigService::getEffectiveConfig` merge order; config validator (allowed types, max widgets).
- **Integration**: GET /admin returns 200 and shows widgets from default config; POST /admin/dashboard/config as admin updates user config; POST as non-admin returns 403; POST with invalid JSON returns 400.
- **Permission**: User with ROLE_ADMIN can edit own config; only super-admin (or designated role) can edit global config.

---

## I. Docs

- **How to add a widget**: 1) Add a `WidgetDefinition` to the registry (id, title, defaultSize, template path, settingsSchema). 2) Create Twig template in `admin/widgets/<type>.html.twig`. 3) Optional: implement a DataProvider for the type and call it from WidgetRenderer.
- **Config schema**: Document widgets[] and nav[] structure in docs/ADMIN_DASHBOARD_ARCHITECTURE.md.
- **Export/import**: Optional future: export config_json as file; import with validation (separate commit).

---

## J. Security notes

- **Input validation**: Strict validation of config_json (allowed widget type list, max number of widgets, w/h within 1–6, settings per type schema). Reject unknown keys.
- **CSRF**: All POST/PATCH/PUT to dashboard config endpoints require CSRF token.
- **Authorization**: Voter `AdminDashboardConfigVoter`: can edit global only for super-admin; can edit own for ROLE_ADMIN. Read access: any admin can read effective config.
- **XSS**: Widget content and nav labels rendered with Twig escaping; avoid raw in widget templates unless sanitized.
