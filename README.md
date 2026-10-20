# Storewright

[![ci](https://github.com/indranandjha1993/storewright/actions/workflows/ci.yml/badge.svg)](https://github.com/indranandjha1993/storewright/actions/workflows/ci.yml)

Storewright is an online store platform built on WordPress and WooCommerce. It sells physical
and digital products, takes payment through Stripe and Razorpay, tracks shipments through to
delivery, and dresses the WordPress admin as the store's own console. Everything ships as one Docker image, so the store you run locally is the store you
deploy.

## Status

Version 0.6.0. A store can take orders and payments, deliver to the areas it chooses, ship in
parcels, issue GST invoices and handle returns, all run from one admin, and it deploys to its
own server with one script. Carrier integrations come next. Every release is listed in
[CHANGELOG.md](CHANGELOG.md).

## What it will do

- Physical, digital and mixed orders from one catalogue, with secure downloads and stock.
- Three ways to pay at checkout: Razorpay, Stripe and cash on delivery. Razorpay and Stripe each
  open their own secure page with every method enabled on the account, so card details never
  touch the store. An INR store offers Razorpay first.
- Shipments built on WooCommerce fulfillments, with carrier integrations (manual, Shiprocket,
  Delhivery first), a tracking page for the customer and delivery notifications.
- One admin for everything: the WordPress and WooCommerce screens, white-labelled and skinned
  from the store's brand, plus pages for returns, payments, delivery areas and the brand, and
  a dashboard that shows the day, the insights and a getting started list for a new store.
- Invoices with GST support for India, coupons, reviews, wishlists, search and customer accounts.
- A block theme that is the store's design system, extended through the WooCommerce
  checkout and Store APIs rather than template overrides.

## How it is built

```
web/app/plugins/storewright-core/         payments, fulfillments, carriers, invoices, admin pages, REST
web/app/mu-plugins/storewright-platform/  always-on: branding, hardening, health, migrations
web/app/themes/storewright/               block theme
docker/                                   image build, nginx and php configuration
deploy/                                   compose file and script for a server
```

WordPress core, WooCommerce and the two gateway plugins are installed by Composer and pinned
in `composer.lock`. Nothing under `web/wp` or `vendor` is tracked. Uploads live in a volume.

Targets: WordPress 7.1, WooCommerce 11.2, PHP 8.3 or newer, MariaDB 11, Redis 7.

## Running it locally

Requires Docker.

```
cp .env.example .env   # then set the salts and an admin password
make up                # start php, nginx, mariadb, redis and mailpit
make install           # install WordPress and WooCommerce, create the admin user
make seed              # demo catalogue with physical and digital products, photos from Pexels
make lint test         # what CI runs on the php
make e2e               # browser tests in a visible Chrome window
make stripe-listen     # forward Stripe sandbox webhooks to /wp-json/storewright/v1/webhooks/stripe
make pins              # import the India Post PIN directory
```

The store answers on `http://localhost:8600`, caught email on `http://localhost:8601`, and the
database on `127.0.0.1:8602` for a desktop client. Storewright keeps to ports 8600 to 8619;
change `WEB_PORT`, `MAIL_PORT` and `DB_PORT` in `.env` if you need others.

## Catalogue

Category, price and availability filters on the shop, and search suggestions as you type. See
[docs/catalogue.md](docs/catalogue.md).

## Search and link previews

Descriptions, Open Graph tags, store structured data, a sitemap at `/sitemap.xml` and a short
robots file. See [docs/seo.md](docs/seo.md).

## Returns

Customers ask to return items from their order page within the return window; staff approve,
collect and refund under Store, Returns. See [docs/returns.md](docs/returns.md).

## Invoices

Numbered GST invoices with CGST and SGST or IGST by place of supply, HSN codes and the amount in
words, ready to print or save as PDF. See [docs/invoices.md](docs/invoices.md).

## Shipping

Ship an order in one parcel or several from the order screen, with carrier and tracking, and the
customer gets an email and a card per parcel on their order. See [docs/fulfilment.md](docs/fulfilment.md).

## Delivery areas

How areas, shapes and the PIN directory work is in [docs/delivery.md](docs/delivery.md).

## Payments

Keys, webhooks and local testing for Razorpay and Stripe are in [docs/payments.md](docs/payments.md).

## Checkout

GSTIN for business buyers, PIN code autofill and a free delivery progress bar. See
[docs/checkout.md](docs/checkout.md).

## Customer account

An overview, saved delivery addresses that say whether the store delivers there, and a
saved-address chooser at checkout. See [docs/account.md](docs/account.md).

## Content

About, Contact, Shipping and returns, Terms, Privacy and a Journal come seeded and ready to edit.
See [docs/content.md](docs/content.md).

## Security

Staff sign in at `/sign-in/`; the usual WordPress probes answer 404 and the usual fingerprints
are not printed. See [docs/security.md](docs/security.md).

## Branding

Name, logo, colours, typefaces and corners are set once under Store, Brand and reach the
storefront, checkout, emails, sign-in page and admin. See [docs/branding.md](docs/branding.md).

## Admin

Store staff work in the WordPress admin, which is the store's own console: how the screens are
arranged and what each one does is in [docs/admin.md](docs/admin.md). Run `npm ci && make assets`
once after cloning for the typefaces and the map library.

## White label

What staff see in place of WordPress and WooCommerce is in [docs/white-label.md](docs/white-label.md).

## Design language

The tokens, type scale, spacing and controls the admin is built against, and how the skin
survives WordPress updates, are in [docs/design.md](docs/design.md).

## Deploying

One server per store. `deploy/` holds a compose file, a Caddyfile for TLS and `deploy.sh` with
`init`, `up`, `update`, `rollback`, `backup` and `restore`; an update that fails its health check
goes back to the previous version by itself. See [docs/deploy.md](docs/deploy.md).

## Help with deployment

I set up and look after Storewright stores for businesses: the server, domain and email, payment
gateways, delivery areas and the first products. Stores I look after are connected to this
repository and update themselves to each new release, with a backup before every update. Write to
me at [indranandjha0@gmail.com](mailto:indranandjha0@gmail.com).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues go through [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later, the same as WordPress. See [LICENSE](LICENSE).
