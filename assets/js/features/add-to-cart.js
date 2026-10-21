/**
 * Delegated handler for every [data-add-to-cart] button (product cards, product page).
 * Button data: data-variant-id, optional data-quantity or data-quantity-input="#selector".
 */
import { postForm } from '../core/http.js';
import { showToast } from '../core/toast.js';
import { t } from '../core/i18n.js';
import { setCartCount } from '../components/cart-count.js';

export function init() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-add-to-cart]');
        if (!button || button.disabled) {
            return;
        }
        event.preventDefault();

        const variantId = Number.parseInt(button.dataset.variantId, 10);
        const quantityInput = button.dataset.quantityInput ? document.querySelector(button.dataset.quantityInput) : null;
        const quantity = Number.parseInt(quantityInput?.value || button.dataset.quantity || '1', 10) || 1;
        if (!variantId) {
            return;
        }

        const label = button.querySelector('[data-label]');
        const original = label?.textContent;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (label) {
            label.textContent = t('common.loading');
        }

        try {
            const { ok, data } = await postForm(document.body.dataset.cartAddUrl, { variantId, quantity });
            if (ok) {
                setCartCount(data.totals?.totalQuantity);
                showToast('success', data.message || t('cart.item_added'), {
                    href: document.body.dataset.cartUrl,
                    label: t('ui.cart.view_cart'),
                });
                if (label) {
                    label.textContent = t('ui.cart.added');
                }
                setTimeout(() => {
                    if (label) {
                        label.textContent = original;
                    }
                }, 1800);
            } else {
                showToast('error', data?.message || t('product.error_adding'));
                if (label) {
                    label.textContent = original;
                }
            }
        } catch {
            showToast('error', t('product.error_adding'));
            if (label) {
                label.textContent = original;
            }
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}
