/**
 * Theme editor: tab switching, homepage section builder and collecting the config JSON on save/publish.
 */
const ACTIVE_TAB = ['border-fg', 'text-fg'];
const INACTIVE_TAB = ['border-transparent', 'text-fg-subtle'];

const TRASH_ICON = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>';
const GRIP_ICON = '<svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>';

let initialConfig = {};

function setNested(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (const key of keys.slice(0, -1)) {
        if (typeof current[key] !== 'object' || current[key] === null) {
            current[key] = {};
        }
        current = current[key];
    }
    const last = keys[keys.length - 1];
    if (value === 'true' || value === true) {
        current[last] = true;
    } else if (value === 'false' || value === false) {
        current[last] = false;
    } else if (value === '' || value === undefined) {
        delete current[last];
    } else {
        current[last] = value;
    }
}

export function collectConfig() {
    const config = structuredClone(initialConfig);

    document.querySelectorAll('[data-theme-key]').forEach((el) => {
        const key = el.getAttribute('data-theme-key');
        if (!key) {
            return;
        }
        let value = el.value;
        if (el.type === 'checkbox') {
            value = el.checked;
        } else if (el.tagName === 'TEXTAREA') {
            try {
                value = JSON.parse(el.value || '[]');
            } catch {
                value = el.value;
            }
        }
        setNested(config, key, value);
    });

    const sections = readSections();
    if (sections !== null) {
        config.homepage = config.homepage || {};
        config.homepage.sections = sections;
    }

    return config;
}

function readSections() {
    const el = document.getElementById('homepage-sections-data');
    if (!el?.value) {
        return null;
    }
    try {
        const sections = JSON.parse(el.value);
        return Array.isArray(sections) ? sections : null;
    } catch {
        return null;
    }
}

function writeSections(sections) {
    const el = document.getElementById('homepage-sections-data');
    if (el) {
        el.value = JSON.stringify(sections);
    }
}

function updateConfigInputs() {
    const json = JSON.stringify(collectConfig());
    document.getElementById('theme-config-input')?.setAttribute('value', json);
    document.getElementById('theme-config-publish')?.setAttribute('value', json);
}

function initTabs() {
    const tabs = document.querySelectorAll('.theme-tab');
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((t) => {
                t.classList.remove(...ACTIVE_TAB);
                t.classList.add(...INACTIVE_TAB);
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.theme-tab-panel').forEach((panel) => panel.classList.add('hidden'));

            tab.classList.remove(...INACTIVE_TAB);
            tab.classList.add(...ACTIVE_TAB);
            tab.setAttribute('aria-selected', 'true');
            document.getElementById('tab-' + tab.dataset.tab)?.classList.remove('hidden');
        });
    });
}

function renderSection(section) {
    const li = document.createElement('li');
    li.className = 'section-card flex items-center gap-4 rounded-xl border border-line bg-panel p-4';
    li.dataset.sectionId = section.id;
    li.innerHTML = `
        <span class="cursor-grab p-1 text-fg-subtle">${GRIP_ICON}</span>
        <div class="min-w-0 flex-1"><span class="font-medium" data-type></span><span class="ml-2 text-sm text-fg-subtle" data-title></span></div>
        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 hover:bg-subtle">
            <input type="checkbox" class="section-enabled checkbox" ${section.enabled !== false ? 'checked' : ''}>
            <span class="text-sm text-fg-muted">Enabled</span>
        </label>
        <button type="button" class="section-remove rounded-lg p-2 text-fg-subtle transition-colors hover:bg-red-50 hover:text-red-600" title="Remove section">${TRASH_ICON}</button>
    `;
    li.querySelector('[data-type]').textContent = (section.type || '').replace(/_/g, ' ');
    li.querySelector('[data-title]').textContent = `— ${section.settings?.title || section.id}`;
    return li;
}

function initSectionBuilder() {
    const list = document.getElementById('section-list');
    const addBtn = document.getElementById('add-section-btn');
    const addSelect = document.getElementById('add-section-type');
    if (!list || !addBtn || !addSelect) {
        return;
    }

    // Delegated, so server-rendered and newly added sections behave the same
    list.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.section-enabled');
        const item = checkbox?.closest('[data-section-id]');
        if (!item) {
            return;
        }
        const sections = readSections() || [];
        const section = sections.find((s) => s.id === item.dataset.sectionId);
        if (section) {
            section.enabled = checkbox.checked;
            writeSections(sections);
        }
        updateConfigInputs();
    });

    list.addEventListener('click', (event) => {
        const item = event.target.closest('.section-remove')?.closest('[data-section-id]');
        if (!item) {
            return;
        }
        writeSections((readSections() || []).filter((s) => s.id !== item.dataset.sectionId));
        item.remove();
        updateConfigInputs();
    });

    addBtn.addEventListener('click', () => {
        const type = addSelect.value;
        const section = { type, id: 's-' + Date.now(), enabled: true, settings: { title: type.replace(/_/g, ' ') } };
        writeSections([...(readSections() || []), section]);
        list.querySelector('[data-empty-placeholder]')?.remove();
        list.appendChild(renderSection(section));
        updateConfigInputs();
    });
}

export function init() {
    try {
        initialConfig = JSON.parse(document.getElementById('theme-config-input')?.value || '{}');
    } catch {
        initialConfig = {};
    }

    initTabs();
    initSectionBuilder();

    document.querySelectorAll('.theme-save-form, .theme-publish-form').forEach((form) => {
        form.addEventListener('submit', updateConfigInputs);
    });
    document.querySelectorAll('[data-theme-key]').forEach((el) => {
        el.addEventListener('change', updateConfigInputs);
        el.addEventListener('input', updateConfigInputs);
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            document.querySelector('.theme-save-form')?.requestSubmit();
        }
    });
}
