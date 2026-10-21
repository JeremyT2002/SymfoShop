/**
 * Cookie consent banner — stores the choice in localStorage and a first-party cookie.
 * Values: "essential" | "all". Dispatches window event "symfoshop:cookie-consent" for optional analytics hooks.
 */
const STORAGE_KEY = 'symfoshop-cookie-consent';
const CONSENT_COOKIE = 'symfoshop_cookie_consent';

function setConsentCookie(value) {
    const maxAge = 60 * 60 * 24 * 180;
    document.cookie = `${CONSENT_COOKIE}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function banner() {
    return document.getElementById('cookie-consent-banner');
}

function choose(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        /* ignore */
    }
    setConsentCookie(value);
    window.dispatchEvent(new CustomEvent('symfoshop:cookie-consent', { detail: { level: value } }));
    banner()?.setAttribute('hidden', '');
}

export function openCookiePreferences() {
    banner()?.removeAttribute('hidden');
}

export function init() {
    const el = banner();
    if (!el) {
        return;
    }

    let stored = null;
    try {
        stored = localStorage.getItem(STORAGE_KEY);
    } catch {
        /* storage blocked — show the banner */
    }

    if (stored) {
        setConsentCookie(stored);
    } else {
        el.removeAttribute('hidden');
    }

    el.querySelector('[data-cookie-accept-all]')?.addEventListener('click', () => choose('all'));
    el.querySelector('[data-cookie-essential-only]')?.addEventListener('click', () => choose('essential'));

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-cookie-preferences]')) {
            event.preventDefault();
            openCookiePreferences();
        }
    });
}
