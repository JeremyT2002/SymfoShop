# SymfoShop

![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=flat-square&logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red?style=flat-square)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.0-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3.13-8BC0D0?style=flat-square&logo=alpine.js&logoColor=white)

A production-grade e-commerce shop system built with Symfony 7.4 and PHP 8.2+.

## 🚀 Features

**Core E-Commerce**: Catalog management (products, variants, categories), shopping cart, order management with Symfony Workflow, **Stripe** and **PayPal** (Orders API v2) payment processing, shipping methods and country-based VAT at checkout, inventory management, PDF invoices, audit logging, admin panel, RESTful API with Swagger/OpenAPI docs, multi-language support (EN/DE/FR)

**User Features**: Authentication (registration, login, password reset), role-based access control, wishlist with heart icon toggle, coupon/discount codes, storefront **legal pages** (privacy, cookies, returns, terms, imprint) and a **cookie consent** banner (localStorage)

**Frontend**: Tailwind CSS design system, Font Awesome icons, Alpine.js notifications, responsive mobile-first design, real-time cart updates, modular ES6 JavaScript architecture

**Developer Tools**: Data fixtures, Makefile commands, PHPUnit tests, linting tools, AI-assisted development ([Cursor](https://cursor.sh/), [Warp](https://www.warp.dev/))

## 📋 Requirements

- PHP 8.2+, Composer, MySQL/MariaDB or SQLite, Symfony CLI (optional)

## 🐳 Docker Setup

```bash
git clone <repository-url> && cd SymfoShop
make docker-up                    # Start services
make docker-db-setup              # Setup database
make docker-load-fixture          # Load sample data (optional)
make docker-admin-user            # Create admin user
```

Access at `http://localhost:8000`

**Docker Commands**: `docker-up`, `docker-down`, `docker-build`, `docker-logs`, `docker-db-setup`, `docker-load-fixture`, `docker-admin-user`

Docker configuration files live in `docker/` (`docker/compose.yaml`, `docker/compose.override.yaml`, `docker/Dockerfile`).

## 🛠️ Local Installation

```bash
git clone <repository-url> && cd SymfoShop
composer install
cp .env.example .env              # Optional: copy template (or use .env.local for overrides)
make db-seed                      # Create DB, run migrations, load fixtures
make admin-user                   # Create admin user
make dev                          # Start dev server
```

## ⚙️ Environment Variables

Use `.env` for **non-secret defaults** (or copy from `.env.example`). Put **real API keys, webhooks, and production secrets** in **`.env.local`** (gitignored) or in your host’s environment variables — never commit live credentials.

Load order (later wins): `.env` → `.env.local` → `.env.<APP_ENV>` → `.env.<APP_ENV>.local`.

**Common (see `.env.example` for the full list):**

| Variable | Description |
|----------|-------------|
| `APP_SECRET` | Random string, min 32 chars (e.g. in `.env.dev` or `.env.local`). |
| `DATABASE_URL` | SQLite default in `.env`; override for MySQL/PostgreSQL. |
| `MAILER_DSN` | `null://null` for dev (no sending). |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` for async queues. |
| `PAYMENT_PROVIDER` | `dev` (simulator), `stripe`, `paypal`, etc. Default is `dev`. |
| `STRIPE_*` | When using Stripe: `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` in `.env.local`. |
| `PAYPAL_*` | When using PayPal: `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID` (sandbox first). |
| `CHECKOUT_SKIP_PAYMENT` | Dev-only shortcut; keep `0` in staging/production. |

### Production logging

With `APP_ENV=prod`, Monolog writes **JSON** to **stderr** (see `config/packages/monolog.yaml`), which suits containers and log aggregators. Do not log payment payloads or tokens. Optional error tracking: install a Sentry bundle and set `SENTRY_DSN` in the host environment (not in committed `.env`).

### Security basics (built-in)

- **Login throttling:** after repeated failed logins, Symfony temporarily blocks further attempts (see `config/packages/security.yaml`).
- **Registration rate limit:** `/register` POSTs are limited per IP per hour (see `framework.rate_limiter` in `config/packages/framework.yaml`; tests use a high limit).
- **CSRF:** session-based forms (e.g. login, registration) use Symfony’s form CSRF protection; the REST API uses API keys and is stateless.

### Stripe webhooks (`POST /webhook/stripe`)

- **Signing secret required:** without `STRIPE_WEBHOOK_SECRET`, the endpoint responds with `503` (configure the secret from [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)).
- **Signature verification** uses `\Stripe\Webhook::constructEvent()`; missing or invalid `Stripe-Signature` → `400`.
- **Idempotency:** each Stripe `event.id` is stored in `processed_webhook_event` with status `pending` → `completed`. Duplicate deliveries return `200` once completed. Concurrent deliveries for the same event may receive `503` with `Retry-After` until the first finishes.
- **Failures:** if handling throws after the claim is inserted, the claim row is removed so Stripe retries can succeed.

### PayPal Checkout (`PAYMENT_PROVIDER=paypal`)

- **Credentials:** set `PAYPAL_CLIENT_ID` and `PAYPAL_CLIENT_SECRET` (sandbox first). Optional `PAYPAL_BASE_URL` (default `https://api-m.sandbox.paypal.com`; live: `https://api-m.paypal.com`).
- **Flow:** after placing an order, the customer is redirected to PayPal. **Return URL:** `GET /payment/paypal/return` (configured in the Orders API `application_context`). **Cancel:** `GET /payment/paypal/cancel`.
- **Webhooks:** `POST /webhook/paypal` — set `PAYPAL_WEBHOOK_ID` from the PayPal Developer dashboard and register the same URL. Events are verified with PayPal’s signature API; idempotency uses `processed_webhook_event` (same table as Stripe, distinct event IDs).
- **Stub mode:** if `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` are unset, behavior matches the old simulator (`paypal_…` reference + dev payment simulator UI).

## 📚 Usage

### Makefile Commands

```bash
# Setup
make install          # Install dependencies
make setup            # Complete setup
make db-seed          # Reset DB + load fixtures

# Database
make db-create        # Create database
make db-migrate       # Run migrations
make db-reset         # Drop, create, migrate
make db-fixtures      # Load sample data

# Development
make dev              # Start dev server
make test             # Run tests
make lint             # Lint code
make cache-clear      # Clear cache
make cleanup-reservations  # Clean expired reservations
```

### Admin Panel

Access at `/admin` (requires `ROLE_ADMIN`). Full CRUD for Products, Categories, Orders, Users, API Keys.

**Default credentials** (after fixtures): `admin@symfoshop.com` / `admin123`

### API Documentation

Interactive Swagger UI at `/api/v1/docs`. RESTful endpoints with Bearer token auth.

**Usage**: Create API key in `/admin/api-keys`, use as `Authorization: Bearer <key>`, access `/api/v1/*`

### Wishlist

Logged-in users: Click heart icon on products to add/remove. AJAX toggle with toast notifications. View at `/account/wishlist`. Duplicate prevention built-in.

### Coupon/Discount Codes

**Features**: Percentage and fixed amount discounts, expiration dates, usage limits (total and per-user), validation with error messages, admin CRUD interface.

**Usage**: Apply coupon codes in cart. Discounts are calculated before tax and displayed in order summary. Admin can create/manage coupons at `/admin/coupons`.

### Internationalization

Supports English (en), German (de), French (fr). Language switcher in navbar, persisted in session.

### Sample Data

```bash
make db-fixtures
```

**Users**: `admin@symfoshop.com`/`admin123`, `john.doe@example.com`/`user123`, `jane.smith@example.com`/`user123`, `bob.wilson@example.com`/`user123`

**Data**: 5 categories, 9 products with variants/stock/media

### Testing

```bash
make test             # All tests
make test-unit        # Unit only
make test-integration # Integration only
make test-coverage    # With coverage
```

### Async Processing

```bash
php bin/console messenger:consume async  # Process emails/invoices
make cleanup-reservations                 # Release expired reservations
php bin/console app:security:cleanup-expired-reset-tokens  # Clean tokens
```

## 🏗️ Architecture

**DDD Approach**: Clear separation (Domain, Application, Infrastructure, UI), business logic in services, entity-based models

**Key Components**: Doctrine ORM, Symfony Workflow (order states), Symfony Messenger (async), Custom Admin Panel, NelmioApiDocBundle (Swagger), Stripe SDK, DomPDF, Tailwind CSS, Alpine.js

**Order Lifecycle**: `new` → `payment_pending` → `paid` → `processing` → `shipped` → `completed` (or `cancelled` from multiple points)

**Payment Flow**: Checkout → Stripe intent → Webhook confirms → Invoice generated → Processing begins

**Inventory**: Stock tracking with `onHand`/`reserved`, expiring reservations, optimistic locking, transactional consistency

## 📁 Project Structure

```
SymfoShop/
├── config/          # Symfony config
├── migrations/      # DB migrations
├── public/          # Web root
├── src/
│   ├── Command/    # CLI commands
│   ├── Controller/ # HTTP controllers
│   ├── Entity/     # Doctrine entities
│   ├── Repository/ # Repositories
│   ├── Service/    # Business logic
│   └── Workflow/   # Workflow guards
├── templates/      # Twig templates
└── tests/          # PHPUnit tests
```

## 🎨 Frontend Stack

Tailwind CSS (CDN), Font Awesome 6.5.2, Alpine.js 3.13.3, custom toast/modals/cart components

**JavaScript Architecture**: Modular ES6 modules under `public/js/` with page-specific lazy loading.

**Note**: The frontend (templates, JavaScript, and styling) was primarily developed using [Cursor AI](https://cursor.sh/), an AI-powered code editor that assisted in creating the modern, responsive UI components and modular JavaScript architecture.

## 🔒 Security

Password hashing, CSRF protection, role-based access, secure password reset tokens, webhook signature verification, SQL injection prevention (Doctrine ORM)

## 📝 Development Guidelines

- **Code Style**: PSR-12, type hints, self-documenting code, thin controllers/fat services
- **Testing**: Unit tests for business logic, integration tests for workflows, test edge cases
- **Commits**: Small, reviewable commits with clear messages, one feature per commit

## 🐛 Troubleshooting

```bash
# Database reset
make db-reset
# Or: php bin/console doctrine:database:drop --force && doctrine:database:create && doctrine:migrations:migrate

# Cache
make cache-clear

# Migrations
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:sync-metadata-storage
```

## 📄 License

Proprietary

## 🤝 Contributing

1. Follow the **Development Guidelines** above (PSR-12, type hints, thin controllers, business logic in `src/Service`, Symfony conventions).
2. Write tests for new features.
3. Update documentation when behavior, APIs, or setup change.
4. Prefer small, reviewable commits with clear messages.

## 📞 Support

Refer to Symfony documentation or create an issue in the repository.
