#!/bin/sh
set -e

# Runs as root first so it can fix ownership on a freshly-mounted volume,
# then drops to the unprivileged `app` user for everything else.

APP_USER=app
DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"

if [ "$(id -u)" = '0' ]; then
    if [ "${DB_CONNECTION:-sqlite}" = 'sqlite' ]; then
        mkdir -p "$(dirname "$DB_PATH")"
        [ -f "$DB_PATH" ] || touch "$DB_PATH"
        chown "$APP_USER:$APP_USER" "$(dirname "$DB_PATH")" "$DB_PATH"
    fi

    chown -R "$APP_USER:$APP_USER" storage bootstrap/cache

    exec su-exec "$APP_USER" "$0" "$@"
fi

# Bootstrapping only applies when this container is starting the web server.
# One-off commands (`php artisan pq:create-user`, `key:generate --show`) must
# run untouched — the key check below would otherwise make generating a key
# impossible, since you need the container to produce one in the first place.
is_server_start() {
    [ "$#" -eq 0 ] && return 0
    [ "${1#-}" != "$1" ] && return 0
    [ "$1" = 'frankenphp' ] && return 0

    return 1
}

if is_server_start "$@"; then
    if [ -z "${APP_KEY:-}" ]; then
        echo 'Prompt Queue: APP_KEY is not set.' >&2
        echo 'Generate one with:' >&2
        echo '  docker compose run --rm app php artisan key:generate --show' >&2
        exit 1
    fi

    # Cached at boot rather than baked into the image, so the running config
    # reflects the environment this container was actually started with.
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    php artisan migrate --force --no-interaction

    if [ "${1#-}" != "$1" ] || [ "$#" -eq 0 ]; then
        set -- frankenphp run "$@"
    fi
fi

exec "$@"
