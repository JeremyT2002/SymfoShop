/**
 * Utility functions
 */

/**
 * Format price in cents to display format
 * @param {number} amount - Amount in cents
 * @param {string} currency - Currency code (e.g., 'EUR')
 * @returns {string} Formatted price string
 */
export function formatPrice(amount, currency = 'EUR') {
    const formatted = (amount / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${formatted} ${currency}`;
}

/**
 * Format number with thousand separators
 * @param {number} num - Number to format
 * @returns {string} Formatted number
 */
export function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Get element by data-js attribute
 * @param {string} value - Value of data-js attribute
 * @returns {HTMLElement|null}
 */
export function getByDataJs(value) {
    return document.querySelector(`[data-js="${value}"]`);
}

/**
 * Get all elements by data-js attribute
 * @param {string} value - Value of data-js attribute
 * @returns {NodeList}
 */
export function getAllByDataJs(value) {
    return document.querySelectorAll(`[data-js="${value}"]`);
}

