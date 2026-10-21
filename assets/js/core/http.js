/**
 * Small fetch wrappers for the shop's JSON endpoints.
 */

/**
 * POST form-encoded data and parse the JSON answer.
 * Never throws for HTTP errors: returns { ok: false, data } so callers can show data.message.
 *
 * @param {string} url
 * @param {Record<string, string|number>} data
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
export async function postForm(url, data = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams(Object.entries(data).map(([k, v]) => [k, String(v)])),
        credentials: 'same-origin',
    });

    return toResult(response);
}

/**
 * @param {string} url
 * @returns {Promise<{ok: boolean, status: number, data: any}>}
 */
export async function getJson(url) {
    const response = await fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    return toResult(response);
}

async function toResult(response) {
    let data = null;
    if ((response.headers.get('Content-Type') || '').includes('application/json')) {
        data = await response.json();
    }

    return { ok: response.ok && data?.success !== false, status: response.status, data };
}
