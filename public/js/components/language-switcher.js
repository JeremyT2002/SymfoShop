/**
 * Language switcher component (desktop and mobile)
 */
import { getAllByDataJs } from '../core/utils.js';

export function init() {
    const baseUrl = window.location.origin;
    
    // Desktop language switcher
    const desktopSwitcher = document.getElementById('language-switcher');
    if (desktopSwitcher) {
        desktopSwitcher.addEventListener('change', function() {
            const locale = this.value;
            window.location.href = `${baseUrl}/locale/${locale}`;
        });
    }
    
    // Mobile language switcher
    const mobileSwitcher = document.getElementById('mobile-language-switcher');
    if (mobileSwitcher) {
        mobileSwitcher.addEventListener('change', function() {
            const locale = this.value;
            window.location.href = `${baseUrl}/locale/${locale}`;
        });
    }
}

