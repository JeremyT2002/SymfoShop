/**
 * Header search with suggestions (combobox pattern).
 * Usage: x-data="searchSuggest" data-url="/search/suggest" on the search form.
 */
export default () => ({
    query: '',
    open: false,
    loading: false,
    products: [],
    categories: [],
    active: -1,
    requestId: 0,

    init() {
        this.query = this.$refs.input?.value || '';
    },

    get options() {
        return [...this.categories, ...this.products];
    },

    async fetch() {
        const query = this.query.trim();
        if (query.length < 2) {
            this.products = [];
            this.categories = [];
            this.open = false;
            return;
        }

        const current = ++this.requestId;
        this.loading = true;
        try {
            const response = await fetch(`${this.$root.dataset.url}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (current !== this.requestId) {
                return;
            }
            this.products = data.products || [];
            this.categories = data.categories || [];
            this.active = -1;
            this.open = true;
        } catch {
            this.open = false;
        } finally {
            if (current === this.requestId) {
                this.loading = false;
            }
        }
    },

    move(step) {
        if (!this.open || this.options.length === 0) {
            return;
        }
        this.active = (this.active + step + this.options.length) % this.options.length;
    },

    choose(event) {
        const option = this.options[this.active];
        if (this.open && option) {
            event.preventDefault();
            window.location.href = option.url;
        }
        // otherwise the form submits to the full results page
    },

    close() {
        this.open = false;
        this.active = -1;
    },
});
