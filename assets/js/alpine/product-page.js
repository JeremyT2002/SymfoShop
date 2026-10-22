/**
 * Alpine component for the product page: option picker (e.g. colour + size), price/stock display
 * and image gallery.
 *
 * Usage: x-data="productPage" with the variant list in <script type="application/json" data-variants>
 * and the images in <script type="application/json" data-images>.
 * Variant: {id, label, attributes: {color: "Red", size: "M"}, price, compareAt, currency, sku, available, images: [index…]}
 */
import { formatMoney } from '../core/money.js';

export default () => ({
    variants: [],
    images: [],
    selectedId: null,
    selected: {},
    activeImage: 0,
    zoomOpen: false,

    init() {
        try {
            this.variants = JSON.parse(this.$root.querySelector('script[data-variants]')?.textContent || '[]');
            this.images = JSON.parse(this.$root.querySelector('script[data-images]')?.textContent || '[]');
        } catch {
            this.variants = [];
        }
        const initial = Number(this.$root.dataset.initialVariant);
        const variant = this.variants.find((v) => v.id === initial) ?? this.variants[0];
        if (variant) {
            this.applyVariant(variant, false);
        }
    },

    get variant() {
        return this.variants.find((v) => v.id === this.selectedId) || null;
    },

    get price() {
        return this.variant ? formatMoney(this.variant.price, this.variant.currency) : '';
    },

    get compareAtPrice() {
        return this.variant?.compareAt ? formatMoney(this.variant.compareAt, this.variant.currency) : '';
    },

    get inStock() {
        return (this.variant?.available ?? 0) > 0;
    },

    get lowStock() {
        const available = this.variant?.available ?? 0;
        return available > 0 && available <= 5;
    },

    /** Variant chosen directly (variants without attributes) */
    select(id) {
        const variant = this.variants.find((v) => v.id === Number(id));
        if (variant) {
            this.applyVariant(variant, true);
        }
    },

    /** Option button, e.g. selectOption('color', 'Red') */
    selectOption(key, value) {
        const wanted = { ...this.selected, [key]: value };
        // Exact match for all chosen options, otherwise the first variant that has this value
        const variant = this.variants.find((v) => this.matches(v, wanted))
            ?? this.variants.find((v) => v.attributes?.[key] === value);
        if (variant) {
            this.applyVariant(variant, true);
        }
    },

    isSelected(key, value) {
        return this.selected[key] === value;
    },

    /** Is there an in-stock variant with this value combined with the other current choices? */
    isAvailable(key, value) {
        const wanted = { ...this.selected, [key]: value };
        return this.variants.some((v) => this.matches(v, wanted) && v.available > 0);
    },

    matches(variant, wanted) {
        return Object.entries(wanted).every(([k, val]) => variant.attributes?.[k] === val);
    },

    applyVariant(variant, moveGallery) {
        this.selectedId = variant.id;
        this.selected = { ...(variant.attributes || {}) };
        if (moveGallery && Array.isArray(variant.images) && variant.images.length > 0) {
            this.activeImage = variant.images[0];
        }
    },

    show(index) {
        this.activeImage = index;
    },

    next() {
        if (this.images.length > 1) {
            this.activeImage = (this.activeImage + 1) % this.images.length;
        }
    },

    previous() {
        if (this.images.length > 1) {
            this.activeImage = (this.activeImage - 1 + this.images.length) % this.images.length;
        }
    },
});
