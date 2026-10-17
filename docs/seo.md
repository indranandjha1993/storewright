# Search and link previews

## What each page prints

| Page | Description | Preview image |
|---|---|---|
| Home | The store description from the Brand screen | Home page photo, then the logo |
| Product | Short description, or the description | Product photo |
| Category | Category description | Category photo |
| Page or journal entry | Excerpt, or the opening text | Featured image |

Each of these gets `<meta name="description">`, Open Graph tags (`og:title`, `og:description`,
`og:url`, `og:image`, `og:site_name`, `og:type`) and a Twitter card. Products add
`product:price:amount`, `product:price:currency` and `product:availability`. Descriptions are cut
at 160 characters on a word boundary.

WordPress prints the canonical link and WooCommerce prints product and breadcrumb structured data.
The home page adds an `Organization` and a `WebSite` with a product search action, so search
engines can offer a search box for the store.

## Sitemap and robots

The sitemap is WordPress's own, served at `/sitemap.xml` with child sitemaps at
`/sitemap-<type>-<name>-<page>.xml`; the `wp-sitemap` addresses answer 404. Users, tags, post
formats and shipping classes are left out. `robots.txt` keeps crawlers out of the bag, checkout and
account pages and points at the sitemap.

Both follow the environment: `DISALLOW_INDEXING` is on outside production, so development and
staging stores answer `Disallow: /` and have no sitemap.

Code: `web/app/plugins/storewright-core/src/Seo/`.
