# White label

A store's staff should only ever see the store's own name. The platform plugin
(`web/app/mu-plugins/storewright-platform`) takes care of that for the login page, the admin and
the admin's REST answers. It needs no settings of its own; it reads three values WordPress already
keeps:

| What | Where it comes from |
|---|---|
| Name | Settings, General, Site Title |
| Logo | the Site Logo block (or the theme's custom logo) |
| Colour | the theme palette colour with the slug `accent` |

Change any of them in the site editor and the login page and admin follow.

## What changes

- The login page shows the logo, or the name when there is no logo, links to the storefront and
  uses the brand colour for its button.
- Page titles end in the store name. The footer shows the name and no version.
- The admin bar loses the WordPress menu, the comments counter and the greeting.
- The dashboard keeps the store status box only. Help tabs are removed.
- Text that names WooCommerce reads "Store", and text that names WordPress reads the store name.
  This covers PHP screens, the React screens and the REST routes under `wc-admin`,
  `wc-analytics` and `wc/v3`. Domains such as WooCommerce.com are left as they are.
- Marketplace suggestions, the Extensions screens and the Marketing overview are gone. Coupons
  stay under Marketing.

Staff without the `manage_options` capability, such as shop managers, also do not see Posts,
Comments, Tools or the system status page. Administrators keep everything.

`wp storewright setup` hides WooCommerce's setup checklists and removes the sample post, page
and comment WordPress installs, as long as nobody has edited them.
