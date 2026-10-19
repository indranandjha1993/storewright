#!/bin/sh
set -eu

source=/opt/storewright-plugins
target=/var/www/html/web/app/plugins
version="${STOREWRIGHT_VERSION:-dev}"

[ -d "$source" ] || exit 0

lock="$target/.sync-lock"
for _ in $(seq 1 60); do
  mkdir "$lock" 2>/dev/null && break
  sleep 1
done
trap 'rmdir "$lock" 2>/dev/null || true' EXIT

for path in "$source"/*/; do
  name="$(basename "$path")"
  marker="$target/$name/.storewright-image"
  if [ -f "$marker" ] && [ "$(cat "$marker")" = "$version" ]; then
    continue
  fi
  rm -rf "$target/$name.next"
  cp -R "$path" "$target/$name.next"
  echo "$version" >"$target/$name.next/.storewright-image"
  rm -rf "$target/$name"
  mv "$target/$name.next" "$target/$name"
done
