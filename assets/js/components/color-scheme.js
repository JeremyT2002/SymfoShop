/**
 * Light/dark toggle. The initial class is set by an inline script in <head> (no flash);
 * this module only handles clicks on [data-color-scheme-toggle].
 */
const STORAGE_KEY = 'symfoshop-color-scheme';

function apply(dark) {
    document.documentElement.classList.toggle('dark', dark);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#09090b' : '#fafafa');
    document.querySelectorAll('[data-color-scheme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
}

export function init() {
    apply(document.documentElement.classList.contains('dark'));

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-color-scheme-toggle]');
        if (!button) {
            return;
        }
        const dark = !document.documentElement.classList.contains('dark');
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch {
            /* storage blocked — still toggle for this page view */
        }
        apply(dark);
    });
}
