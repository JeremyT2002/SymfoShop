/**
 * Cart badge update component
 */

/**
 * Update cart badge with item count
 * @param {number|null} count - Item count or null to hide badge
 */
export function updateCartBadge(count) {
    const cartLink = document.querySelector('a[href*="cart_show"]');
    if (!cartLink) return;
    
    let badge = cartLink.querySelector('.cart-badge');
    
    if (count !== null && count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cart-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center';
            cartLink.classList.add('relative');
            cartLink.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else if (badge) {
        badge.classList.add('hidden');
    }
}

// Expose globally for backward compatibility
window.updateCartBadge = updateCartBadge;

