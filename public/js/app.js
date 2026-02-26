/**
 * Main application entry point
 * Initializes all JavaScript modules
 */

import { init as initMobileMenu } from './components/mobile-menu.js';
import { init as initLanguageSwitcher } from './components/language-switcher.js';
import { init as initAddToCart } from './global/add-to-cart.js';
import { init as initWishlist } from './features/wishlist.js';

// Initialize core components (always loaded)
initMobileMenu();
initLanguageSwitcher();
initAddToCart();
initWishlist();

// Initialize page-specific modules based on data-js-page attribute
document.addEventListener('DOMContentLoaded', function() {
    const page = document.body.dataset.jsPage;
    
    if (page === 'cart') {
        import('./features/cart.js').then(module => {
            if (module.init) module.init();
        });
    } else if (page === 'product') {
        import('./features/product.js').then(module => {
            if (module.init) module.init();
        });
    } else if (page === 'checkout') {
        import('./features/checkout.js').then(module => {
            if (module.init) module.init();
        });
    } else if (page === 'payment') {
        import('./features/payment.js').then(module => {
            if (module.init) module.init();
        });
    } else if (page === 'category-filters') {
        import('./features/category-filters.js').then(module => {
            if (module.init) module.init();
        });
    }
    
    // Admin password toggle (always available on admin pages)
    if (document.body.classList.contains('admin') || window.location.pathname.includes('/admin/')) {
        import('./components/password-toggle.js').then(module => {
            if (module.init) module.init();
        });
    }
});

