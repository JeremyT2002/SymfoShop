/**
 * Checkout: recalculates shipping/tax/total when the shipping method or country changes,
 * and shows a busy state while the order is being placed.
 */
import { getJson } from '../core/http.js';
import { formatMoney } from '../core/money.js';
import { t } from '../core/i18n.js';

export function init() {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) {
        return;
    }

    const summary = document.querySelector('[data-checkout-summary]');
    const countrySelect = form.querySelector('[data-checkout-country]');
    let requestId = 0;

    const refresh = async () => {
        const shipping = form.querySelector('input[name="shipping_method"]:checked')?.value || '';
        const country = countrySelect?.value || '';
        const params = new URLSearchParams({ shipping_method: shipping, country });
        const current = ++requestId;

        summary?.setAttribute('aria-busy', 'true');
        try {
            const { ok, data } = await getJson(`${form.dataset.totalsUrl}?${params}`);
            if (!ok || current !== requestId) {
                return;
            }
            const totals = data.totals;
            summary?.querySelectorAll('[data-checkout-total]').forEach((el) => {
                const key = el.dataset.checkoutTotal;
                if (key === 'shippingAmount' && totals.shippingAmount === 0) {
                    el.textContent = t('checkout.shipping_free');
                } else if (key === 'discount') {
                    el.textContent = `−${formatMoney(totals.discount, totals.currency)}`;
                } else {
                    el.textContent = formatMoney(totals[key], totals.currency);
                }
            });
        } catch {
            /* keep the server-rendered numbers; the order is recalculated on submit anyway */
        } finally {
            if (current === requestId) {
                summary?.removeAttribute('aria-busy');
            }
        }
    };

    form.querySelectorAll('input[name="shipping_method"]').forEach((radio) => radio.addEventListener('change', refresh));
    countrySelect?.addEventListener('change', refresh);

    form.addEventListener('submit', () => {
        const button = document.querySelector('[data-place-order]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.querySelector('[data-label]')?.replaceChildren(t('ui.checkout.placing_order'));
        }
    });
}
