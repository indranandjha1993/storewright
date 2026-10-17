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
