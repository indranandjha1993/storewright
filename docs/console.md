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

- **Today** (`/`): orders to fulfil, awaiting payment and on hold, each linking to the order list.
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
`web/app/plugins/storewright-console/src/Console.php`. A link can open a screen directly with
`?page=storewright&p=/path`.
