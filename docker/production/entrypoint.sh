#!/usr/bin/env sh
set -eu

runtime_role="${CIVICLENS_RUNTIME_ROLE:-web}"

if [ "$runtime_role" = "release" ]; then
    : "${RELEASE_APP_KEY:?RELEASE_APP_KEY must be set}"
    : "${RELEASE_DB_HOST:?RELEASE_DB_HOST must be set}"
    : "${RELEASE_DB_DATABASE:?RELEASE_DB_DATABASE must be set}"
    : "${RELEASE_DB_USERNAME:?RELEASE_DB_USERNAME must be set}"
    : "${RELEASE_DB_PASSWORD:?RELEASE_DB_PASSWORD must be set}"

    export APP_KEY="$RELEASE_APP_KEY"
    export DB_CONNECTION=mysql
    export DB_HOST="$RELEASE_DB_HOST"
    export DB_PORT="${RELEASE_DB_PORT:-3306}"
    export DB_DATABASE="$RELEASE_DB_DATABASE"
    export DB_USERNAME="$RELEASE_DB_USERNAME"
    export DB_PASSWORD="$RELEASE_DB_PASSWORD"
fi

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "$runtime_role" = "web" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan optimize
fi

exec "$@"
