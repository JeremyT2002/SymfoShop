# Catalog / Category Product Filters

## 1. Filtering logic & query strategy

- **Controller** parses query params into `CatalogFilters` (min_price, max_price, attr_*, in_stock, sort).
- **ProductRepository**:
  - `findFilteredByCategory(Category, CatalogFilters, offset, limit)`: builds a DQL query with:
    - `p.status = active`, `p.category = :category`
    - inner join `p.variants` for price and attribute filtering
    - price: `v.priceAmount BETWEEN min AND max` (products that have at least one variant in range)
    - in_stock: left join `v.stockItem`, `(s.onHand - s.reserved) > 0`
    - attributes: product IDs from a separate step (native SQL for JSON, or PHP fallback) then `p.id IN (:ids)`
  - `countFilteredByCategory`: same filters, `COUNT(DISTINCT p.id)` without `GROUP BY`.
  - `getFilterOptionsForCategory`: returns `price_min`, `price_max`, and `attributes` (key => value => count) for the category.
- **Attribute filter**: Variant has JSON `attributes` (e.g. `{"size":"M","color":"Red"}`). On SQLite we use `json_extract(pv.attributes, '$.key') IN (...)`; on other DBs we fetch variants and filter in PHP.

## 2. Database / attribute modeling

- **Product**: `category_id` (ManyToOne). **ProductVariant**: `price_amount`, `attributes` (JSON). **StockItem**: `on_hand`, `reserved`; available = on_hand - reserved.
- No separate attribute table: variant attributes are a flexible JSON object. Filter options are derived at runtime from distinct keys/values in the category’s variants.
- For very large catalogs, consider an indexed attribute table or search engine for better performance.

## 3. URL & SEO

- All filters and sort are **GET query parameters**: `?min_price=1000&max_price=5000&in_stock=1&attr_size[]=M&attr_color[]=Red&sort=price-asc&page=2`.
- Same params are used for pagination and “clear” links so filter state is shareable and bookmarkable.
- No inline JS in Twig; filter form is a normal GET form; JS only for drawer and sort auto-submit.

## 4. Performance

- Indexes: `product(category_id, status)`, `product_variant(product_id)`, `stock_item(variant_id)`.
- JSON attribute filter: on SQLite uses `json_extract` (no index); on other DBs we use a PHP fallback over a bounded result set. For large datasets, add a proper attribute index or search backend.
- Pagination: `setFirstResult` / `setMaxResults` to limit result size.

## 5. Commit plan

| # | Commit message | Files |
|---|----------------|--------|
| 1 | feat(catalog): add CatalogFilters DTO and ProductRepository filtered queries | src/Catalog/CatalogFilters.php, src/Repository/ProductRepository.php |
| 2 | feat(catalog): category controller uses filters and filter options | src/Controller/Catalog/CategoryController.php |
| 3 | feat(catalog): category filter UI (sidebar, drawer, badges, GET form) | templates/catalog/category/show.html.twig, _filter_form.html.twig |
| 4 | feat(ui): category filters JS (drawer, sort auto-submit) | public/js/features/category-filters.js, public/js/app.js |
| 5 | feat(catalog): category filter translations | translations/messages.en.yaml (and de/fr if needed) |
| 6 | docs(catalog): document filter logic and URL strategy | docs/CATALOG_FILTERS.md |

Optional later: test(catalog): repository and controller filter tests; fix(catalog): price slider (optional enhancement).
