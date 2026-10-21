/**
 * Keeps every cart counter ([data-cart-count]) in sync after AJAX cart changes.
 */
export function setCartCount(count) {
    const value = Math.max(0, Number(count) || 0);
    document.querySelectorAll('[data-cart-count]').forEach((badge) => {
        badge.textContent = value > 99 ? '99+' : String(value);
        badge.hidden = value === 0;
    });
}
