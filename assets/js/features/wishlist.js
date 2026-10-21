/**
 * Wishlist hearts ([data-wishlist-toggle][data-product-id]). The initial state is rendered by the
 * server (aria-pressed), so no status requests are needed on page load.
 */
import { postForm } from '../core/http.js';
import { showToast } from '../core/toast.js';
import { t } from '../core/i18n.js';

function setState(productId, active) {
    document.querySelectorAll(`[data-wishlist-toggle][data-product-id="${productId}"]`).forEach((button) => {
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

export function init() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-wishlist-toggle]');
        if (!button) {
            return;
        }
        event.preventDefault();

        const productId = button.dataset.productId;
        if (!productId || button.getAttribute('aria-busy') === 'true') {
            return;
        }

        const wasActive = button.getAttribute('aria-pressed') === 'true';
        setState(productId, !wasActive); // optimistic
        button.setAttribute('aria-busy', 'true');

        try {
            const { ok, data } = await postForm(document.body.dataset.wishlistToggleUrl, { productId });
            if (!ok) {
                throw new Error(data?.message);
            }
            setState(productId, Boolean(data.inWishlist));
            showToast('success', data.message || t(data.inWishlist ? 'wishlist.added' : 'wishlist.removed'));

            // On the wishlist page itself, removed products disappear.
            if (!data.inWishlist) {
                button.closest('[data-wishlist-item]')?.remove();
            }
        } catch (error) {
            setState(productId, wasActive);
            showToast('error', error?.message || t('wishlist.error'));
        } finally {
            button.removeAttribute('aria-busy');
        }
    });
}
