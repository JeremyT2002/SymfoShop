/**
 * API helper functions for making requests
 */

/**
 * Make a POST request to an endpoint
 * @param {string} url - Endpoint URL
 * @param {Object} data - Data to send
 * @returns {Promise<Response>}
 */
export async function post(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    });
}

/**
 * Make a GET request to an endpoint
 * @param {string} url - Endpoint URL
 * @returns {Promise<Response>}
 */
export async function get(url) {
    return fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    });
}

/**
 * Parse JSON response
 * @param {Response} response - Fetch response
 * @returns {Promise<Object>}
 */
export async function parseJson(response) {
    return response.json();
}

