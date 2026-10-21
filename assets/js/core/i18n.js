/**
 * Translated UI strings, rendered by base.html.twig into <script type="application/json" id="app-i18n">.
 */
let messages = null;

function load() {
    if (messages !== null) {
        return messages;
    }
    try {
        messages = JSON.parse(document.getElementById('app-i18n')?.textContent || '{}');
    } catch {
        messages = {};
    }
    return messages;
}

/**
 * @param {string} key e.g. "cart.added"
 * @param {Record<string, string|number>} [params] replaces %name% placeholders
 */
export function t(key, params = {}) {
    let text = load()[key] ?? key;
    for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`%${name}%`, String(value));
    }
    return text;
}
