#!/usr/bin/env sh
set -e

chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize

exec "$@"
