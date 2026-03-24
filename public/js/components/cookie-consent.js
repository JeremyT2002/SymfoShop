/**
 * Cookie consent banner — stores choice in localStorage (symfoshop-cookie-consent).
 * Values: "essential" | "all". Dispatches window event symfoshop:cookie-consent for optional analytics hooks.
 */
const STORAGE_KEY = 'symfoshop-cookie-consent';

export function init() {
    const banner = document.getElementById('cookie-consent-banner');
    if (!banner) {
        return;
    }

    try {
        if (localStorage.getItem(STORAGE_KEY)) {
            banner.remove();
            return;
        }
    } catch (e) {
        /* storage blocked — show banner but buttons may fail */
    }

    banner.classList.remove('hidden');
    banner.removeAttribute('hidden');

    const hide = (value) => {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {
            /* ignore */
        }
        window.dispatchEvent(
            new CustomEvent('symfoshop:cookie-consent', { detail: { level: value } })
        );
        banner.remove();
    };

    const acceptAll = banner.querySelector('[data-cookie-accept-all]');
    const essential = banner.querySelector('[data-cookie-essential-only]');
    if (acceptAll) {
        acceptAll.addEventListener('click', () => hide('all'));
    }
    if (essential) {
        essential.addEventListener('click', () => hide('essential'));
    }
}
