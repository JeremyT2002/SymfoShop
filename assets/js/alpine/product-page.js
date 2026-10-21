/**
 * Alpine component for the product page: variant picker, price/stock display and image gallery.
 *
 * Usage: x-data="productPage" with the variant list in <script type="application/json" data-variants>.
 */
import { formatMoney } from '../core/money.js';

export default () => ({
    variants: [],
    selectedId: null,
    images: [],
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
        this.selectedId = this.variants.find((v) => v.id === initial)?.id ?? this.variants[0]?.id ?? null;
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

    select(id) {
        this.selectedId = Number(id);
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
