/**
 * Global add-to-cart handler
 * Handles add-to-cart buttons throughout the site
 */
import { showToast } from '../core/toast.js';
import { post, parseJson } from '../core/api.js';
import { updateCartBadge } from '../components/cart-badge.js';

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
        addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Loading...</span>';
        
        post(getCartAddUrl(), {
            variantId: variantId,
            quantity: quantity
        })
        .then(parseJson)
        .then(data => {
            if (data.success) {
                // Show success toast
                showToast('success', data.message || 'Item added to cart');
                
                // Update cart badge
                if (data.totals?.totalQuantity !== undefined) {
                    updateCartBadge(data.totals.totalQuantity);
                } else {
                    updateCartBadge(null);
                }
                
                // Reset button after delay
                setTimeout(() => {
                    addToCartBtn.disabled = false;
                    addToCartBtn.innerHTML = originalHTML;
                }, 1500);
            } else {
                showToast('error', data.message || 'An error occurred');
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while adding to cart');
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = originalHTML;
        });
    });
}

