/**
 * Mobile menu toggle component
 */
import { getByDataJs } from '../core/utils.js';

export function init() {
    const toggleButton = getByDataJs('mobile-menu-toggle');
    if (!toggleButton) return;
    
    toggleButton.addEventListener('click', function() {
        const menu = document.getElementById('mobile-menu');
        const button = this;
        const icon = document.getElementById('mobile-menu-icon');
        
        if (menu && button && icon) {
            const isHidden = menu.classList.contains('hidden');
            menu.classList.toggle('hidden');
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    });
}

// Expose globally for backward compatibility (if called from onclick)
window.toggleMobileMenu = function() {
    const menu = document.getElementById('mobile-menu');
    const button = document.getElementById('mobile-menu-button');
    const icon = document.getElementById('mobile-menu-icon');
    
    if (menu && button && icon) {
        const isHidden = menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    }
};

