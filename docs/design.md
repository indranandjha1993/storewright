# Design language

The console, the advanced admin and the sign-in page are built against one spec: the tokens
below, one type scale, one spacing scale and one set of controls. The storefront shares the same
tokens through `theme.json` and adds its own larger scale for shoppers. A screen that follows this
page looks like every other screen without anyone comparing them.

Where it lives:

| Surface | Source |
|---|---|
| Tokens | `Brand\Palette` and `Brand\Tokens` in `web/app/mu-plugins/storewright-platform/src/Brand/` |
| Console shell and screens | `web/app/plugins/storewright-console/lib/_shell.scss` and `_screen.scss`, components in `lib/screen.jsx` |
| Advanced admin | `web/app/mu-plugins/storewright-platform/assets/admin.css` and `admin.js`, enqueued by `WhiteLabel\AdminSkin` |
| Storefront | `web/app/themes/storewright/style.css` (Controls, Buttons, Shell polish sections) |

## Tokens

Every colour, radius and typeface comes from the Brand screen (see [branding.md](branding.md))
and is printed as CSS variables in `<style id="storewright-tokens">` on every admin, console and
sign-in page. Nothing in the console or the admin skin uses a literal colour; the only exception
is the warning amber on notices, which no brand colour covers.

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
`--wp-components-color-accent` are set to it at `:root`, on the console stage and, with
`!important`, on every `[data-wpds-corner-radius]` root WordPress 7.1 paints its own tokens on.

## Type

Body text is 14px (`0.875rem`) in the body typeface. Everything else is one of these roles.

| Role | Size | Weight | Typeface | Notes |
|---|---|---|---|---|
| Screen title | 2rem | 600 | heading | line-height 1.1, letter-spacing -0.01em |
| Stat value | 2rem | 600 | heading | line-height 1, tabular numerals |
| Panel title | 1.375rem | 600 | heading | the title of a detail panel |
| Store name in the sidebar | 1.25rem | 600 | heading | line-height 1.2 |
| Card title | 1.125rem | 600 | heading | sections inside a screen, admin box headings, WooCommerce `h2` |
| Admin box heading | 1rem | 600 | heading | `.postbox .hndle` |
| Body, inputs, buttons | 0.875rem | 400, buttons 500, primary 600 | body | navigation items too |
| Meta | 0.8125rem | 400 or 500 | body | notes under a stat, pager, footer links, segmented buttons, submenu items |
| Pill, eyebrow, small print | 0.75rem | 600 pills, 400 otherwise | body | admin footer as well |
| Label | 0.6875rem | 600 | body | uppercase, letter-spacing 0.08em: table heads, panel block titles |
| Role label | 0.6875rem | 600 | body | uppercase, letter-spacing 0.12em: the line under the store name |

Numbers that line up in columns use `font-variant-numeric: tabular-nums`. Muted text is never
smaller than 0.75rem and never lighter than `--sw-muted`, which keeps it at 4.5:1 on the base.

## Space

The scale is 4px steps written in rem: 0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 2.5 and 3rem. Where
each one goes:

| Where | Value |
|---|---|
| Screen padding | 2rem top, 2.5rem sides, 3rem bottom; 1.25rem and 1rem under 782px |
| Gap between the screen's sections | 1.5rem |
| Panel and card padding, table cell sides | 1.25rem |
| Gap inside a panel or card | 1rem, 0.75rem in a card |
| Table cell top and bottom, pager padding, notice margin | 0.75rem |
| Gap between actions or chips | 0.5rem |
| Sidebar width | 248px (15.5rem), 36px folded or under 960px |
| Navigation item | 2.25rem tall, 0.75rem from the sidebar edge, 2px apart |
| Content surface | 0.5rem from the viewport, 12px corners, padding as the screen |
| Screen width | 80rem, or the full stage for split and table screens |
| Detail aside | 22rem to 26rem, sticky 1rem from the top; stacks under 1100px |
| Canvas sidebar (brand, areas) | 20rem to 26rem beside the canvas |

## Controls

Every input, select and button on a screen is 40px tall so they line up in a row. In the console
that is `__next40pxDefaultSize` on every `TextControl`, `SelectControl`, `SearchControl` and
`FormTokenField`; in the admin it is the stock height of a WordPress input. Storefront inputs are
3rem for shoppers' thumbs.

| Control | Recipe |
|---|---|
| Input | surface background, 1px `--sw-line` border (1.5px on the storefront), `--sw-radius-small`, body typeface. Focus: `--sw-accent` border with a 1px ring of the same (3px `--sw-accent-soft` ring on the storefront). Error: `--sw-sale` border |
| Primary button | `--sw-accent` background, `--sw-on-accent` text, weight 600, `--sw-radius-small`, no shadow. Hover and focus: `--sw-accent-hover` |
| Secondary button | surface background, `--sw-line` border, contrast text, weight 500. Hover and focus: accent border and accent text |
| Link button (`.sw-link`) | no box, accent text, weight 600, underline on hover. `.is-quiet` is muted and turns contrast on hover |
| Segmented control (`Segmented`) | one row of options on a 6% contrast tint with 3px padding; the active option sits on the surface with a 1px shadow and weight 600. One of these for every filter and switch; never a row of chips or pressed buttons |
| Checkbox and radio | drawn with the accent; the admin keeps WordPress's shape with a muted border |
| Pill (`.sw-pill`) | 0.75rem, weight 600, 999px corners on `--sw-accent-soft`; `.is-quiet` on `--sw-line`, `.is-bad` sale text on a 14% sale tint, `.is-good` accent text |
| Focus | 2px accent ring on anything focused by keyboard; storefront offsets it 3px |

Rows in a table act through links or `.sw-link` buttons, right-aligned in the last cell, never
through icon buttons.

## Surfaces

| Surface | Recipe |
|---|---|
| Panel (`.sw-panel`) | surface, 1px line border, `--sw-radius`, `--sw-shadow`, 1.25rem padding. Blocks inside separate with a 1px line and a label-style title |
| Card (`.sw-card`, admin `.postbox`, `.card`) | as a panel with a 3% shadow. `is-flush` drops the padding so a table can fill it |
| List (`.sw-list`, admin `.wp-list-table`) | surface, line border, `--sw-radius`, clipped corners, no stripes. Heads are labels; rows hover on 40% of the soft accent; the last row has no bottom border |
| Stat (`.sw-stat`) | a card with a meta label, a 2rem heading value and a note that is accent when up and sale when down |
| Empty state (`.sw-empty`) | dashed line border, muted centred text |
| Notice | surface, line border, 4px left border: accent for success, sale for error, `#d4a017` for warning, muted for info |
| Save bar (`.sw-save`) | sticky at the bottom of a form on the surface; the notice sits above the buttons |
| Submenu, popover | surface, line border, `--sw-radius-small`, `--sw-shadow` |

## Shell

Console and admin share one shell. The sidebar is 248px on the base colour: the store's logo or
its name in the heading typeface over a role label, the navigation, and a footer with the signed-in
user and a sign-out link above a line. Navigation items are 2.25rem tall with `--sw-radius-small`
corners, icons muted at rest; hover is a 6% contrast tint and the current item is accent text on
the soft accent. The content area is a surface 0.5rem inside the viewport with 12px corners and a
4% shadow. There is no admin bar above 782px; below it WordPress needs the bar for the menu
toggle, so it returns there and the sidebar shrinks to 190px.

The console's sidebar is `@wordpress/boot`'s own, styled through its `boot-*` class names from
`lib/_shell.scss`; the admin's is `#adminmenu` with the brand block and footer injected by
`assets/admin.js`. The two are kept the same by hand, which is why both sets of values are on
this page.

## Adding a screen

Build it from `Screen`, `Card`, `Segmented` and `Empty` in `lib/screen.jsx` with the classes
above; choose `split="detail"` for a list with a panel (orders, customers) and `split="canvas"`
for a form beside a preview or map (brand, areas). Use WordPress components for inputs and
buttons and give every input `__next40pxDefaultSize`. Put a screen's own styles in its
`style.scss` on the tokens; a value not on this page is a reason to come back and add it here.

When a task still runs in the advanced admin, its stock screen already wears the skin. A new
WordPress or WooCommerce element that does not is styled in `admin.css` with the recipe above,
against the ids and classes the element has carried since WordPress 3.8, never by changing its
markup.

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
