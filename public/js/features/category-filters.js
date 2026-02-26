/**
 * Category listing filters: mobile drawer toggle, sort select auto-submit.
 * Filter form is GET so no JS required for submit; URL persists all params (SEO-friendly).
 */
export function init() {
    const drawer = document.getElementById('filter-drawer');
    const overlay = document.getElementById('filter-drawer-overlay');
    const openBtn = document.querySelector('[data-js="filter-drawer-open"]');
    const closeBtn = document.querySelector('[data-js="filter-drawer-close"]');

    function openDrawer() {
        if (drawer) drawer.classList.remove('translate-x-full');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.setAttribute('aria-hidden', 'false');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (drawer) drawer.classList.add('translate-x-full');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && !drawer.classList.contains('translate-x-full')) {
            closeDrawer();
        }
    });

    const sortForm = document.querySelector('[data-js="filter-sort-form"]');
    const sortSelect = document.getElementById('sort-select');
    if (sortForm && sortSelect) {
        sortSelect.addEventListener('change', function () {
            sortForm.submit();
        });
    }
}
