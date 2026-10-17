# Branding

Every surface a client or their shoppers see is styled from one set of choices: the store name,
the logo, four colours, two typefaces and a corner style. Staff change them in the console under
Brand. The same choices reach:

- the storefront, through the theme's palette, typefaces and corner sizes (`theme.json`)
- the checkout and account pages, which use the same theme tokens
- the sign-in page and the regular admin, through CSS variables and the admin colour
- the console, whose screens read the same variables
- transactional emails, which take the colours and the closest email-safe typeface
- the browser's `theme-color`, so the address bar matches on phones

## Choices

| Setting | What it does |
|---|---|
| Accent | Buttons, links, highlights, the admin colour and the email header |
| Background | The page behind everything. Cards sit on white, or on a lighter shade when the background is dark |
| Text | Body text. Saving refuses a text colour with less than 4.5:1 contrast against the background |
| Sale | Sale badges and warnings |
| Headings, Body text | A typeface each from the bundled list |
| Corners | Sharp, soft or round. Round also makes buttons pills |

Derived shades (hover, soft tint, line, muted text, the text colour on top of the accent) are
worked out from these, so a client never has to pick eleven colours.

The store description (the site tagline, up to 160 characters) is what search results and link
previews show for the home page; see [seo.md](seo.md).

Two more pieces of the storefront come from the same screen: the announcement line above the
header (option `storewright_announcement`, hidden when empty) and the home page hero photograph
(option `storewright_hero_image`, an attachment id; without one the hero sits on the soft accent
tint).

## Typefaces

The list is the system typeface plus six open typefaces under the SIL Open Font License, bundled
as variable fonts: Inter, Manrope, Space Grotesk, Fraunces, Playfair Display and Source Serif.
A new store starts with Fraunces for headings and Inter for text.
Their files are not in git. `npm run fonts` copies the Latin subsets and licences from the
`@fontsource-variable` packages into `web/app/themes/storewright/assets/fonts/`; `make console`
runs it, the image build runs it, and CI runs it. Only the typefaces a store uses are loaded on
its pages.

## Emails

Order and account emails use WooCommerce's own templates with the brand applied on top. The logo
heads each email, or the store name as a wordmark when there is no logo. Titles take the heading
typeface and text the body typeface, each as its closest email-safe family (Georgia for serif
faces, Helvetica for sans faces), because most mail apps ignore web fonts. Colours come from the
palette: the accent for buttons and links, the background behind the card, text and muted text.

Order received, on hold and completed emails end with a "View your order" button: the account
order page for customers with an account, the order confirmation page for guests. The new order
email to staff links the order number to the order in the console.

To see them, place an order on the local stack and open Mailpit on `http://localhost:8601`.

## Where the choices live

The option `storewright_brand` holds the colours, typefaces and corner style. The name is the
site title (`blogname`) and the logo is the site logo, as everywhere else in WordPress.

Read or change them over REST (`manage_woocommerce`):

```
GET /wp-json/storewright/v1/brand
PUT /wp-json/storewright/v1/brand   {"accent":"#7A1F5C","headingFont":"fraunces","radius":"round","name":"Lila","logo":42,"announcement":"Free delivery over 999","heroImage":57}
```

or from the command line:

```
wp storewright brand show
wp storewright brand set accent=#7A1F5C headingFont=fraunces radius=round
wp storewright brand reset
```

## For developers

`Storewright\Platform\Brand\Settings::get()` returns the choices, `Palette::from()` the derived
colours, `Tokens::css()` the `--sw-*` CSS variables. The theme's `theme.json` keeps matching
defaults, so the storefront looks the same with the platform plugin off.

Custom CSS should use the tokens, never a literal colour:

- storefront: `var(--wp--preset--color--accent)`, `var(--wp--custom--radius)`, `var(--wp--preset--font-family--heading)`
- admin, login and console: `var(--sw-accent)`, `var(--sw-radius)`, `var(--sw-font-heading)`
