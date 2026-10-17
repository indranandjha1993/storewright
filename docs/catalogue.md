# Browsing the catalogue

## Shop and category pages

The shop and every category page share the `archive-product` template: the category chips across
the top, a filter column on the left and the product grid on the right. Filters are WooCommerce's
own product filter blocks, so they need no plugin and update the grid without a page load:

| Filter | Shows |
|---|---|
| Category | Every category with products, with counts |
| Price | A range slider with editable amounts |
| Availability | Each stock status that has products, with counts |

Chosen filters appear as removable chips with a "Clear all" button, and they are kept in the URL
(`/shop/?categories=kitchen-and-desk`) so a filtered page can be shared. Below 782px the column
becomes a "Filter products" button that opens the filters in a drawer.

To add or reorder filters, edit the template in the advanced admin under Appearance, Editor,
Templates, Product Catalog. Attribute filters such as size or colour can be added there once the
store has global attributes.

## Search

The search button in the header opens a field that suggests up to five products as the shopper
types, with photo and price, from the public Store API (`/wp-json/wc/store/v1/products`). Arrow
keys move through the list, Enter opens a product and Escape closes it. The last row opens the full
results.

Results use the `product-search-results` template: the search terms as the heading, the same
filters and grid as the shop, and, when nothing matches, the `storewright/no-results` pattern with
the category chips and a link back to everything. A search with exactly one match goes straight to
that product.
