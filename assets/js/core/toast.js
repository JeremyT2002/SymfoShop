/**
 * Toast notifications in a polite live region (#toast-region, rendered by base layouts).
 */
const ICONS = {
    success: '<path d="M20 6 9 17l-5-5"/>',
    error: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/>',
};

function region() {
    let el = document.getElementById('toast-region');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-region';
        document.body.appendChild(el);
    }
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('role', 'status');
    el.className = 'pointer-events-none fixed inset-x-0 top-3 z-[100] flex flex-col items-center gap-2 px-4 sm:top-auto sm:bottom-6 sm:items-end sm:px-6';
    return el;
}

/**
 * @param {'success'|'error'|'info'} type
 * @param {string} message plain text, never HTML
 * @param {{href?: string, label?: string}} [action] optional link shown next to the message
 */
export function showToast(type, message, action = null) {
    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-fg shadow-lg shadow-black/5 animate-rise';

    const iconColor = type === 'success' ? 'text-emerald-600' : type === 'error' ? 'text-red-600' : 'text-primary-600';
    toast.innerHTML = `<svg class="size-5 shrink-0 ${iconColor}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[type] || ICONS.info}</svg>`;

    const text = document.createElement('p');
    text.className = 'flex-1';
    text.textContent = message;
    toast.appendChild(text);

    if (action?.href) {
        const link = document.createElement('a');
        link.href = action.href;
        link.className = 'shrink-0 font-semibold text-primary-600 hover:underline dark:text-primary-400';
        link.textContent = action.label || '';
        toast.appendChild(link);
    }

    region().appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, type === 'error' ? 6000 : 3500);
}
