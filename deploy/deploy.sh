#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
STATE="$DIR/.deploy"
BACKUPS="$DIR/backups"
mkdir -p "$STATE" "$BACKUPS"

env_value() {
  grep -E "^$1=" "$DIR/.env" | tail -1 | cut -d= -f2-
}

compose() {
  docker compose --progress quiet -f "$DIR/docker-compose.yml" --env-file "$DIR/.env" "$@"
}

current() {
  cat "$STATE/current" 2>/dev/null || true
}

use_version() {
  [ -n "$1" ] || { echo "no version given" >&2; exit 1; }
  export STOREWRIGHT_VERSION="$1"
}

pull() {
  compose pull --quiet app web 2>/dev/null || {
    local image
    image="$(env_value STOREWRIGHT_IMAGE)"
    docker image inspect "$image:$STOREWRIGHT_VERSION" "$image-web:$STOREWRIGHT_VERSION" >/dev/null \
      || { echo "images for $STOREWRIGHT_VERSION are neither in the registry nor on this machine" >&2; exit 1; }
  }
}

healthy() {
  for _ in $(seq 1 30); do
    if compose exec -T web wget -qO- http://127.0.0.1/health >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

migrate() {
  compose exec -T app wp core update-db --quiet
  compose exec -T app wp wc update >/dev/null
  compose exec -T app wp rewrite flush --quiet
  compose exec -T app wp cache flush --quiet
}

secret() {
  LC_ALL=C tr -dc 'A-Za-z0-9!#%+=?@^_~' </dev/urandom | head -c "${1:-64}"
}

cmd_init() {
  if [ -f "$DIR/.env" ]; then
    echo ".env already exists, leaving it alone"
    return
  fi
  cp "$DIR/.env.example" "$DIR/.env"
  for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
    sed -i.bak "s|^$key=.*|$key=$(secret 64)|" "$DIR/.env"
  done
  for key in DB_PASSWORD DB_ROOT_PASSWORD ADMIN_PASSWORD; do
    sed -i.bak "s|^$key=.*|$key=$(secret 24 | tr -dc 'A-Za-z0-9')|" "$DIR/.env"
  done
  rm -f "$DIR/.env.bak"
  chmod 600 "$DIR/.env"
  echo "wrote .env with fresh secrets; set DOMAIN, WP_HOME, ADMIN_EMAIL and mail before ./deploy.sh up"
}

# The app copies its plugins into the shared volume as it starts; nothing may run inside it before it answers.
started() {
  healthy || { echo "the store did not answer /health" >&2; return 1; }
}

cmd_up() {
  use_version "${1:-$(current)}"
  pull
  compose up -d --remove-orphans
  started || exit 1
  if ! compose exec -T app wp core is-installed >/dev/null 2>&1; then
    compose exec -T app bin/install
  fi
  healthy || { echo "the store did not answer /health" >&2; exit 1; }
  echo "$STOREWRIGHT_VERSION" >"$STATE/current"
  [ -n "$(env_value MAIL_HOST)" ] || echo "MAIL_HOST is empty, so the store cannot send order or account emails until it is set" >&2
  echo "store $STOREWRIGHT_VERSION is up at $(env_value WP_HOME), staff sign in at $(env_value WP_HOME)/$(env_value LOGIN_PATH)/"
}

cmd_update() {
  local from to
  from="$(current)"
  to="${1:?usage: deploy.sh update <version>}"
  [ -n "$from" ] || { echo "nothing deployed yet, use ./deploy.sh up $to" >&2; exit 1; }
  [ "$from" != "$to" ] || { echo "already on $to"; exit 0; }
  use_version "$to"
  pull
  use_version "$from"
  cmd_backup
  use_version "$to"
  compose up -d --remove-orphans
  if started && migrate && healthy; then
    echo "$from" >"$STATE/previous"
    echo "$to" >"$STATE/current"
    echo "updated $from -> $to"
    return
  fi
  echo "update to $to failed its health check, going back to $from" >&2
  use_version "$from"
  compose up -d --remove-orphans
  healthy && echo "back on $from" >&2
  exit 1
}

cmd_rollback() {
  local to
  to="$(cat "$STATE/previous" 2>/dev/null || true)"
  [ -n "$to" ] || { echo "no earlier version recorded" >&2; exit 1; }
  local from
  from="$(current)"
  use_version "$to"
  pull
  compose up -d --remove-orphans
  if started && compose exec -T app wp cache flush --quiet && healthy; then
    echo "$from" >"$STATE/previous"
    echo "$to" >"$STATE/current"
    echo "rolled back to $to"
    return
  fi
  echo "$to did not come up, staying on $from" >&2
  use_version "$from"
  compose up -d --remove-orphans
  healthy && echo "still on $from" >&2
  exit 1
}

cmd_backup() {
  [ -n "${STOREWRIGHT_VERSION:-}" ] || use_version "$(current)"
  local stamp target
  stamp="$(date +%Y%m%d-%H%M%S)"
  target="$BACKUPS/$stamp"
  mkdir -p "$target"
  compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' | gzip >"$target/db.sql.gz"
  compose exec -T app tar -C /var/www/html/web/app -czf - uploads >"$target/uploads.tar.gz"
  compose exec -T app tar -C /var/www/html/web/app -czf - plugins languages >"$target/plugins.tar.gz"
  echo "$STOREWRIGHT_VERSION" >"$target/version"
  local keep
  keep="$(env_value BACKUP_KEEP || true)"
  ls -1d "$BACKUPS"/*/ | sort -r | tail -n +"$(( ${keep:-14} + 1 ))" | while read -r old; do rm -rf "$old"; done
  echo "backup $stamp written ($(du -sh "$target" | cut -f1))"
}

cmd_restore() {
  local source="${1:?usage: deploy.sh restore <backup folder>}"
  [ -d "$source" ] || source="$BACKUPS/$source"
  [ -f "$source/db.sql.gz" ] && [ -f "$source/uploads.tar.gz" ] || { echo "$source has no db.sql.gz and uploads.tar.gz" >&2; exit 1; }
  if [ "${2:-}" != "--yes" ]; then
    read -r -p "Replace the store's database and uploads with $(basename "$source")? Type restore: " answer
    [ "$answer" = "restore" ] || { echo "nothing changed"; exit 1; }
  fi
  use_version "$(current)"
  gunzip -c "$source/db.sql.gz" | compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
  compose exec -T app sh -c 'find /var/www/html/web/app/uploads -mindepth 1 -delete && tar -C /var/www/html/web/app -xzf -' <"$source/uploads.tar.gz"
  if [ -f "$source/plugins.tar.gz" ]; then
    compose exec -T app sh -c 'find /var/www/html/web/app/plugins /var/www/html/web/app/languages -mindepth 1 -delete && tar -C /var/www/html/web/app -xzf -' <"$source/plugins.tar.gz"
    compose exec -T app sync-plugins
  fi
  compose exec -T app wp cache flush --quiet
  healthy || { echo "the store did not answer /health after the restore" >&2; exit 1; }
  echo "restored $(basename "$source")"
}

cmd_status() {
  use_version "$(current)"
  echo "running: $(current)  previous: $(cat "$STATE/previous" 2>/dev/null || echo none)"
  compose ps
  compose exec -T web wget -qO- http://127.0.0.1/version || true
  echo
}

case "${1:-}" in
  init) cmd_init ;;
  up) shift; cmd_up "${1:-}" ;;
  update) shift; cmd_update "${1:-}" ;;
  rollback) cmd_rollback ;;
  backup) cmd_backup ;;
  restore) shift; cmd_restore "${1:-}" "${2:-}" ;;
  status) cmd_status ;;
  logs) use_version "$(current)"; compose logs --tail=100 -f app web ;;
  wp) shift; use_version "$(current)"; compose exec app wp "$@" ;;
  *) echo "usage: deploy.sh init | up <version> | update <version> | rollback | backup | restore <backup> [--yes] | status | logs | wp <args>" >&2; exit 1 ;;
esac
