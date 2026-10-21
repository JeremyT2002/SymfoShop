/*
 * Storefront entry point (rendered via {{ importmap('app') }}).
 * Global behaviour is initialised here; page features are loaded on demand from <body data-page="…">.
 */
import './styles/app.css';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import { init as initColorScheme } from './js/components/color-scheme.js';
import { init as initCookieConsent } from './js/components/cookie-consent.js';
import { init as initAddToCart } from './js/features/add-to-cart.js';
import { init as initWishlist } from './js/features/wishlist.js';
import productPage from './js/alpine/product-page.js';

Alpine.plugin(focus);
Alpine.plugin(collapse);
Alpine.data('productPage', productPage);

window.Alpine = Alpine;
Alpine.start();

initColorScheme();
initCookieConsent();
initAddToCart();
initWishlist();

const pageModules = {
    cart: () => import('./js/features/cart.js'),
    checkout: () => import('./js/features/checkout.js'),
    payment: () => import('./js/features/payment.js'),
    'support-chat': () => import('./js/features/support-chat.js'),
};

const page = document.body.dataset.page;
if (page && pageModules[page]) {
    pageModules[page]().then((module) => module.init?.());
}
