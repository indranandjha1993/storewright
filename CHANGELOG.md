# Changelog

All notable changes are listed here. Versions follow semantic versioning once there is a release.

## Unreleased

## 0.2.2 - 2026-10-19

Templates the Site Editor can open, a locked plugin set, and client stores that update themselves on each release.

- Every template and template part is built from blocks the Site Editor understands, and the Storewright blocks preview there instead of showing up as unsupported.
- The plugins and the theme a store needs cannot be deactivated from the admin, and no plugin can be added; what a store runs is decided by the image.
- Weights default to kilograms and dimensions to centimetres.
- Setup switches on WooCommerce's redesigned emails itself. A store installed from the command line sent the old plain emails until someone first opened the admin.
- `wp storewright templates` reports when a WooCommerce update changed a template the theme overrides, and CI fails on it.
- Client servers listed in the repository's deploy targets receive `deploy.sh update` when a release is published.
- Dependabot opens weekly pull requests for Composer, npm and the workflow actions.
- The browser tests start from the seeded store, set their users' passwords once per run, and run the console specs after the storefront specs, which removes the sign-in and cash-on-delivery flakes.


## 0.2.1 - 2026-10-17

Running a store on its own server.

- `deploy/` with a compose file, Caddy for TLS and `deploy.sh`: init, up, update with backup and automatic rollback on a failed health check, rollback, backup, restore, status, logs and wp.
- A web image beside the app image, both built by `make images` and published to GHCR by the release workflow on each tag after a Trivy scan.
- Production stores send mail over SMTP from MAIL_HOST and related settings; before this they could not send order emails.
- nginx takes the shopper's address from proxies on private networks, so the sign-in lockout and rate limit apply per shopper.

## 0.2.0 - 2026-10-17

A store a client can run: payments on three methods, delivery areas, a branded storefront, and a
console for orders, shipping, returns, invoices and settings.

Payments
- Stripe payments on Stripe's hosted checkout page through our own gateway, with refunds; the official Stripe plugin is no longer bundled.
- Payment routing by currency: an INR store offers Razorpay first.
- Cash on delivery with rules: every item must ship, an order value limit and an optional PIN list.
- Razorpay webhooks recorded once, like Stripe's.
- Payments screen: gateway mode, webhook health and address, online orders still unpaid after 15 minutes with a check against the gateway, and the webhook log.

Delivery
- Delivery areas from PIN rules, circles and drawn shapes, with India Post's PIN directory, edited on a map in the console.
- Delivery check by PIN or location in the header and on products, with the options and charges for that address.
- Shipments from the console: partial parcels, Indian and international carriers, tracking number and link, and an email to the customer.
- Track an order page and a card per parcel on the customer's order.

Storefront
- One brand setting for name, logo, colours, typefaces and corners across storefront, checkout, emails, sign-in and console.
- Finished home, shop, product, cart, checkout, account, pages, journal and 404 designs with open demo photos.
- Shop filters by category, price and stock, search suggestions and a search results page.
- Ratings and a review summary from verified buyers, and Save for later in the account.
- Checkout takes a business name and checked GSTIN, fills city and state from the PIN code and shows progress to free delivery.
- Account overview and an address book that says whether each address can be delivered to.
- Descriptions, Open Graph tags, store structured data and a sitemap at /sitemap.xml.

Operations
- Full-screen console: Today with a getting started list, Orders, Returns, Products, Customers, Payments, Delivery areas, Pages and journal, Brand and Settings.
- Printable GST invoices numbered per financial year, with CGST and SGST or IGST by place of supply and HSN codes.
- Returns from the customer's order page, approved, received and refunded from the console.
- Branded emails with links to the right order for customers and staff.
- Seeded business pages and journal entries written for a small shop.

Security and white label
- Sign-in at /sign-in/ with a lockout after repeated failures, fewer platform fingerprints, quiet REST index and a plain robots file.
- The store's name in place of WordPress and WooCommerce for staff, without marketplace promotions.

## 0.1.0 - 2026-10-16

The walking skeleton: a running store you can install with one command and pay for with a Stripe
sandbox card.

- Project layout, licence and contribution guidelines.
- WordPress, WooCommerce and the Stripe and Razorpay gateways managed by Composer.
- Application image and local stack with nginx, MariaDB, Redis and Mailpit.
- One-command install and a demo catalogue seed.
- Platform layer: health and version endpoints, hardening, local mail capture, gateway keys from the environment.
- Storewright block theme.
- CI: coding standards, static analysis, unit tests and a full stack smoke test.
- Payment webhook log: each verified Stripe event is recorded once and repeat deliveries are not reprocessed.
- Stripe stays in test mode on every store that is not production.
- `wp storewright setup` applies store defaults and a domestic shipping zone.
- Browser tests for the bag and checkout on phone and desktop viewports.
- Gateways switch on when their keys are in the environment; Stripe uses the optimized checkout.
- Product page on the Interactivity API gallery and add to bag form, without the jQuery gallery scripts.
- Header fits a phone screen; the closed bag drawer is out of the tab order.
- Home page built from reusable patterns: hero, category grid, new in, trust strip.
- One set of gateway variables; the key decides sandbox or live, live keys are refused outside production, and keys from the environment are never stored.
- Stripe sandbox order tested end to end in the browser, with webhooks forwarded by `make stripe-listen`.
