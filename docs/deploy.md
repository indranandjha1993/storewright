# Deploying a store

Each client store runs on its own server from the release images. `deploy/` is everything the
server needs: a compose file, a Caddyfile, an example `.env` and `deploy.sh`.

## Images

A release is two images with the same tag:

| Image | Holds |
|---|---|
| `ghcr.io/indranandjha1993/storewright:<version>` | PHP-FPM with WordPress, WooCommerce, the gateways and Storewright |
| `ghcr.io/indranandjha1993/storewright-web:<version>` | nginx with the same static files |

`make images VERSION=0.2.0` builds both locally; `IMAGE=` changes the name. Uploads live in a
volume shared by both, so nothing in the images changes at run time.

Pushing a `v*` tag runs the release workflow (`.github/workflows/release.yml`): it builds both
images, scans them with Trivy and stops on any critical issue that has a fix, pushes them to GHCR
and creates the GitHub release from that version's section of `CHANGELOG.md` if it does not exist
yet. It can also be started by hand for an existing tag. The images are public, so a server pulls
them without logging in.

## The server

A small Linux VPS (2 vCPU and 4 GB is plenty for most stores) with Docker and the compose plugin.
Point the store's domain at it, then copy `deploy/` over:

```
scp -r deploy/ store-server:/srv/acme-store
ssh store-server
cd /srv/acme-store
./deploy.sh init            # writes .env with fresh salts and passwords, readable by the owner only
nano .env                   # DOMAIN, WP_HOME, ADMIN_EMAIL, SITE_TITLE, mail, gateway keys
./deploy.sh up 0.2.0
```

`up` starts MariaDB, Redis, the app, nginx, the cron worker and Caddy, installs WordPress with the
store defaults, waits for `/health`, and prints the address and the staff sign-in page. The admin
password is in `.env`. Run again, it brings the containers back without reinstalling.

### TLS and proxies

With `COMPOSE_PROFILES=caddy` (the default in the example), Caddy listens on 80 and 443 and gets
certificates for `DOMAIN`. Leave the profile empty to run behind something else, such as a
Cloudflare tunnel or the host's own proxy, pointed at `WEB_BIND` (127.0.0.1:8080 by default).
nginx trusts `X-Forwarded-For` from private networks only, so the sign-in lockout and rate limit
see the shopper's address.

### Mail

Production sends through SMTP when `MAIL_HOST` is set (`MAIL_SMTP_PORT`, `MAIL_ENCRYPTION` tls or
ssl, `MAIL_USER`, `MAIL_PASSWORD`, and `MAIL_FROM` for the sender). Any transactional mail service
works. Without it, order emails are not delivered.

### Plugins

The plugins a store runs come with each release, and the admin cannot install others. Set
`PLUGIN_MANAGEMENT=open` in `.env` and restart to let the store install, update and remove its own
plugins from the advanced admin. The plugins and the theme Storewright needs stay locked either
way, their updates arrive only with a release, and themes and core cannot be installed from the
admin. Installed plugins live in the `plugins` volume, go into every backup as `plugins.tar.gz`,
and survive updates: on start the app copies the release's own plugins into the volume and leaves
the rest alone. Installed language packs live in the `languages` volume the same way. `/version`
reports the mode and how many extra plugins are installed.

### What an update keeps

An update replaces the code that ships in the image and nothing else. The database (settings,
products, orders, the brand, Site Editor changes to templates and styles, custom CSS, the settings
of any plugin), the `uploads`, `plugins` and `languages` volumes and `.env` are never written by
`deploy.sh update`; the release's own plugins and theme are refreshed in place and everything a
client added stays as it was. The update takes a backup first and rolls back on its own if the new
version does not come up healthy.

## Day to day

```
./deploy.sh status          # running and previous version, containers, /version
./deploy.sh update 0.3.0    # backup, new images, database upgrades, health check
./deploy.sh rollback        # back to the version before the last update
./deploy.sh backup          # database and uploads into backups/<date-time>/
./deploy.sh restore <name>  # put a backup back, asks first unless --yes
./deploy.sh logs
./deploy.sh wp user list    # any wp-cli command in the app container
```

`update` takes a backup, starts the new images, runs WordPress's and WooCommerce's database
upgrades, flushes rewrite rules and caches, and waits for `/health`. If the new version does not
come up healthy it switches back to the old one and exits with an error, so a bad release leaves
the store on the version that worked. Updates never run `wp storewright setup`, which would reset
store settings. Storewright's own tables upgrade themselves on the first request of a new
version; migrations only add, so rolling back is safe.

Backups keep the last `BACKUP_KEEP` (14 by default). Copy `backups/` off the server, for example
with a nightly cron entry on the host:

```
30 2 * * * cd /srv/acme-store && ./deploy.sh backup && rclone sync backups remote:acme-store
```

## Updates from this repository

A store can update itself to every release. After the release workflow publishes the images, its
deploy job goes through the servers listed in the repository variable `DEPLOY_TARGETS`, one at a
time: it copies that release's `deploy.sh`, compose file and Caddyfile to the server and runs
`./deploy.sh update <version>`, which backs up first and goes back by itself if the new version
is unhealthy. A failed server does not stop the others. Only tags and manual runs start it, never
pull requests, so forks cannot reach the secrets.

To connect a store that is already installed with `./deploy.sh up` (see The server above):

1. On the server, create a user for deployments that belongs to the `docker` group and owns the
   store's folder, and add a new SSH public key to its `authorized_keys`.
2. In the repository settings, under Environments, create an environment with a neutral name such
   as `store-3`; the name shows in the public run log, so it should not name the client. Limit it
   to tags matching `v*`.
3. Give the environment these secrets: `DEPLOY_HOST` (address), `DEPLOY_USER`, `DEPLOY_PATH` (the
   store's folder, such as `/srv/acme-store`), `DEPLOY_SSH_KEY` (the private key) and
   `DEPLOY_KNOWN_HOSTS` (the output of `ssh-keyscan <address>`).
4. Add the name to the repository variable `DEPLOY_TARGETS`, a JSON list such as
   `["store-1","store-3"]`.

Server addresses and keys live only in those secrets, never in the repository. To hold a store
on its version, remove it from `DEPLOY_TARGETS`; to approve each update by hand, add a required
reviewer to its environment.

## Keeping WordPress and WooCommerce current

Stores never update WordPress, WooCommerce or plugins from their own admin: `DISALLOW_FILE_MODS`
removes install, update and delete everywhere. New versions arrive as a release instead.

Every Monday Dependabot opens pull requests for new versions: WordPress, WooCommerce and the
Razorpay plugin together, then the JavaScript packages and the CI actions. CI runs the full suite
on each and `wp storewright templates` fails if WooCommerce changed a template the theme
overrides. Merge with a short message such as `update woocommerce to 11.3.0`, add the change to
`CHANGELOG.md`, and tag a patch release; connected stores then update on their own. Security
releases go the same way, the day they appear.

## Drill

Run on 2026-10-17 against images built from 0.2.0, as a staging store on port 8610:

1. `up 0.2.0`: installed and healthy in 13 seconds; home, `/version` and `/sign-in/` answered 200.
2. A page and an uploaded file were added, then `backup` wrote the database and uploads (60 KB).
3. `update 0.2.1` then `rollback`: `/version` showed 0.2.1 and then 0.2.0.
4. The page and file were deleted, then `restore <first backup> --yes` brought both back.
5. `up` on the running store did not reinstall.
6. `update 9.9.9` stopped before touching anything because the images did not exist.
7. `update` to a deliberately broken 0.2.9 failed its health check and the store went back to
   0.2.0 by itself, still serving.
8. With `BACKUP_KEEP=3`, older backups were removed.
