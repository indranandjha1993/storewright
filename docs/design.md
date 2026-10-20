# Design language

The admin and the sign-in page are built against one spec: the tokens below, one type scale, one
spacing scale and one set of controls. The storefront shares the same tokens through `theme.json`
and adds its own larger scale for shoppers. A screen that follows this page looks like every other
screen without anyone comparing them.

Where it lives:

| Surface | Source |
|---|---|
| Tokens | `Brand\Palette` and `Brand\Tokens` in `web/app/mu-plugins/storewright-platform/src/Brand/` |
| Admin | `web/app/mu-plugins/storewright-platform/assets/admin.css` and `admin.js`, enqueued by `WhiteLabel\AdminSkin` |
| Storefront | `web/app/themes/storewright/style.css` (Controls, Buttons, Shell polish sections) |

## Tokens

Every colour, radius and typeface comes from the Brand screen (see [branding.md](branding.md))
and is printed as CSS variables in `<style id="storewright-tokens">` on every admin and sign-in
page. Nothing in the admin skin uses a literal colour; the only exception is the warning amber on
notices, which no brand colour covers.

| Token | Derived from | Used for |
|---|---|---|
| `--sw-base` | Background | The page behind everything: sidebar, admin body |
| `--sw-surface` | White on a light base, base lightened 6% on a dark base | Content area, panels, cards, inputs, tables |
| `--sw-contrast` | Text | Body text, headings, active labels |
| `--sw-muted` | Text mixed 38% into base | Secondary text, labels, icons at rest, table heads |
| `--sw-line` | Text mixed 88% into base | Borders, dividers, quiet pills |
| `--sw-accent` | Accent | Links, primary buttons, active navigation, focus rings, positive notes |
| `--sw-accent-hover` | Accent darkened 12% (lightened on a dark base) | Hover and focus of anything accent |
| `--sw-accent-soft` | Accent mixed 86% into base | Active navigation background, selection, row hover at 40% |
| `--sw-on-accent` | Black or white, whichever reads on the accent | Text on primary buttons and counters |
| `--sw-sale` | Sale | Errors, out of stock, negative change, declined states |
| `--sw-on-sale` | Black or white on the sale colour | Text on sale badges |
| `--sw-shadow` | Text colour at 6% | Submenus, panels, popovers |
| `--sw-radius` | Corners: 2px, 10px or 20px | Panels, cards, tables, segmented controls |
| `--sw-radius-small` | Corners: 2px, 8px or 12px | Inputs, buttons, menu items, notices |
| `--sw-radius-button` | Corners: 2px, 10px or 999px | Storefront buttons |
| `--sw-font-body` | Body typeface | Everything |
| `--sw-font-heading` | Heading typeface | Titles, stat values, the store name |

The storefront reads the same values as `--wp--preset--color--{base,surface,contrast,muted,line,
accent,accent-hover,accent-soft,on-accent,sale,on-sale}` and `--wp--custom--radius{,-small,-button}`.

WordPress's own components follow the accent because `--wp-admin-theme-color` and
`--wp-components-color-accent` are set to it at `:root` and, with `!important`, on every
`[data-wpds-corner-radius]` root WordPress 7.1 paints its own tokens on.

## Type

Body text is 14px (`0.875rem`) in the body typeface. Everything else is one of these roles.

| Role | Size | Weight | Typeface | Notes |
|---|---|---|---|---|
| Screen title | 2rem | 600 | heading | `.wrap h1`, line-height 1.1, letter-spacing -0.01em |
| Stat value | 1.5rem | 600 | heading | dashboard numbers, line-height 1, tabular numerals |
| Section title | 1.375rem | 600 | heading | a section inside a page, such as the webhook log |
| Store name in the sidebar | 1.25rem | 600 | heading | line-height 1.2 |
| Card title | 1.125rem | 600 | heading | cards, fieldset legends, WooCommerce `h2` |
| Admin box heading | 1rem | 600 | heading | `.postbox .hndle`, the dashboard boxes |
| Body, inputs, buttons | 0.875rem | 400, buttons 500, primary 600 | body | navigation items too |
| Meta | 0.8125rem | 400 or 500 | body | notes under a number, pager, footer links, period switch, submenu items |
| Pill, eyebrow, small print | 0.75rem | 600 pills, 400 otherwise | body | admin footer as well |
| Label | 0.6875rem | 600 | body | uppercase, letter-spacing 0.08em: table heads, dashboard block titles |
| Role label | 0.6875rem | 600 | body | uppercase, letter-spacing 0.12em: the line under the store name |

Numbers that line up in columns use `font-variant-numeric: tabular-nums`. Muted text is never
smaller than 0.75rem and never lighter than `--sw-muted`, which keeps it at 4.5:1 on the base.

## Space

The scale is 4px steps written in rem: 0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 2.5 and 3rem. Where
each one goes:

| Where | Value |
|---|---|
| Screen padding | 1.5rem top, 2.5rem sides, 3rem bottom; 1rem and 0.75rem under 782px |
| Gap between a page's sections | 1.5rem to 2rem |
| Card padding, table cell sides | 1.25rem |
| Gap inside a card | 0.75rem to 1rem |
| Table cell top and bottom, notice margin | 0.75rem |
| Gap between actions or chips | 0.5rem |
| Sidebar width | 248px, 36px folded or under 960px, 190px under 782px |
| Navigation item | 2.25rem tall, 0.5rem from the sidebar edge, 2px apart |
| Content surface | 0.5rem from the viewport, 12px corners |
| Form column beside a canvas (brand, areas) | 20rem to 28rem or 30rem, the map or preview sticky 1rem from the top; stacks under 1100px |

## Controls

Inputs, selects and buttons on a screen share WordPress's stock heights so they line up in a row.
Storefront inputs are 3rem for shoppers' thumbs.

| Control | Recipe |
|---|---|
| Input | surface background, 1px `--sw-line` border (1.5px on the storefront), `--sw-radius-small`, body typeface. Focus: `--sw-accent` border with a 1px ring of the same (3px `--sw-accent-soft` ring on the storefront). Error: `--sw-sale` border |
| Primary button | `--sw-accent` background, `--sw-on-accent` text, weight 600, `--sw-radius-small`, no shadow. Hover and focus: `--sw-accent-hover` |
| Secondary button | surface background, `--sw-line` border, contrast text, weight 500. Hover and focus: accent border and accent text |
| Row action | a plain link in the last cell or under the item, accent text, weight 600; `.sw-quiet` is muted and turns contrast on hover. `.button-link-delete` is sale text for the destructive one |
| Views and switches | WordPress's `.subsubsub` list for the views of a table; the dashboard period switch (`.sw-dash__periods`) is one row of links on a 6% contrast tint with 3px padding, the active one on the surface with a 1px shadow and weight 600 |
| Checkbox and radio | drawn with the accent; the admin keeps WordPress's shape with a muted border |
| Pill (`.sw-pill`) | 0.75rem, weight 600, 999px corners on `--sw-accent-soft`; `.is-quiet` on `--sw-line`, `.is-bad` sale text on a 14% sale tint, `.is-good` accent text |
| Focus | 2px accent ring on anything focused by keyboard; storefront offsets it 3px |

Rows in a table act through links or plain buttons in the last cell, never through icon buttons.

## Surfaces

| Surface | Recipe |
|---|---|
| Card (`.sw-card`, `.sw-areas__card`, `.sw-brand__card`, `.postbox`) | surface, 1px line border, `--sw-radius`, 3% shadow, 1.25rem padding. Dashboard boxes separate their blocks with a label-style title |
| List (`.wp-list-table`) | surface, line border, `--sw-radius`, clipped corners, no stripes. Heads are labels; rows hover on 40% of the soft accent |
| Stat (`.sw-dash__kpi`) | a bordered box with a meta label, a 1.5rem heading value and a note that is accent when up and sale when down |
| Queue (`.sw-dash__queues`) | a link on the soft accent with the count in the heading typeface |
| Share (`.sw-dash__shares`) | name, value and a 4px accent bar on a line track |
| Empty state (`.sw-empty`, `.sw-dash__empty`) | dashed line border and muted centred text on a page, muted text in a dashboard box |
| Notice | surface, line border, 4px left border: accent for success, sale for error, `#d4a017` for warning, muted for info; a flash notice survives the redirect after a form |
| Answer (`.sw-areas__answer`) | the delivery test's result, a soft accent box with an accent left border, sale when refused |
| Submenu, popover | surface, line border, `--sw-radius-small`, `--sw-shadow` |

## Shell

The sidebar is 248px on the base colour: the store's logo or its name in the heading typeface
over a role label, the navigation, and a footer with the signed-in user and a sign-out link above
a line, both injected into `#adminmenu` by `assets/admin.js`. Navigation items are 2.25rem tall
with `--sw-radius-small` corners, icons muted at rest; hover is a 6% contrast tint and the
current item is accent text on the soft accent. The content area is a surface 0.5rem inside the
viewport with 12px corners and a 4% shadow. There is no admin bar above 782px; below it
WordPress needs the bar for the menu toggle, so it returns there and the sidebar shrinks to
190px.

## Adding a page

A new page is a class in `storewright-core/src/Admin/` that registers a submenu under
`woocommerce`, renders stock WordPress markup (`.wrap`, `h1.wp-heading-inline`, `.subsubsub`,
`.wp-list-table`, `.button`, `.notice`) with the `sw-` classes above where the stock ones do not
reach, and handles its forms through `Admin\Action` and `Admin\Flash`. Styles go in
`admin.css` on the tokens; a value not on this page is a reason to come back and add it here.

A WordPress or WooCommerce element that does not yet wear the skin is styled in `admin.css`
with the recipe above, against the ids and classes the element has carried since WordPress 3.8,
never by changing its markup.

## Surviving updates

The skin never changes WordPress or WooCommerce markup. It is CSS over the ids and classes
their screens have carried for years, so a core or plugin update keeps working; what an update
can do is rename a class the skin targets and leave a screen half styled. Three things catch that
before a store sees it:

- Core, WooCommerce and the gateways are pinned to exact versions in `composer.json` and only
  move through the weekly Dependabot pull requests, which run the whole suite.
- The browser suite signs in as staff, opens the orders list, the product list, the product
  editor, the settings screen and the coupons list, and compares the computed styles of the
  menu, the content surface, the title, the primary button, inputs, table heads and tabs with
  the brand tokens on that page. One wrong value fails the run and names the screen, the
  element and the property, so the fix is a selector in `admin.css`.
- The same test refuses a screen without the current menu item, the brand block, a title or a
  primary button, which is what a renamed shell id looks like.

A bump that fails this way is fixed in the same pull request as the bump, never by skipping the
check. Client stores only ever receive a release tag, and a tag is cut from a green main.
