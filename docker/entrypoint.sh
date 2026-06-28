#!/bin/sh
set -eu

APP_DIR="/var/www/html"
ROLE="${1:-web}"

wait_for_tcp() {
    host="$1"
    port="$2"
    name="$3"
    max_attempts="${4:-60}"

    attempt=1
    while [ "$attempt" -le "$max_attempts" ]; do
        if HOST="$host" PORT="$port" php -r "exit(@fsockopen(getenv('HOST'), (int) getenv('PORT')) ? 0 : 1);"; then
            echo "Connected to ${name} at ${host}:${port}"

            return 0
        fi

        echo "Waiting for ${name} at ${host}:${port} (${attempt}/${max_attempts})..."
        attempt=$((attempt + 1))
        sleep 2
    done

    echo "Timed out waiting for ${name} at ${host}:${port}" >&2

    exit 1
}

prepare_runtime() {
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is not set. Generate one with: php artisan key:generate" >&2

        exit 1
    fi

    wait_for_tcp "${DB_HOST:-mariadb}" "${DB_PORT:-3306}" "MariaDB"
    wait_for_tcp "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "Redis"

    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /tmp/nginx/client_body \
        /tmp/nginx/proxy \
        /tmp/nginx/fastcgi \
        /tmp/nginx/uwsgi \
        /tmp/nginx/scgi \
        /var/lib/nginx/logs \
        /var/lib/nginx/tmp

    chown -R app:app storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache

    php artisan migrate --force --no-interaction

    if [ "${APP_ENV:-local}" = "production" ]; then
        php artisan config:cache --no-interaction
        php artisan route:cache --no-interaction
        php artisan view:cache --no-interaction
    else
        php artisan config:clear --no-interaction
        php artisan route:clear --no-interaction
        php artisan view:clear --no-interaction
    fi
}

case "$ROLE" in
    web)
        prepare_runtime
        exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    queue)
        prepare_runtime
        exec php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
        ;;
    scheduler)
        prepare_runtime
        exec php artisan schedule:work
        ;;
    migrate)
        prepare_runtime
        ;;
    *)
        exec "$@"
        ;;
esac
