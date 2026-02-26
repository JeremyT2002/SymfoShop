# Frontend JavaScript Guide

## Overview

All JavaScript in this project is organized in modular ES6 files under `public/js/`. Inline `<script>` tags in Twig templates are **not allowed** - all JavaScript must be extracted into separate modules.

## Directory Structure

```
public/js/
├── app.js                    # Main entry point, initializes all modules
├── core/
│   ├── toast.js             # Toast notification system
│   ├── utils.js             # Utility functions (formatPrice, etc.)
│   └── api.js               # API helper functions
├── components/
│   ├── mobile-menu.js       # Mobile menu toggle
│   ├── language-switcher.js # Language switcher (desktop/mobile)
│   ├── cart-badge.js        # Cart badge updates
│   └── password-toggle.js  # Password visibility toggle
├── features/
│   ├── cart.js              # Cart page functionality
│   ├── product.js           # Product page functionality
│   ├── checkout.js          # Checkout page functionality
│   ├── payment.js           # Stripe payment integration
│   └── wishlist.js          # Wishlist functionality
└── global/
    └── add-to-cart.js       # Global add-to-cart handler
```

## Module Pattern

Each module follows this pattern:

```javascript
/**
 * Module description
 */
import { dependency } from '../path/to/dependency.js';

export function init() {
    // Initialization code
    const element = document.querySelector('[data-js="module-name"]');
    if (!element) return;
    
    // Event handlers, etc.
}

// Optional: Expose globally for backward compatibility
window.moduleFunction = function() {
    // ...
};
```

## Data Attributes Convention

Use `data-js-*` attributes for JavaScript hooks:

- `data-js="module-name"` - Main hook for module initialization
- `data-js="action-name"` - Specific action (e.g., `data-js="cart-remove-item"`)
- `data-*` - Data attributes for passing values (e.g., `data-variant-id`, `data-product-id`)

### Examples

```html
<!-- Add to cart button -->
<button data-js="add-to-cart" data-variant-id="123">
    Add to Cart
</button>

<!-- Cart quantity decrease -->
<button data-js="cart-quantity-decrease" data-variant-id="123">
    -
</button>

<!-- Wishlist toggle -->
<button data-js="wishlist-toggle" data-product-id="456">
    <i class="wishlist-icon"></i>
</button>
```

## Adding New JavaScript

### Step 1: Create Module File

Create a new file in the appropriate directory:
- `core/` - Shared utilities
- `components/` - Reusable UI components
- `features/` - Page-specific functionality
- `global/` - Global handlers

### Step 2: Write Module

```javascript
// public/js/features/my-feature.js
import { showToast } from '../core/toast.js';
import { getByDataJs } from '../core/utils.js';

export function init() {
    const button = getByDataJs('my-feature-button');
    if (!button) return;
    
    button.addEventListener('click', function() {
        // Handle click
        showToast('success', 'Feature activated!');
    });
}
```

### Step 3: Register in app.js

```javascript
// public/js/app.js
import { init as initMyFeature } from './features/my-feature.js';

// For page-specific modules:
document.addEventListener('DOMContentLoaded', function() {
    const page = document.body.dataset.jsPage;
    
    if (page === 'my-page') {
        import('./features/my-feature.js').then(module => {
            if (module.init) module.init();
        });
    }
});

// For global modules:
initMyFeature();
```

### Step 4: Add Data Attributes to Template

```twig
{# templates/my-page.html.twig #}
{% block js_page %}my-page{% endblock %}

<button data-js="my-feature-button">Click Me</button>
```

## Page Detection

Pages are detected via the `data-js-page` attribute on the `<body>` tag:

```twig
{% block js_page %}cart{% endblock %}
```

Available page types:
- `cart` - Cart page
- `product` - Product detail page
- `checkout` - Checkout page
- `payment` - Payment page
- (empty) - Default/home pages

## Routes and Configuration

Routes and configuration are passed via data attributes on the `<body>` tag or container elements:

```twig
<body data-cart-add-url="{{ path('cart_add') }}"
      data-wishlist-toggle-url="{{ path('account_wishlist_toggle') }}">
```

Access in JavaScript:

```javascript
const url = document.body.dataset.cartAddUrl || '/cart/add';
```

## Best Practices

1. **Never use inline `<script>` tags** in Twig templates
2. **Use data-js-* attributes** for all JavaScript hooks
3. **Avoid fragile selectors** - prefer data attributes over IDs/classes
4. **Export init() function** from each module
5. **Handle missing elements gracefully** - check if element exists before using
6. **Use ES6 modules** - import/export syntax
7. **Keep modules focused** - one responsibility per module
8. **Document your code** - add JSDoc comments

## Common Patterns

### Event Delegation

```javascript
// For dynamically added elements
document.addEventListener('click', function(e) {
    const button = e.target.closest('[data-js="my-button"]');
    if (!button) return;
    
    // Handle click
});
```

### API Calls

```javascript
import { post, parseJson } from '../core/api.js';

post('/api/endpoint', { data: 'value' })
    .then(parseJson)
    .then(data => {
        // Handle response
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

### Toast Notifications

```javascript
import { showToast } from '../core/toast.js';

showToast('success', 'Operation completed!');
showToast('error', 'Something went wrong');
```

## Testing

When adding new JavaScript:

1. Test in modern browsers (Chrome, Firefox, Safari, Edge)
2. Check browser console for errors
3. Verify functionality works as expected
4. Test with JavaScript disabled (graceful degradation)

## Migration Notes

- Old inline scripts have been extracted to modules
- Global functions are still exposed for backward compatibility
- All templates now use `data-js-*` attributes
- Page-specific modules are loaded on demand

