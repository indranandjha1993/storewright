# Security

A Storewright store runs with file changes disabled (`DISALLOW_FILE_MODS`), automatic updates
off (updates arrive as a new image), XML-RPC off and the database behind the application
network. The rest of this page covers what a scanner sees.

## Sign-in

Staff sign in at `/sign-in/` (set `LOGIN_PATH` to use another word). `/wp/wp-login.php` answers
404 at nginx and again in PHP, so login forms, password resets and logouts all go through the
chosen path. Shoppers keep using the account page. After five failed attempts for a name from
one address, further attempts are refused for 15 minutes, and nginx allows at most 60 requests a
minute per address on the sign-in path, with a burst of 30. Login errors never say whether the name exists.

## What is hidden

- Generator tags, RSD, Windows Live Writer, shortlink, REST discovery and oEmbed links are not
  printed; the `Link` header is dropped; `X-Redirect-By` and `X-Pingback` are not sent.
- Core and plugin asset URLs lose their `?ver=` version. Our own assets keep a version taken
  from the file, which says nothing about WordPress.
- The REST index (`/wp-json/`) lists nothing for a visitor who is not signed in. The Store API
  keeps working because the storefront calls routes directly. User routes are hidden from
  visitors, author archives answer 404 and `?author=1` does not redirect to a name.
- `readme.html`, `license.txt`, `wp-cron.php`, the installer and direct hits on files in
  `wp-includes` answer 404, as do the project's build files.
- The admin bar shows only for staff.

## Headers

Every response carries `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera and microphone
off, geolocation and payment for the store itself) and `Cross-Origin-Opener-Policy`. Over HTTPS,
`Strict-Transport-Security` is added. PHP does not announce itself and nginx hides its version.

## What stays visible

WordPress leaves fingerprints that cannot be removed without breaking the store: block class
names in HTML, `/app/` and `/wp/` paths in asset URLs, the Store API under `/wp-json/wc/store/`.
The aim is to give nothing away for free and to make the usual automated probes fail, not to
pretend the stack is something else. Keep the image current; that is the control that matters.
