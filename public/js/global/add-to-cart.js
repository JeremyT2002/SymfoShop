/**
 * Global add-to-cart handler
 * Handles add-to-cart buttons throughout the site
 */
import { showToast } from '../core/toast.js';
import { post, parseJson } from '../core/api.js';
import { updateCartBadge } from '../components/cart-badge.js';

function getMessage(key, fallback) {
    return document.body?.dataset?.[key] || fallback;
}

function getCurrentBadgeCount() {
    const badge = document.querySelector('a[href*="cart_show"] .cart-badge');
    if (!badge) return 0;
    const raw = (badge.textContent || '').trim();
    if (raw === '' || raw === '99+') return 99;
    const parsed = Number.parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
}

// Get routes from data attributes on body or window
function getCartAddUrl() {
    const body = document.body;
    return body.dataset.cartAddUrl || '/cart/add';
}

export function init() {
    document.addEventListener('click', function(e) {
        const addToCartBtn = e.target.closest('[data-js="add-to-cart"]');
        if (!addToCartBtn || addToCartBtn.tagName === 'A') return;
        
        e.preventDefault();
        const variantId = parseInt(addToCartBtn.dataset.variantId);
        const quantity = parseInt(addToCartBtn.dataset.quantity) || 1;
        const originalHTML = addToCartBtn.innerHTML;
        
        if (!variantId || variantId <= 0) {
            console.error('Invalid variant ID');
            return;
        }
        
        // Disable button and show loading
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i><span>${getMessage('msgLoading', 'Loading...')}</span>`;
        
        post(getCartAddUrl(), {
            variantId: variantId,
            quantity: quantity
        })
        .then(parseJson)
        .then(data => {
            if (data.success) {
                // Show success toast
                showToast('success', data.message || getMessage('msgCartAdded', 'Item added to cart'));
                
                // Update cart badge immediately from API totals if available,
                // otherwise use optimistic increment so the UI never lags behind.
                if (data.totals?.totalQuantity !== undefined) {
                    updateCartBadge(data.totals.totalQuantity);
                } else {
                    updateCartBadge(getCurrentBadgeCount() + quantity);
                }
                
                // Reset button after delay
                setTimeout(() => {
                    addToCartBtn.disabled = false;
                    addToCartBtn.innerHTML = originalHTML;
                }, 1500);
            } else {
                showToast('error', data.message || getMessage('msgError', 'An error occurred'));
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', getMessage('msgCartAddError', 'An error occurred while adding to cart'));
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = originalHTML;
        });
    });
}

