# Changelog

All notable changes are listed here. Versions follow semantic versioning once there is a release.

## Unreleased

- One pipeline for every push and tag, each stage gated on the one before, so a release is only built, scanned, published and deployed after the whole suite has passed on that commit; `bin/release x.y.z` is the one command that cuts a release.
## 0.7.0 - 2026-10-20

Taxes and charges, permission guards checked per user, and every admin field doing something.

- Every settings field now does something: the email template colours, typeface and logo that the brand decides are gone from the Emails tab, the Razorpay key fields that the server provides are replaced by a note with the mode, and the cash on delivery PIN rules that only the command line could set have a field under Taxes and charges.
- A permissions spec opens every admin screen and the main API routes as a guest, a shopper, a staff member and the owner, and checks each one is let in or kept out as their role says.
- A Taxes and charges tab under Store, Settings holds the GSTIN, the usual GST rate and the invoice prefix, and two checkout charges: packing and handling (free from an order amount if wanted) and a cash on delivery handling charge, each shown as its own line and carried onto the invoice. WooCommerce's own tax switch is no longer shown, since prices include GST.
## 0.6.0 - 2026-10-20

One admin. The React console is gone; WordPress and WooCommerce's own screens, white-labelled and skinned from the brand, are the console.

- Orders, products, customers, coupons, pages and the journal are worked in their WooCommerce and WordPress screens.
- Returns, Payments, Delivery areas and Brand are pages under the Store menu, with the delivery map and the brand's live preview kept.
- Phone, GSTIN, GST rate, invoice prefix, the return window and the cash on delivery limit are fields on the general settings tab.
- The dashboard carries the day's numbers, the insights, the getting started list, gateway health and background work, and staff land on it after sign-in.
- Every task has one door: WooCommerce's Home and the shipping zones section hand over to the dashboard and Delivery areas, and the WooCommerce Payments menu entry is gone.
- The image no longer builds a JavaScript app; the typefaces and the map library are copied from npm at build time.
- Background work in the console: whether the job runner is alive, jobs waiting and overdue, and every failed job with what went wrong, to run again or dismiss; /health reports a runner that has been silent for 15 minutes.
- docs/design.md writes down the tokens, type scale, spacing, controls and surfaces the console and the advanced admin share; admin page titles now sit on the same scale as console titles.
- The browser suite checks the computed styles of five admin screens against the brand tokens, so a WordPress or WooCommerce update that moves a selector the skin relies on fails CI before it reaches a store.

## 0.5.0 - 2026-10-20

One shell for console and admin, a storefront that answers back, and insights on Today.

- The storefront's forms, buttons, option cards, quantity steppers, filters and notices share one brand control system with hover, focus, loading and added states, and the cart page is called Your bag.
- Today shows the payment mix, new against returning customers, where orders go, the delivery method split and days of stock left per product, with open returns one click away.
- The plugin list describes the commerce engine and the Razorpay gateway in the store's words, hides filters, checkboxes and search while plugins are managed, and every plugin and theme header carries the release version.
- The advanced admin and the console share one shell: the same sidebar with the store's name, the signed-in user and sign out, the same content surface, and no admin bar, dashboard or WooCommerce activity strip.

## 0.4.0 - 2026-10-20

The admin wears the brand, and a store can bring its own plugins.

- A store can install and remove its own plugins from the advanced admin when its server sets `PLUGIN_MANAGEMENT=open`; the default stays managed, the plugins and theme Storewright needs stay locked either way, and client-added plugins live in a volume that backups include and updates keep.
- The advanced admin follows the brand: menu, admin bar, tables, cards, buttons, inputs, notices and tabs use the store's colours and typefaces.
- Every console input is 40px tall, matching the buttons beside it.
- The sign-in page's remaining links and the show-password control use the brand colours.

## 0.3.0 - 2026-10-20

The console looks like the store it runs.

- The sidebar carries the store name or logo, the brand colours and typeface, and the link to the full admin sits at the bottom.
- Orders, customers, brand and delivery areas share one split layout: the same header, column widths and sticky panel, and the orders table drops its wide columns while an order is open.
- Filters and switches use one segmented control instead of chips on some screens and pressed buttons on others.
- Row actions in the product, page, journal and delivery area lists are links; buttons are kept for actions that change data.
- The webhook addresses on the payments screen show in full.

## 0.2.3 - 2026-10-19

Place Order works on the first press.

- Place Order works on the first press after a shopper edits the address. WooCommerce disabled the button mid-click to recalculate delivery and the press was lost; checkout now presses it again once the recalculation ends, unless the total or delivery method changed, in which case the shopper sees the new amount first.
- Release builds no longer write the build cache, which no later release could read, and the image job stops after 30 minutes.

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
