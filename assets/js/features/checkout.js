/**
 * Checkout: recalculates shipping/tax/total when the shipping method or country changes,
 * and shows a busy state while the order is being placed.
 */
import { getJson } from '../core/http.js';
import { formatMoney } from '../core/money.js';
import { t } from '../core/i18n.js';

/**
 * One "incl. 19 % VAT" row per rate, or the tax-free export note.
 */
function renderTaxRows(container, totals) {
    if (!container) {
        return;
    }
    const locale = document.documentElement.lang || 'en';
    const rows = (totals.taxBreakdown || []).map((row) => {
        const percent = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(row.rate * 100);
        const div = document.createElement('div');
        div.className = 'flex justify-between';
        const dt = document.createElement('dt');
        dt.textContent = container.dataset.labelIncluded.replace('__RATE__', percent);
        const dd = document.createElement('dd');
        dd.className = 'tabular-nums';
        dd.textContent = formatMoney(row.amount, totals.currency);
        div.append(dt, dd);
        return div;
    });
    if (rows.length === 0 && !totals.pricesIncludeTax) {
        const note = document.createElement('p');
        note.textContent = container.dataset.labelExport;
        rows.push(note);
    }
    container.replaceChildren(...rows);
}

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
            renderTaxRows(summary?.querySelector('[data-checkout-tax-rows]'), totals);
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
