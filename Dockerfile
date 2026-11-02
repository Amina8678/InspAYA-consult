#
# One image, two Render services (web and worker). The role is chosen at
# start by the first argument or APP_ROLE (see docker/entrypoint.sh).
# Laravel caches are built at container start, not here: the environment
# variables only exist at runtime.

# --- Build stage: production Composer dependencies only ---------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
        --prefer-dist --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize

# --- Final stage: php-fpm + nginx + supervisor ------------------------------
FROM php:8.4-fpm-bookworm

# Newest libpq and pg_dump from the PostgreSQL project's own repository.
# Debian's libpq is 15; a newer libpq gives `sslrootcert=system` and keeps
# SNI on (libpq 14+, needed so Neon can route the connection), and a pg_dump
# older than the server refuses to dump it. A newer client can dump older servers.
ARG PG_MAJOR=18

RUN set -eux; \
    echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80retries; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg; \
    install -d /usr/share/postgresql-common/pgdg; \
    curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc; \
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        nginx supervisor gettext-base openssl \
        postgresql-client-${PG_MAJOR} libpq-dev libicu-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl opcache intl; \
    # Keep the runtime libraries, drop the headers and toolchain leftovers.
    apt-mark manual libpq5 libicu72; \
    apt-get purge -y --auto-remove libpq-dev libicu-dev gnupg; \
    rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled /etc/nginx/conf.d/default.conf

# Fail the build if an extension the app needs is missing. fileinfo and
# mbstring ship with the base image; GD, zip, bcmath and imagick are not used
# by the app (see docs/render.md). intl is: the admin media views call
# Number::fileSize(). The `pgsql` extension is not
# needed either: Laravel talks to PostgreSQL through PDO only.
RUN php -r 'foreach (["pdo_pgsql","pcntl","fileinfo","mbstring","openssl","ctype","dom","xml","tokenizer","curl","intl","Zend OPcache"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "missing extension $e\n"); exit(1); } }'

COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/site.conf.template /etc/nginx/templates/site.conf.template
COPY docker/supervisor/ /etc/supervisor/
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint.sh; \
    mkdir -p /run/php /var/log/nginx; \
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
             storage/logs storage/app/public storage/app/private bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    rm -f bootstrap/cache/*.php; \
    runuser -u www-data -- php artisan package:discover --ansi

ENV PORT=10000 \
    PHP_FPM_MAX_CHILDREN=8 \
    QUEUE_SLEEP=3

EXPOSE 10000

# No CMD: the role is the first argument, else APP_ROLE, else web.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
