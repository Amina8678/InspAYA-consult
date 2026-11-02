#!/usr/bin/env bash
# Container entrypoint for the web and worker roles.
#
# Role: first argument (`web` / `worker`) or APP_ROLE, default `web`.
# Anything else (`php artisan ...`, `bash`) is run as given, so Render's
# pre-deploy command and shell keep working through the same entrypoint.
#
# This script never touches the database schema or its data (no migrations,
# no seeding): those are a pre-deploy step and a one-time manual step.
set -euo pipefail

cd /var/www/html

role="${1:-${APP_ROLE:-web}}"

case "$role" in
    web | worker) ;;
    *) exec "$@" ;;
esac

as_app() { runuser -u www-data -- "$@"; }

# Variables arrive at runtime, so the caches are built now, not in the image.
build_caches() {
    # A stale cache baked into a rebuilt container would be wrong; start clean.
    rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php
    as_app php artisan config:cache
    as_app php artisan route:cache
    as_app php artisan view:cache
    as_app php artisan event:cache
}

render_nginx() {
    export PORT="${PORT:-10000}"
    local conf=/etc/nginx/conf.d/default.conf

    AUTH_DIRECTIVES='    # basic auth disabled'
    if [ -n "${BASIC_AUTH_USER:-}" ] && [ -n "${BASIC_AUTH_PASSWORD:-}" ]; then
        # Password goes through stdin, not argv, so it never shows in `ps`.
        umask 077
        printf '%s:%s\n' "$BASIC_AUTH_USER" "$(printf '%s' "$BASIC_AUTH_PASSWORD" | openssl passwd -apr1 -stdin)" > /etc/nginx/htpasswd
        chown www-data:www-data /etc/nginx/htpasswd
        umask 022
        # $auth_realm is "off" for /up (see nginx.conf), so the probe is exempt.
        AUTH_DIRECTIVES='    auth_basic $auth_realm;
    auth_basic_user_file /etc/nginx/htpasswd;'
    fi

    ROBOTS_SERVER='    # indexing allowed'
    ROBOTS_LOCATION=''
    ROBOTS_PHP=''
    if [ "${STAGING_NOINDEX:-0}" = "1" ]; then
        # add_header in a location replaces the server-level ones, so it is
        # repeated per location. PHP responses may already carry the header
        # (/up does), so hide the app's copy to avoid a duplicate.
        ROBOTS_SERVER='    add_header X-Robots-Tag "noindex, nofollow" always;'
        ROBOTS_LOCATION='        add_header X-Robots-Tag "noindex, nofollow" always;'
        ROBOTS_PHP='        fastcgi_hide_header X-Robots-Tag;
        add_header X-Robots-Tag "noindex, nofollow" always;'
    fi
    export AUTH_DIRECTIVES ROBOTS_SERVER ROBOTS_LOCATION ROBOTS_PHP

    envsubst '${PORT} ${AUTH_DIRECTIVES} ${ROBOTS_SERVER} ${ROBOTS_LOCATION} ${ROBOTS_PHP}' \
        < /etc/nginx/templates/site.conf.template > "$conf"
    nginx -t
}

if [ "$role" = "web" ]; then
    render_nginx

    # The persistent disk mounts over storage/app/public owned by root.
    mkdir -p storage/app/public
    chown -R www-data:www-data storage/app/public
    as_app php artisan storage:link --force

    build_caches
    exec supervisord -n -c /etc/supervisor/supervisord-web.conf
fi

# worker: no nginx, no disk. QUEUE_SLEEP is read by supervisord-worker.conf.
export QUEUE_SLEEP="${QUEUE_SLEEP:-3}"
build_caches
exec supervisord -n -c /etc/supervisor/supervisord-worker.conf
