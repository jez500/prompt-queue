# syntax=docker/dockerfile:1

# Prompt Queue — production image.
#
# Three stages: composer dependencies, front-end assets, then a lean runtime.
# The asset stage needs BOTH PHP and Node, because the Wayfinder Vite plugin
# shells out to `php artisan wayfinder:generate` to emit resources/js/routes
# and resources/js/actions before Vite bundles them.

ARG FRANKENPHP_VERSION=1-php8.3-alpine

# --------------------------------------------------------------------------
# Stage 1 — PHP dependencies
# --------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --------------------------------------------------------------------------
# Stage 2 — front-end assets
# --------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS assets

RUN apk add --no-cache nodejs npm

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor ./vendor

# Wayfinder boots the framework to read the route table, which needs a key.
# This .env is scratch space for the build and is never copied into runtime.
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build \
    && rm .env

# --------------------------------------------------------------------------
# Stage 3 — runtime
# --------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS runtime

# su-exec drops privileges in the entrypoint once volume ownership is fixed.
RUN apk add --no-cache su-exec \
    && install-php-extensions opcache \
    && addgroup -g 1000 -S app \
    && adduser -u 1000 -S -G app app

WORKDIR /app

ENV SERVER_NAME=:8080 \
    SERVER_ROOT=public/ \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

# The build context ships no .env and empty storage scaffolding, so recreate
# the tree Laravel expects and hand it to the unprivileged user.
RUN rm -rf .env docker \
    && mkdir -p \
        database \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R app:app storage bootstrap/cache database \
    # Caddy provisions a local CA on boot and writes to these paths, which
    # fails once the entrypoint drops to the unprivileged user.
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /data/caddy /config/caddy

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);'

ENTRYPOINT ["entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
