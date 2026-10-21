/**
 * Cart page: quantity steppers update the server in place (debounced), lines can be removed,
 * coupons applied/removed and the cart cleared — without full reloads where avoidable.
 */
import { postForm } from '../core/http.js';
import { showToast } from '../core/toast.js';
import { formatMoney } from '../core/money.js';
import { t } from '../core/i18n.js';
import { setCartCount } from '../components/cart-count.js';

let root;

function url(name) {
    return root.dataset[name];
}

function renderTotals(totals, lines) {
    const currency = totals.currency || 'EUR';
    setCartCount(totals.totalQuantity);

    root.querySelectorAll('[data-cart-total]').forEach((el) => {
        const key = el.dataset.cartTotal;
        const value = key === 'grand' ? totals.subtotal - (totals.discount || 0) : totals[key];
        el.textContent = key === 'discount' ? `−${formatMoney(value, currency)}` : formatMoney(value, currency);
    });
    root.querySelectorAll('[data-cart-total-quantity]').forEach((el) => {
        el.textContent = String(totals.totalQuantity);
    });
    root.querySelectorAll('[data-discount-row]').forEach((el) => {
        el.hidden = !(totals.discount > 0);
    });

    Object.entries(lines || {}).forEach(([variantId, line]) => {
        const total = root.querySelector(`[data-line-total="${variantId}"]`);
        if (total) {
            total.textContent = formatMoney(line.total, currency);
        }
    });

    if (totals.totalQuantity === 0) {
        window.location.reload();
    }
}

async function send(endpoint, payload) {
    try {
        const { ok, data } = await postForm(url(endpoint), payload);
        if (!ok) {
            showToast('error', data?.message || t('cart.error_occurred'));
            return null;
        }
        return data;
    } catch {
        showToast('error', t('cart.error_occurred'));
        return null;
    }
}

function initQuantitySteppers() {
    const timers = new Map();

    root.querySelectorAll('[data-cart-line]').forEach((line) => {
        const variantId = line.dataset.cartLine;
        const input = line.querySelector('[data-quantity-input]');
        if (!input) {
            return;
        }
        let confirmed = input.value;

        const commit = async () => {
            const quantity = Math.max(1, Number.parseInt(input.value, 10) || 1);
            input.value = String(quantity);
            if (String(quantity) === confirmed) {
                return;
            }
            line.setAttribute('aria-busy', 'true');
            const data = await send('updateUrl', { variantId, quantity });
            line.removeAttribute('aria-busy');
            if (data) {
                confirmed = String(quantity);
                renderTotals(data.totals, data.lines);
            } else {
                input.value = confirmed;
            }
        };

        const schedule = () => {
            clearTimeout(timers.get(variantId));
            timers.set(variantId, setTimeout(commit, 450));
        };

        line.querySelector('[data-quantity-step="-1"]')?.addEventListener('click', () => {
            input.value = String(Math.max(1, (Number.parseInt(input.value, 10) || 1) - 1));
            schedule();
        });
        line.querySelector('[data-quantity-step="1"]')?.addEventListener('click', () => {
            input.value = String((Number.parseInt(input.value, 10) || 0) + 1);
            schedule();
        });
        input.addEventListener('change', schedule);

        line.querySelector('[data-remove-line]')?.addEventListener('click', async () => {
            line.setAttribute('aria-busy', 'true');
            const data = await send('removeUrl', { variantId });
            if (!data) {
                line.removeAttribute('aria-busy');
                return;
            }
            line.classList.add('opacity-0', 'transition-opacity', 'duration-200');
            setTimeout(() => {
                line.remove();
                renderTotals(data.totals, data.lines);
            }, 200);
            showToast('success', data.message);
        });
    });
}

function initCoupon() {
    const form = root.querySelector('[data-coupon-form]');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('input[name="code"]');
        const error = form.querySelector('[data-coupon-error]');
        const button = form.querySelector('button[type="submit"]');
        const code = (input?.value || '').trim();

        if (!code) {
            input?.focus();
            return;
        }

        button.disabled = true;
        const { ok, data } = await postForm(url('couponApplyUrl'), { code }).catch(() => ({ ok: false, data: null }));
        button.disabled = false;

        if (ok) {
            window.location.reload();
            return;
        }
        if (error) {
            error.textContent = data?.message || t('cart.error_occurred');
            error.hidden = false;
        }
        input?.setAttribute('aria-invalid', 'true');
    });

    root.querySelector('[data-coupon-remove]')?.addEventListener('click', async () => {
        if (await send('couponRemoveUrl', {})) {
            window.location.reload();
        }
    });
}

function initClear() {
    const button = root.querySelector('[data-cart-clear]');
    button?.addEventListener('click', async () => {
        if (!window.confirm(t('cart.clear_cart_confirm'))) {
            return;
        }
        button.disabled = true;
        if (await send('clearUrl', {})) {
            window.location.reload();
        } else {
            button.disabled = false;
        }
    });
}

export function init() {
    root = document.querySelector('[data-cart]');
    if (!root) {
        return;
    }
    initQuantitySteppers();
    initCoupon();
    initClear();
}
