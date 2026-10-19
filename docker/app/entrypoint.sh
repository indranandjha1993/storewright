#!/bin/sh
set -eu

sync-plugins
exec docker-php-entrypoint "$@"
