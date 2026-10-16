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

## Typefaces

The list is the system typeface plus six open typefaces under the SIL Open Font License, bundled
as variable fonts: Inter, Manrope, Space Grotesk, Fraunces, Playfair Display and Source Serif.
Their files are not in git. `npm run fonts` copies the Latin subsets and licences from the
`@fontsource-variable` packages into `web/app/themes/storewright/assets/fonts/`; `make console`
runs it, the image build runs it, and CI runs it. Only the typefaces a store uses are loaded on
its pages.

## Where the choices live

The option `storewright_brand` holds the colours, typefaces and corner style. The name is the
site title (`blogname`) and the logo is the site logo, as everywhere else in WordPress.

Read or change them over REST (`manage_woocommerce`):

```
GET /wp-json/storewright/v1/brand
PUT /wp-json/storewright/v1/brand   {"accent":"#7A1F5C","headingFont":"fraunces","radius":"round","name":"Lila","logo":42}
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
