/**
 * Theme Editor - tab switching and config collection
 */

const CONFIG_INITIAL = JSON.parse(document.getElementById('theme-config-input')?.value || '{}');

function setNested(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (let i = 0; i < keys.length - 1; i++) {
        const k = keys[i];
        if (!(k in current) || typeof current[k] !== 'object') {
            current[k] = {};
        }
        current = current[k];
    }
    const last = keys[keys.length - 1];
    if (value === 'true' || value === true) current[last] = true;
    else if (value === 'false' || value === false) current[last] = false;
    else if (value === '' || value === undefined) delete current[last];
    else current[last] = value;
}

function collectConfig() {
    const config = JSON.parse(JSON.stringify(CONFIG_INITIAL));
    document.querySelectorAll('[data-theme-key]').forEach((el) => {
        const key = el.getAttribute('data-theme-key');
        if (!key) return;
        let value;
        if (el.type === 'checkbox') {
            value = el.checked;
        } else if (el.tagName === 'TEXTAREA') {
            try {
                value = JSON.parse(el.value || '[]');
            } catch {
                value = el.value;
            }
        } else {
            value = el.value;
        }
        setNested(config, key, value);
    });
    const sectionsEl = document.getElementById('homepage-sections-data');
    if (sectionsEl?.value) {
        try {
            const sections = JSON.parse(sectionsEl.value);
            if (Array.isArray(sections)) {
                config.homepage = config.homepage || {};
                config.homepage.sections = sections;
            }
        } catch (_) {}
    }
    return config;
}

function updateConfigInputs() {
    const config = collectConfig();
    const json = JSON.stringify(config);
    document.getElementById('theme-config-input')?.setAttribute('value', json);
    document.getElementById('theme-config-publish')?.setAttribute('value', json);
}

function initTabs() {
    const tabs = document.querySelectorAll('.theme-tab');
    const panels = document.querySelectorAll('.theme-tab-panel');
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach((t) => {
                t.classList.remove('border-blue-500', 'text-blue-600');
                t.classList.add('border-transparent', 'text-gray-500');
            });
            panels.forEach((p) => p.classList.add('hidden'));
            tab.classList.remove('border-transparent', 'text-gray-500');
            tab.classList.add('border-blue-500', 'text-blue-600');
            const panel = document.getElementById('tab-' + target);
            if (panel) panel.classList.remove('hidden');
        });
    });
}

function initFormSubmit() {
    document.querySelectorAll('.theme-save-form, .theme-publish-form').forEach((form) => {
        form.addEventListener('submit', () => {
            updateConfigInputs();
        });
    });
}

function initSectionBuilder() {
    const list = document.getElementById('section-list');
    const addBtn = document.getElementById('add-section-btn');
    const addSelect = document.getElementById('add-section-type');
    const dataEl = document.getElementById('homepage-sections-data');
    if (!list || !addBtn || !addSelect || !dataEl) return;

    function getSections() {
        try {
            return JSON.parse(dataEl.value || '[]');
        } catch {
            return [];
        }
    }

    function saveSections(sections) {
        dataEl.value = JSON.stringify(sections);
    }

    function renderSection(section) {
        const li = document.createElement('li');
        li.className = 'flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-lg';
        li.dataset.sectionId = section.id;
        li.innerHTML = `
            <span class="cursor-move text-gray-400"><i class="fas fa-grip-vertical"></i></span>
            <div class="flex-1">
                <span class="font-medium">${section.type}</span>
                <span class="text-sm text-gray-500 ml-2">(${section.settings?.title || section.id})</span>
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" class="section-enabled rounded border-gray-300 text-blue-600" ${section.enabled !== false ? 'checked' : ''}>
                <span class="text-sm">Enabled</span>
            </label>
            <button type="button" class="section-remove text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
        `;
        li.querySelector('.section-enabled').addEventListener('change', () => {
            const sections = getSections();
            const s = sections.find((x) => x.id === section.id);
            if (s) s.enabled = li.querySelector('.section-enabled').checked;
            saveSections(sections);
            updateConfigInputs();
        });
        li.querySelector('.section-remove').addEventListener('click', () => {
            li.remove();
            const sections = getSections().filter((s) => s.id !== section.id);
            saveSections(sections);
            updateConfigInputs();
        });
        return li;
    }

    addBtn.addEventListener('click', () => {
        const type = addSelect.value;
        const sections = getSections();
        const id = 's-' + Date.now();
        const section = {
            type,
            id,
            enabled: true,
            settings: { title: type.replace(/_/g, ' ') },
        };
        sections.push(section);
        saveSections(sections);
        const empty = list.querySelector('.text-gray-500');
        if (empty) empty.remove();
        list.appendChild(renderSection(section));
        updateConfigInputs();
    });

    list.querySelectorAll('.section-enabled').forEach((cb) => {
        cb.addEventListener('change', updateConfigInputs);
    });
}

function init() {
    initTabs();
    initFormSubmit();
    initSectionBuilder();
    document.querySelectorAll('[data-theme-key]').forEach((el) => {
        el.addEventListener('change', updateConfigInputs);
        el.addEventListener('input', updateConfigInputs);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init, collectConfig };
