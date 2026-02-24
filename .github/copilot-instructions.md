# SymfoShop AI Coding Instructions

## Project Overview
SymfoShop is a production-grade e-commerce platform built with Symfony 7.4, featuring catalog management, shopping cart, order processing with workflow, Stripe payments, inventory tracking, and a RESTful API.

## Architecture & Key Components

### Core Entities & Relationships
- **Product**: Core catalog item with variants, media, and status
- **Order**: Central entity with workflow states (new → payment_pending → paid → processing → shipped → completed)
- **OrderItem**: Links orders to product variants with quantities and pricing
- **Payment**: Stripe payment records with idempotency keys
- **Customer/User**: User accounts with roles (ROLE_USER, ROLE_ADMIN)
- **Category**: Hierarchical product categories
- **StockItem**: Inventory tracking with reservations

### Service Layer
- **CartService**: Session-based cart using immutable CartItem DTOs
- **PaymentService**: Stripe integration with webhook handling
- **InventoryService**: Stock management with optimistic locking
- **InvoiceService**: PDF generation using DomPDF
- **Audit Logging**: Automatic logging via EventSubscriber

### Workflow Integration
Order lifecycle managed by Symfony Workflow:
```yaml
# config/packages/workflow.yaml
framework:
  workflows:
    order:
      type: state_machine
      supports: App\Entity\Order
      places: [new, payment_pending, paid, processing, shipped, completed, cancelled]
      transitions:
        submit_payment: { from: new, to: payment_pending }
        confirm_payment: { from: payment_pending, to: paid }
        # ... etc
```

## Development Workflow

### Essential Commands
```bash
# Setup & Database
make setup              # Complete installation
make db-seed           # Reset DB and load fixtures
make admin-user        # Create admin user

# Development
make dev               # Start dev server
make test              # Run PHPUnit tests
make lint              # Code quality checks
make check             # Lint + test

# Maintenance
make cache-clear       # Clear Symfony cache
make cleanup-reservations  # Clean expired inventory locks
```

### Database Operations
- Use `make db-seed` for fresh development environment
- Migrations in `migrations/` directory
- Fixtures in `src/DataFixtures/` provide sample data
- Admin user: `admin@symfoshop.com` / `admin123`

## Code Patterns & Conventions

### Money Handling
- Store prices in cents as integers (e.g., €10.00 = 1000)
- Display formatting handled in Twig templates
- Example: `product.price` is stored as 1000, displayed as "€10.00"

### Cart Implementation
```php
// Session-based cart with immutable items
$cartService->add($variantId, $quantity);
$items = $cartService->getDetailedItems(); // Returns enriched CartItem[]
```

### Form Classes
- Extend `AbstractType` with Tailwind CSS classes
- CSRF protection enabled by default
- Example in `src/Form/LoginFormType.php`

### Controller Organization
- Feature-based subdirectories: `Controller/Catalog/`, `Controller/Cart/`
- Dependency injection in constructors
- Repository injection for data access

### Testing Approach
- PHPUnit with mocking for services
- Integration tests for workflows and API
- Example: `tests/Service/Cart/CartServiceTest.php`

### Internationalization
- Translations in `translations/messages.{en,de,fr}.yaml`
- Locale switching via `LocaleController`
- Twig templates use `|trans` filter

## API Integration Points

### REST API
- Base path: `/api/v1/`
- Bearer token auth via API keys
- Documentation: `/api/v1/docs` (Swagger UI)
- Key entities: Products, Categories, Cart, Orders

### External Services
- **Stripe**: Payment processing with webhooks
- **Mailer**: Email delivery (invoice PDFs, notifications)
- **Messenger**: Async processing (Doctrine transport)

### Webhooks
- Stripe payment confirmations
- Processed webhook events tracked in `ProcessedWebhookEvent`

## Security & Audit
- Role-based access: `ROLE_USER`, `ROLE_ADMIN`
- Audit logging via `AuditLogSubscriber`
- API key management for programmatic access
- Password reset with token-based flow

## Frontend Integration
- **Tailwind CSS**: Utility-first styling
- **Alpine.js**: Lightweight JavaScript interactions
- **Twig Templates**: Server-side rendering with components
- **Toast Notifications**: Alpine-based feedback system

## Common Patterns
- **Immutable DTOs**: `CartItem` uses readonly properties
- **Event Subscribers**: For audit logging and locale handling
- **Custom Commands**: Cleanup tasks in `src/Command/`
- **Workflow Guards**: Business logic validation in transitions

## File Structure Highlights
- `src/Entity/`: Doctrine entities with relationships
- `src/Service/`: Business logic organized by domain
- `src/Controller/`: Feature-grouped controllers
- `templates/`: Twig views with i18n support
- `config/packages/`: Symfony bundle configurations
- `migrations/`: Database schema evolution
- `tests/`: PHPUnit test suite with integration tests</content>
<parameter name="filePath">/workspaces/SymfoShop/.github/copilot-instructions.md