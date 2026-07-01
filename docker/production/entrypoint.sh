#!/usr/bin/env sh
set -e

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize

exec "$@"
