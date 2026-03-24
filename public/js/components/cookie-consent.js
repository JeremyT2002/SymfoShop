/**
 * Cookie consent banner — stores choice in localStorage (symfoshop-cookie-consent).
 * Values: "essential" | "all". Dispatches window event symfoshop:cookie-consent for optional analytics hooks.
 */
const STORAGE_KEY = 'symfoshop-cookie-consent';

function hideBanner(banner, value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {
        /* ignore */
    }
    window.dispatchEvent(
        new CustomEvent('symfoshop:cookie-consent', { detail: { level: value } })
    );
    banner.classList.add('hidden');
    banner.setAttribute('hidden', '');
}

/**
 * Clear stored choice and show the banner again (e.g. from footer “Cookie settings”).
 */
export function openCookiePreferences() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
        /* ignore */
    }
    const banner = document.getElementById('cookie-consent-banner');
    if (!banner) {
        return;
    }
    banner.classList.remove('hidden');
    banner.removeAttribute('hidden');
}

export function init() {
    const banner = document.getElementById('cookie-consent-banner');
    if (!banner) {
        return;
    }

    try {
        if (localStorage.getItem(STORAGE_KEY)) {
            banner.classList.add('hidden');
            banner.setAttribute('hidden', '');
            return;
        }
    } catch (e) {
        /* storage blocked — show banner but buttons may fail */
    }

    banner.classList.remove('hidden');
    banner.removeAttribute('hidden');

    const acceptAll = banner.querySelector('[data-cookie-accept-all]');
    const essential = banner.querySelector('[data-cookie-essential-only]');
    if (acceptAll) {
        acceptAll.addEventListener('click', () => hideBanner(banner, 'all'));
    }
    if (essential) {
        essential.addEventListener('click', () => hideBanner(banner, 'essential'));
    }
}
