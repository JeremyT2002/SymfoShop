/**
 * Wishlist functionality
 * Handles wishlist toggle buttons and status checks
 */
import { showToast } from '../core/toast.js';
import { post, get, parseJson } from '../core/api.js';
import { getAllByDataJs } from '../core/utils.js';

// Get routes from data attributes
function getWishlistToggleUrl() {
    const body = document.body;
    return body.dataset.wishlistToggleUrl || '/account/wishlist/toggle';
}

function getWishlistCheckUrl() {
    const body = document.body;
    return body.dataset.wishlistCheckUrl || '/account/wishlist/check';
}

export function init() {
    // Wishlist toggle handler
    document.addEventListener('click', function(e) {
        const wishlistToggle = e.target.closest('[data-js="wishlist-toggle"]');
        if (!wishlistToggle) return;
        
        e.preventDefault();
        const productId = parseInt(wishlistToggle.dataset.productId);
        const icon = wishlistToggle.querySelector('.wishlist-icon');
        const isInWishlist = icon?.dataset.inWishlist === 'true';
        
        if (!productId || productId <= 0) return;
        
        // Disable button during request
        wishlistToggle.disabled = true;
        const originalHTML = icon?.innerHTML || '';
        if (icon) {
            icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        post(getWishlistToggleUrl(), {
            productId: productId
        })
        .then(parseJson)
        .then(data => {
            if (data.success) {
                // Update icon state
                if (icon) {
                    icon.dataset.inWishlist = data.inWishlist ? 'true' : 'false';
                    if (data.inWishlist) {
                        icon.className = 'fas fa-heart text-red-500 wishlist-icon transition-colors duration-200';
                        icon.setAttribute('data-in-wishlist', 'true');
                    } else {
                        icon.className = 'fas fa-heart text-gray-400 wishlist-icon transition-colors duration-200';
                        icon.setAttribute('data-in-wishlist', 'false');
                    }
                }
                
                // Show toast notification
                const message = data.message || (data.inWishlist ? 'Product added to wishlist' : 'Product removed from wishlist');
                showToast('success', message);
            } else {
                showToast('error', data.message || 'An error occurred');
                if (icon) {
                    icon.innerHTML = originalHTML;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred');
            if (icon) {
                icon.innerHTML = originalHTML;
            }
        })
        .finally(() => {
            wishlistToggle.disabled = false;
        });
    });
    
    // Check wishlist status on page load
    document.addEventListener('DOMContentLoaded', function() {
        const wishlistToggles = getAllByDataJs('wishlist-toggle');
        if (wishlistToggles.length === 0) return;
        
        const productIds = Array.from(wishlistToggles)
            .map(toggle => parseInt(toggle.dataset.productId))
            .filter(id => id > 0);
        
        if (productIds.length === 0) return;
        
        // Check each product's wishlist status
        productIds.forEach(productId => {
            const toggle = Array.from(wishlistToggles)
                .find(t => parseInt(t.dataset.productId) === productId);
            if (!toggle) return;
            
            get(`${getWishlistCheckUrl()}?productId=${productId}`)
                .then(parseJson)
                .then(data => {
                    if (data.success && data.inWishlist) {
                        const icon = toggle.querySelector('.wishlist-icon');
                        if (icon) {
                            icon.className = 'fas fa-heart text-red-500 wishlist-icon transition-colors duration-200';
                            icon.setAttribute('data-in-wishlist', 'true');
                        }
                    }
                })
                .catch(error => {
                    // Silently fail - user might not be logged in
                    console.debug('Wishlist check failed:', error);
                });
        });
    });
}

