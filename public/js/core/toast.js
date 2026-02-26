/**
 * Toast notification system
 * Provides global toast notifications for success/error messages
 */

/**
 * Show a toast notification
 * @param {string} type - 'success' or 'error'
 * @param {string} message - Message to display
 */
export function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-xl flex items-center gap-3 animate-slide-in-right ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Expose globally for backward compatibility
window.showToast = showToast;
window.showSuccessToast = (message) => showToast('success', message);
window.showErrorToast = (message) => showToast('error', message);

