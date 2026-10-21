/**
 * Stripe Payment Element. Config comes from [data-stripe] (publishable key, client secret, return URL);
 * Stripe.js itself is loaded by the payment template.
 */
import { t } from '../core/i18n.js';

export function init() {
    // Stripe.js is a separate deferred script; wait for it if this module ran first.
    if (typeof window.Stripe === 'undefined' && document.readyState !== 'complete') {
        window.addEventListener('load', init, { once: true });
        return;
    }

    const config = document.querySelector('[data-stripe]');
    const container = document.getElementById('payment-element');
    const button = document.querySelector('[data-pay]');
    const message = document.querySelector('[data-payment-message]');

    if (!config || !container || !button || typeof window.Stripe === 'undefined') {
        return;
    }

    const { stripeKey, clientSecret, returnUrl } = config.dataset;
    if (!stripeKey || !clientSecret || !returnUrl) {
        return;
    }

    const dark = document.documentElement.classList.contains('dark');
    const stripe = window.Stripe(stripeKey, { locale: document.documentElement.lang || 'auto' });
    const elements = stripe.elements({
        clientSecret,
        appearance: {
            theme: dark ? 'night' : 'stripe',
            variables: {
                colorPrimary: getComputedStyle(document.documentElement).getPropertyValue('--color-primary-600').trim() || '#2563eb',
                fontFamily: 'Inter, system-ui, sans-serif',
                borderRadius: '8px',
            },
        },
    });
    elements.create('payment').mount(container);

    const label = button.querySelector('[data-label]');
    const idleLabel = label?.textContent;

    const showError = (text) => {
        if (message) {
            message.textContent = text;
            message.hidden = false;
        }
        button.disabled = false;
        if (label) {
            label.textContent = idleLabel;
        }
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        if (label) {
            label.textContent = t('ui.payment.processing');
        }
        if (message) {
            message.hidden = true;
        }

        try {
            const { error } = await stripe.confirmPayment({ elements, confirmParams: { return_url: returnUrl } });
            // Only reached on error — on success Stripe redirects to return_url.
            if (error) {
                showError(error.message || t('ui.payment.failed'));
            }
        } catch {
            showError(t('ui.payment.failed'));
        }
    });
}
