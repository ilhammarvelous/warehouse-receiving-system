#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    printf '%s\n' 'APP_KEY must be set before the backend can start.' >&2
    exit 1
fi

php artisan migrate --force

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
