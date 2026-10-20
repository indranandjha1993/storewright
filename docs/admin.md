# Admin

Store staff work in the WordPress admin at `/wp/wp-admin/`, reached from `/sign-in/`. It is
white-labelled and skinned from the brand (see [white-label.md](white-label.md) and
[design.md](design.md)), so nothing on it says WordPress or WooCommerce. Anyone with the
`manage_woocommerce` capability (shop managers and administrators) lands on the Dashboard after
signing in; shoppers are sent to their account page.

Every task has one place. The menu, top to bottom:

- **Dashboard**: a Getting started list for a new store (seven steps checked on the server, each
  linking to the screen that finishes it, hidden once done), Today (sales, orders, average order
  and refunds for today, the last 7 or 30 days against the period before, the queues to fulfil,
  awaiting payment and on hold, low stock and best sellers), Insights (payment mix, new against
  returning customers, where orders go, delivery methods, days of stock), Payments (each gateway's
  mode and webhook health) and Background work (whether the job runner is alive, jobs waiting and
  overdue, and every failed job to run again or dismiss).
- **Journal**, **Media**, **Pages**: WordPress's own screens. Posts are the store's journal.
- **Store**: WooCommerce's menu, arranged as Orders, Returns, Customers, Payments, Delivery areas,
  Brand, Settings and Reports, with Status for administrators.
  - **Orders**: WooCommerce's list and order screen, where shipments are made (see
    [fulfilment.md](fulfilment.md)) and refunds and line edits happen.
  - **Returns**: return requests from customers, approved, received and refunded in three steps.
    See [returns.md](returns.md).
  - **Payments**: each gateway's mode, webhook health and webhook address, online orders still
    unpaid after 15 minutes with a button that asks the gateway, and the webhook log. See
    [payments.md](payments.md).
  - **Delivery areas**: the areas in the order addresses are checked, an editor for each one with
    a map, and a box that tests a PIN code or a spot on the map. WooCommerce's shipping zones
    section leads here. See [delivery.md](delivery.md).
  - **Brand**: name, logo, colours, typefaces and corners for every surface, with a live preview.
    See [branding.md](branding.md).
  - **Settings**: WooCommerce's settings. The General tab carries the store's phone, GSTIN, usual
    GST rate, invoice prefix (see [invoices.md](invoices.md)), the return window and the cash on
    delivery limit; Payments, Shipping, Accounts and Emails are WooCommerce's own tabs.
- **Products**: WooCommerce's catalogue, with quick edit for price and stock.
- **Analytics**: WooCommerce's reports.
- **Marketing**: coupons.
- **Appearance**, **Users**: for administrators.

## How the pages are built

The Storewright pages are plain WordPress admin pages in `storewright-core/src/Admin/`: a class
per page registers a submenu under `woocommerce`, renders with the stock markup the skin already
styles, and handles its forms through `admin-post.php` with a nonce (`Admin\Action`) and a flash
notice across the redirect (`Admin\Flash`). The dashboard boxes are `wp_add_dashboard_widget`
callbacks in `Admin\Dashboard` over the queries in `Admin\Today` and `Admin\Insights`. Two pages
carry a small hand-written script: the delivery map (`assets/js/admin-areas.js`, Leaflet) and the
brand preview (`assets/js/admin-brand.js`). Leaflet is copied out of npm by `npm run assets`,
which `make assets`, CI and the image build run; nothing is bundled.

## Setting up after cloning

```
npm ci
make assets
```
