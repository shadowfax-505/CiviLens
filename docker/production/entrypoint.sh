#!/usr/bin/env sh
set -eu

runtime_role="${CIVICLENS_RUNTIME_ROLE:-web}"

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "$runtime_role" = "web" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan optimize
fi

exec "$@"
