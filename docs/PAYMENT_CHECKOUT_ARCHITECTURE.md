# Payment & Checkout Enhancements – Architecture

## A. Architecture Overview

### Payment provider interface and registry

- **PaymentProviderInterface**: Implemented by each provider (Stripe, PayPal, Klarna, dev).
  - `getName(): string` – Provider key (e.g. `stripe`, `dev`).
  - `startPayment(Order $order): PaymentResult` – Creates payment (intent/session), returns redirect URL and/or client secret / reference for frontend.
  - `handleReturn(Request $request): ?PaymentResolution` – Handles user return from provider (success/cancel URL); returns resolution (success/failed/pending) and payment reference.
  - `handleWebhook(Request $request): ?PaymentResolution` – Verifies signature, parses event, returns resolution; returns null if event not applicable.
- **PaymentResult**: DTO with `redirectUrl?: string`, `clientSecret?: string`, `referenceId: string`, `provider: string`.
- **PaymentResolution**: DTO with `referenceId: string`, `status: 'succeeded'|'failed'|'pending'`, `orderId?: int`.
- **PaymentProviderRegistry**: Registry of named providers; `get(string $name): PaymentProviderInterface`, `getDefault(): PaymentProviderInterface` (from env `PAYMENT_PROVIDER`).

### Config and environment

- `PAYMENT_PROVIDER`: Default provider key (`stripe`, `paypal`, `klarna`, `dev`). In `dev`/`test`, default to `dev`.
- `STRIPE_*`, `PAYPAL_*`, `KLARNA_*` for provider-specific keys; dev provider needs no keys.
- Webhook URLs per provider (e.g. `/webhook/stripe`, `/webhook/dev` for local testing if needed).

### Webhooks

- Each real provider has a dedicated webhook controller or a single dispatcher that uses the registry to resolve provider and call `handleWebhook`.
- Webhook handling: verify signature (provider-specific), idempotency (e.g. `ProcessedWebhookEvent` or provider-specific), then update `Payment` and Order workflow (confirm_payment / cancel).
- Dev provider: no external webhooks; state changes only via simulator page (and optional internal “webhook” for consistency).

### Order and Payment

- **Order**: Add optional `user` (ManyToOne User, nullable) for linking when guest registers later.
- **Payment**: Keep `provider`, `paymentIntentId` (or generic `externalReference`); dev uses e.g. `dev_<uuid>`.
- **CheckoutSession**: New entity for multi-step state and abandoned-cart: `email`, `payload` (JSON: customer + addresses), `step`, `restoreTokenHash`, `restoreTokenExpiresAt`, `emailCapturedAt`; optional `user`; cart fingerprint for restore.

---

## B. Implementation steps (high level)

1. Add **PaymentProviderInterface**, **PaymentResult**, **PaymentResolution** DTOs, and **PaymentProviderRegistry**; refactor **PaymentService** to delegate to registry/default provider.
2. Implement **StripePaymentProvider** (extract current Stripe logic from PaymentService).
3. Add **DevPaymentProvider** (fake reference, store Payment in DB, no external calls); add **PaymentSimulatorController** + simulator page (buttons: success / fail / pending).
4. Add **CheckoutSession** entity and repository; persist checkout state (step, customer, address) and email for abandoned cart.
5. Add **Order.user** (nullable) and optional “create account” after purchase; link existing orders by email on registration.
6. Split checkout into steps (Cart → Address → Payment → Review/Confirm); progress indicator; persist state in CheckoutSession/session.
7. **Address autocomplete**: **AddressAutocompleteProviderInterface**, **DevAddressProvider** (static dataset), optional real provider (env API key); fallback: manual entry without JS.
8. **Abandoned cart**: Scheduled command finds CheckoutSessions with email and no order, older than X; enqueue **SendAbandonedCartEmail**; handler sends email with hashed restore token link; restore cart endpoint validates token and restores cart.
9. Tests, docs, security checklist.

---

## C. Commit plan (exact messages and files)

| # | Commit message | Files touched |
|---|----------------|---------------|
| 1 | chore: add payment & checkout architecture doc | docs/PAYMENT_CHECKOUT_ARCHITECTURE.md |
| 2 | feat(payment): add PaymentProviderInterface and PaymentResult/PaymentResolution DTOs | src/Service/Payment/Provider/*.php (interface, DTOs) |
| 3 | feat(payment): add PaymentProviderRegistry and config PAYMENT_PROVIDER | src/Service/Payment/PaymentProviderRegistry.php, config/services.yaml, .env.example |
| 4 | feat(payment): extract Stripe into StripePaymentProvider | src/Service/Payment/Provider/StripePaymentProvider.php, refactor PaymentService |
| 5 | feat(payment): add DevPaymentProvider and simulator page | DevPaymentProvider, PaymentSimulatorController, templates, routes |
| 6 | fix: Payment entity support generic external reference for dev provider | src/Entity/Payment.php (optional rename or allow dev_ prefix), migration if needed |
| 7 | feat(checkout): add CheckoutSession entity for state and abandoned cart | Entity, Repository, migration |
| 8 | feat(checkout): guest checkout and Order.user nullable link | Order entity, migration, registration linking |
| 9 | feat(checkout): multi-step checkout with progress indicator | CheckoutController steps, Twig components, session/CheckoutSession persistence |
| 10 | feat(ui): checkout progress indicator component and Tailwind | templates/components/checkout_progress.html.twig |
| 11 | feat(address): AddressAutocompleteProvider and DevAddressProvider | Interface, DevAddressProvider, config |
| 12 | feat(address): address autocomplete JS module and data-* hooks | public/js/features/address-autocomplete.js, Twig address form |
| 13 | feat(notification): abandoned cart email and restore token | Message, Handler, CheckoutSession restore token, command |
| 14 | feat(checkout): restore cart endpoint and safe link in email | Controller, security (token hash, expiry) |
| 15 | test: payment provider and checkout unit/integration tests | tests/ |
| 16 | docs: local payment testing and adding providers | docs/PAYMENT_CHECKOUT_ARCHITECTURE.md, README |
| 17 | security: webhook signature verification and restore token hardening | Doc + existing webhook verification, token hashing |

---

## D. UI/Twig snippets

### Checkout progress indicator

```twig
{# templates/components/checkout_progress.html.twig #}
<nav aria-label="{{ 'checkout.progress'|trans }}" class="flex items-center justify-center gap-2 py-4">
  {% set steps = [
    { key: 'cart', label: 'checkout.step.cart', path: 'cart_show' },
    { key: 'address', label: 'checkout.step.address', path: 'checkout' },
    { key: 'payment', label: 'checkout.step.payment', path: null },
    { key: 'review', label: 'checkout.step.review', path: null }
  ] %}
  {% for step in steps %}
    <div class="flex items-center">
      <a href="{{ step.path ? path(step.path) : '#' }}" 
         class="px-3 py-1 rounded {{ current_step >= loop.index ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}"
         aria-current="{{ current_step == loop.index ? 'step' : 'false' }}">{{ step.label|trans }}</a>
      {% if not loop.last %}<span class="mx-1 text-gray-400">→</span>{% endif %}
    </div>
  {% endfor %}
</nav>
```

### Guest checkout email capture

Already present in CustomerInfoType (email required). Optional “Create account after purchase” checkbox:

```twig
{{ form_row(form.createAccount, { label: 'checkout.create_account_after'|trans }) }}
```

### Payment provider selection UI

```twig
<div class="space-y-2" data-js="payment-provider-choice">
  {% for provider in payment_providers %}
    <label class="flex items-center p-3 border rounded cursor-pointer">
      <input type="radio" name="payment_provider" value="{{ provider.name }}" data-js="payment-provider" {{ provider.default ? 'checked' : '' }}>
      <span class="ml-2">{{ provider.label|trans }}</span>
    </label>
  {% endfor %}
</div>
```

### Dev payment simulator UI

```twig
<div class="border rounded p-4 bg-amber-50" data-js-page="payment-simulator">
  <h2>Development: Simulate payment</h2>
  <p>Order: {{ order.orderNumber }}, Reference: {{ payment_reference }}</p>
  <div class="flex gap-2 mt-2">
    <a href="{{ path('dev_payment_simulate', {reference: payment_reference, outcome: 'success'}) }}" class="btn bg-green-600 text-white">Success</a>
    <a href="{{ path('dev_payment_simulate', {reference: payment_reference, outcome: 'failure'}) }}" class="btn bg-red-600 text-white">Failure</a>
    <a href="{{ path('dev_payment_simulate', {reference: payment_reference, outcome: 'pending'}) }}" class="btn bg-gray-600 text-white">Pending</a>
  </div>
</div>
```

---

## E. JS module structure

- **Progress indicator**: No JS required if steps are server-rendered links; optional `public/js/features/checkout-progress.js` to highlight current step from `data-js-step` on body.
- **Address autocomplete**: `public/js/features/address-autocomplete.js` – fetch from endpoint (e.g. `/api/address-suggestions?q=...`), debounce 300ms, inject suggestions in a listbox; `data-js="address-autocomplete"`, `data-address-suggestions-url`; fallback: form works without JS (manual entry).

---

## F. Tests to add

- Unit: `StripePaymentProvider::startPayment` (mock StripeClient), `DevPaymentProvider::startPayment` / `handleReturn`.
- Unit: `PaymentProviderRegistry::get` / `getDefault` with env.
- Integration: Checkout flow with dev provider (create order → simulator success → order paid).
- Integration: Abandoned cart command enqueues message; handler sends email (or assert message dispatched).
- Unit: Restore token generation and validation (hash, expiry).

Final commit: `test: add payment provider and abandoned cart tests`.

---

## G. Docs updates

- In README or docs: “Local payment testing” – set `PAYMENT_PROVIDER=dev`, use simulator page.
- “Adding a payment provider” – implement interface, register in registry, add webhook route and config.
- Env vars: `PAYMENT_PROVIDER`, `STRIPE_*`, optional `ADDRESS_AUTOCOMPLETE_PROVIDER`, `ADDRESS_API_KEY`.

Final commit: `docs: payment and checkout local testing and env vars`.

---

## H. Security checklist

- **Webhook signature verification**: Stripe (and any real provider) must verify signature before processing; never trust raw POST body without verification.
- **Replay prevention**: Idempotency (e.g. `ProcessedWebhookEvent`) to avoid processing same event twice.
- **Restore cart tokens**: One-time use, hashed (e.g. `hash_hmac('sha256', token, secret)`), short expiry (e.g. 7 days); link in email only; no sensitive data in URL except token.
- **GDPR**: Abandoned cart only for users who entered email in checkout; optional opt-out in email; document in privacy policy.

---

## Local payment testing (implemented)

- Set `PAYMENT_PROVIDER=dev` in `.env` (or leave unset; default is `dev`). No Stripe keys required for simulator.
- Checkout creates an order and redirects to the payment page. When provider is `dev`, the page shows a **Development payment simulator** with three buttons: **Simulate success**, **Simulate failure**, **Leave pending**.
- Success: payment status → succeeded, inventory committed, order → paid, invoice created, redirect to success page.
- Failure: payment → failed, inventory released, order cancelled, redirect to cart.
- Simulator URL (dev/test only): `/_dev/payment/simulate/{referenceId}/{outcome}` with `referenceId` = `dev_*` and `outcome` = `success`|`failure`|`pending`.
- For real Stripe: set `PAYMENT_PROVIDER=stripe` and configure `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, and optionally `STRIPE_WEBHOOK_SECRET` in `.env.local`.
