/**
 * Formats minor units (cents) the same way the Twig |money filter does.
 *
 * @param {number} minorUnits
 * @param {string} [currency]
 */
export function formatMoney(minorUnits, currency = 'EUR') {
    const locale = document.documentElement.lang || 'en';
    try {
        return new Intl.NumberFormat(locale, { style: 'currency', currency }).format((minorUnits || 0) / 100);
    } catch {
        return `${((minorUnits || 0) / 100).toFixed(2)} ${currency}`;
    }
}
