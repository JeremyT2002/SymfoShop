/**
 * Password toggle component
 * Toggles password field visibility
 */
import { getAllByDataJs } from '../core/utils.js';

export function init() {
    getAllByDataJs('password-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetFieldId = this.dataset.targetFieldId;
            if (!targetFieldId) return;
            
            const field = document.getElementById(targetFieldId);
            const icon = document.getElementById(targetFieldId + '-toggle-icon');
            
            if (field && icon) {
                if (field.type === 'password') {
                    field.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    field.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    });
}

// Expose globally for backward compatibility
window.togglePassword = function(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-toggle-icon');
    if (field && icon) {
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
};

