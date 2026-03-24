/**
 * Toggles html.dark for Tailwind darkMode: 'class'. Persists choice in localStorage.
 * If no preference saved, initial mode follows prefers-color-scheme (set inline in <head>).
 */
const STORAGE_KEY = 'symfoshop-color-scheme';
const THEME_COLOR_LIGHT = '#f9fafb';
const THEME_COLOR_DARK = '#030712';

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function setMetaThemeColor() {
    const meta = document.getElementById('meta-theme-color');
    if (!meta) {
        return;
    }
    meta.setAttribute('content', isDark() ? THEME_COLOR_DARK : THEME_COLOR_LIGHT);
}

function updateToggleUi(button) {
    const dark = isDark();
    const lightLabel = button.getAttribute('data-label-light') || 'Dark mode';
    const darkLabel = button.getAttribute('data-label-dark') || 'Light mode';
    button.setAttribute('aria-label', dark ? darkLabel : lightLabel);
    button.setAttribute('aria-pressed', dark ? 'true' : 'false');
}

export function init() {
    const buttons = document.querySelectorAll('[data-color-scheme-toggle]');
    if (buttons.length === 0) {
        return;
    }

    buttons.forEach((button) => {
        updateToggleUi(button);
        button.addEventListener('click', () => {
            const root = document.documentElement;
            const nextDark = !root.classList.contains('dark');
            if (nextDark) {
                root.classList.add('dark');
                localStorage.setItem(STORAGE_KEY, 'dark');
            } else {
                root.classList.remove('dark');
                localStorage.setItem(STORAGE_KEY, 'light');
            }
            setMetaThemeColor();
            buttons.forEach((b) => updateToggleUi(b));
        });
    });

    setMetaThemeColor();
}
