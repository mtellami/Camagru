#!/bin/bash
set -Eeuo pipefail

PORT=${PORT:-8080}
[[ "$PORT" =~ ^[0-9]+$ ]] && (( PORT > 0 && PORT < 65536 )) || exit 1
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
# Fail with an actionable message before launching either server.
php -r '
require "/var/www/html/src/bootstrap.php";
try {
    Camagru\Config::validateStartup();
    file_put_contents("/etc/msmtprc", Camagru\Mail\Smtp::configuration());
} catch (Throwable $error) {
    fwrite(STDERR, "Configuration error: " . $error->getMessage() . PHP_EOL);
    exit(1);
}
'
chown root:www-data /etc/msmtprc
chmod 640 /etc/msmtprc

# Both masters receive graceful shutdown signals; Docker's init reaps orphans.
shutdown() {
    trap '' TERM INT QUIT
    kill -QUIT "$php_pid" "$nginx_pid" 2>/dev/null || true
    wait "$php_pid" 2>/dev/null || true
    wait "$nginx_pid" 2>/dev/null || true
}

# Free Render services have no pre-deploy command; migrations are serialized in SQL.
if [[ "${AUTO_MIGRATE:-0}" == 1 ]]; then
    DB_HOST="${DB_DIRECT_HOST:-${DB_HOST}}" php /var/www/html/bin/migrate.php
fi

nginx -t
php-fpm --test

php-fpm --nodaemonize &
php_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!

trap 'shutdown; exit 0' TERM INT QUIT

# If either server exits, stop its peer and fail the container.
status=0
wait -n "$php_pid" "$nginx_pid" || status=$?
shutdown
if [ "$status" -eq 0 ]; then status=1; fi
exit "$status"
