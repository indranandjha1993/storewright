# Console

Store staff work in a full-screen console at `wp-admin/admin.php?page=storewright`. Anyone with
the `manage_woocommerce` capability (shop managers and administrators) lands there after logging
in. Everyone else gets a refusal. The sidebar's Advanced link opens the regular admin, and the
admin's Console menu item leads back.

The console is the `storewright-console` plugin. WordPress mounts it with its own `@wordpress/boot`
module, so the plugin ships only its screens.

## Building

The build output is not in git. Build it once after cloning and after every change:

```
npm ci
make console
```

`npm run console:dev` rebuilds on every save. The application image builds the console itself,
so a deployment needs nothing extra.

## Screens

- **Today** (`/`): sales, orders, average order and refunds for today, the last 7 or 30 days
  against the period before, a sales chart, the queues to fulfil, awaiting payment and on hold,
  recent orders, payment gateway health from the webhook log, low stock and best sellers.
- **Orders** (`/orders`): every order with status filters, search, a detail panel with items,
  shipments (see [fulfilment.md](fulfilment.md)), customer, delivery address, payment and notes,
  and the next status one click away. Refunds and line edits open the advanced order screen.
- **Payments** (`/payments`): each gateway's mode, webhook health and webhook address to copy
  into the gateway dashboard, online orders still unpaid after 15 minutes with a button that asks
  the gateway and records the payment if it arrived, and the webhook log. See
  [payments.md](payments.md).
- **Products** (`/products`): the catalogue with stock filters and search; price, sale price and
  stock are edited in the row. Variations and everything else open the full editor.
- **Customers** (`/customers`): shoppers with a profile panel: contact, delivery address and
  recent orders.
- **Pages and journal** (`/content`): the business pages and journal entries with their status,
  each opening in the block editor.
- **Brand** (`/brand`): name, logo, colours, typefaces and corners for every surface, with a live
  preview. See [branding.md](branding.md).

Links into a screen carry their state in the query string: `p=/orders&status=processing` opens
the fulfilment queue and `p=/orders&order=42` opens one order.
- **Delivery areas** (`/areas`): the delivery areas in the order addresses are checked, an editor
  for each one and a box that tests a PIN code or a spot on the map. See
  [delivery.md](delivery.md).

## Adding a screen

Each screen is a folder under `web/app/plugins/storewright-console/routes/`:

```
routes/today/
  package.json   the route: its path and the page it belongs to
  route.js       the title, and redirects or data loading before it shows
  stage.jsx      the screen
  style.scss     its styles
```

WordPress packages such as `@wordpress/components` and `@wordpress/api-fetch` are not bundled.
The page loads the copies that ship with WordPress. Anything else a screen imports, such as
Leaflet for the map, is bundled into that screen. Code shared between screens lives in
`web/app/plugins/storewright-console/lib/`. Screens read the store through the REST API
with the logged-in user's nonce, so the API's own capability checks apply.

A screen appears in the sidebar once it is added to `menuItems()` in
`web/app/plugins/storewright-console/src/Console.php`; its icon is set in
`packages/console-init/src/index.js`, which runs before the app renders. A link can open a
screen directly with `?page=storewright&p=/path`.

Screens share `lib/screen.jsx` (`Screen`, `Card`, `Empty`) and `lib/_screen.scss` (tables,
stats, pills, avatars), all drawn from the brand tokens, so a new screen looks like the others
without copying styles.
