# SymfoShop

![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=flat-square&logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red?style=flat-square)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.0-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3.13-8BC0D0?style=flat-square&logo=alpine.js&logoColor=white)

Production-oriented e-commerce on **Symfony 7.4** and **PHP 8.2+**: catalog, cart, checkout, payments (Stripe & PayPal), shipping/VAT, invoices, admin, API docs, EN/DE/FR.

## 🌐 Live demo

**Public demo:** [symfoshop-demo.kittyware.io](https://symfoshop-demo.kittyware.io/) Currently offline!!!!

**Admin:** `admin@symfoshop.com` / `admin123`

## Contents

| Section | What you’ll find |
|--------|-------------------|
| [Live demo](#-live-demo) | Public demo instance |
| [Quick start](#quick-start) | Fastest path to a running shop |
| [Features](#features-at-a-glance) | Capabilities in one screen |
| [Requirements](#requirements) | Tooling |
| [Environment](#environment-variables) | Important `.env` keys |
| [Docker](#docker) / [Local install](#local-installation) | Two setup paths |
| [Makefile](#makefile-commands) | Common commands |
| [Payments](#payments) | Stripe & PayPal notes |
| [Usage](#usage) | Admin, API, i18n, fixtures |
| [Fixture users](#fixture-users) | Demo logins after `doctrine:fixtures:load` |
| [Testing & CI](#testing--ci) | PHPUnit & GitHub Actions |
| [Architecture](#architecture) | Structure & order flow |
| [Troubleshooting](#troubleshooting) | When something breaks |

---

## Quick start

**Docker**

```bash
git clone <repository-url> && cd SymfoShop
make docker-up && make docker-db-setup && make docker-admin-user   # optional: make docker-load-fixture
```

→ [http://localhost:8000](http://localhost:8000) · Compose files in `docker/`.

**Local (no Docker)**

```bash
git clone <repository-url> && cd SymfoShop
composer install
cp .env.example .env    # then set APP_SECRET, DATABASE_URL, etc.
make db-seed && make admin-user && make dev
```

---

## Features at a glance

| Area | Highlights |
|------|------------|
| **Store** | Products, variants, categories, cart, coupons, wishlist, legal pages, cookie banner, contact & return forms, sitemap/robots |
| **Checkout** | Workflow-based orders, shipping methods, country-based VAT, **Stripe** & **PayPal** (Orders API v2) |
| **Back office** | Products, categories, orders, users, API keys, payment/shipping methods, return requests, theme editor |
| **Account** | Register/login/reset, dashboard (profile, orders, localized invoice PDFs) |
| **API** | REST + OpenAPI/Swagger at `/api/v1/docs` (Bearer API keys) |
| **Tech** | Doctrine, Messenger (async mail), DomPDF invoices, Tailwind + Alpine, modular ES modules under `public/js/` |

---

## Requirements

- PHP 8.2+, Composer  
- MySQL/MariaDB or SQLite  
- Symfony CLI (optional)  

---

## Environment variables

Use **`.env`** for defaults; **secrets** in **`.env.local`** or the host environment (never commit live keys).

Load order (later wins): `.env` → `.env.local` → `.env.<APP_ENV>` → `.env.<APP_ENV>.local`.

| Variable | Purpose |
|----------|---------|
| `APP_SECRET` | Random string, ≥ 32 characters |
| `DEFAULT_URI` | Base URL for CLI/routing (e.g. `http://localhost`) |
| `DATABASE_URL` | DB connection; SQLite default in template |
| `MAILER_DSN` | e.g. `null://null` in dev |
| `MAILER_FROM` | From address for transactional mail |
| `CONTACT_NOTIFY_EMAIL` | Inbox for contact form (falls back to `MAILER_FROM` if unset) |
| `MESSENGER_TRANSPORT_DSN` | Async transport (e.g. `doctrine://default`) |
| `PAYMENT_PROVIDER` | `dev`, `stripe`, `paypal`, … |
| `STRIPE_*` | `STRIPE_SECRET_KEY`, publishable key, `STRIPE_WEBHOOK_SECRET` when using Stripe |
| `PAYPAL_*` | `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID` for PayPal |
| `CHECKOUT_SKIP_PAYMENT` | Dev-only shortcut; use `0` in staging/production |
| `SENTRY_DSN` | Optional; used when `APP_ENV=prod` (Sentry bundle) |

**Production logging:** with `APP_ENV=prod`, Monolog emits **JSON on stderr** (`config/packages/monolog.yaml`) — suitable for containers. Do not log payment payloads or tokens.

**Built-in security:** login throttling, registration rate limiting (`framework.rate_limiter`), CSRF on session forms; API is stateless with API keys.

---

## Docker

| Make target | Role |
|-------------|------|
| `docker-up` / `docker-down` | Start/stop stack |
| `docker-build` | Build images |
| `docker-logs` | Logs |
| `docker-db-setup` | Database setup |
| `docker-load-fixture` | Sample data (optional) |
| `docker-admin-user` | Admin user |

Files: `docker/compose.yaml`, `docker/compose.override.yaml`, `docker/Dockerfile`.

---

## Local installation

```bash
composer install
cp .env.example .env
make db-seed
make admin-user
make dev
```

---

## Makefile commands

| Command | Description |
|---------|-------------|
| `make install` | Composer install |
| `make setup` | Full local setup |
| `make db-seed` | DB + migrations + fixtures |
| `make db-create` / `make db-migrate` / `make db-reset` | Database lifecycle |
| `make db-fixtures` | Load fixtures only |
| `make dev` | Symfony dev server |
| `make test` | PHPUnit (see [Testing & CI](#testing--ci)) |
| `make lint` | Lint |
| `make cache-clear` | Clear cache |
| `make cleanup-reservations` | Expired stock reservations |

---

## Payments

### Stripe (`POST /webhook/stripe`)

- Requires **`STRIPE_WEBHOOK_SECRET`**; otherwise the endpoint returns **503** (configure in [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)).
- Verifies `Stripe-Signature`; idempotency via `processed_webhook_event` (duplicate completed events → 200).

### PayPal (`PAYMENT_PROVIDER=paypal`)

- **Sandbox first:** `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`; optional `PAYPAL_BASE_URL` (live: `https://api-m.paypal.com`).
- **Return / cancel:** `GET /payment/paypal/return`, `GET /payment/paypal/cancel`.
- **Webhooks:** `POST /webhook/paypal` — set `PAYPAL_WEBHOOK_ID` in the PayPal developer app.
- **Unset credentials:** behaves like the dev simulator (no real PayPal API).

---

## Usage

### Admin

- URL: **`/admin`** — requires `ROLE_ADMIN`.
- After fixtures: **`admin@symfoshop.com`** / **`admin123`**.

### API

- Docs: **`/api/v1/docs`** (Swagger UI).
- Create a key under **API Keys** in admin; send **`Authorization: Bearer <key>`** on `/api/v1/*`.

### Storefront extras

- **Wishlist** (logged in): heart on product → `/account/wishlist`.
- **Coupons:** apply in cart; manage at `/admin/coupons`.
- **Languages:** EN / DE / FR (switcher in navbar, session).

### Sample data

```bash
make db-fixtures
```

**Catalog:** 5 categories, 9 products with variants, stock, media.

### Async

```bash
php bin/console messenger:consume async
make cleanup-reservations
php bin/console app:security:cleanup-expired-reset-tokens
```

---

## Testing & CI

```bash
make test              # all tests
make test-unit
make test-integration
make test-coverage
```

**GitHub Actions** (on push/PR to `main` and `dev`): Composer install, `lint:yaml`, `lint:translations`, `lint:twig`, `lint:container` (`test` env), **PHPUnit**. The workflow creates a minimal `.env` (including `DEFAULT_URI`) so `cache:clear` after `composer install` succeeds.

---

## Architecture

- **Layers:** domain logic in services; thin controllers; Doctrine entities/repositories.
- **Order states (Workflow):** `new` → `payment_pending` → `paid` → `processing` → `shipped` → `completed` (or `cancelled`).
- **Inventory:** `onHand` / `reserved`, expiring reservations, optimistic locking.
- **Stack:** Messenger, Nelmio OpenAPI, Stripe SDK, DomPDF, Tailwind, Alpine.js.

### Repository layout

```
SymfoShop/
├── config/           Symfony config
├── docker/           Docker Compose & Dockerfile
├── migrations/
├── public/           Web root, JS modules
├── src/
│   ├── Command/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│   └── Workflow/
├── templates/
├── tests/
└── translations/
```

---

## Security

Password hashing, CSRF on forms, roles, secure reset tokens, webhook signature checks, parameterized queries via Doctrine.

---

## Development guidelines

- **Style:** PSR-12, typed properties/parameters, fat services / thin controllers.
- **Tests:** cover business logic and critical workflows; run `make test` before large changes.
- **Commits:** small, reviewable steps with clear messages.
- **Cursor:** shared agent rules live under `.cursor/rules/` (PRs via `gh`, commit conventions).

---

## Troubleshooting

```bash
make db-reset
make cache-clear
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:sync-metadata-storage
```

---

## License

Proprietary.

---

## Contributing

1. Follow the guidelines above and Symfony best practices.  
2. Add tests for new behavior.  
3. Update this README when setup, env vars, or APIs change.  
4. Open a PR (e.g. `gh pr create` from a feature branch).

---

## Support

Symfony docs and project issues in this repository.
