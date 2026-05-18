#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

cd /var/www/html

APP_TZ="${TZ:-America/La_Paz}"
if [ -f /var/www/html/.env ]; then
    ENV_TZ=$(sed -n 's/^TIMEZONE="\{0,1\}\([^"#]*\)"\{0,1\}$/\1/p' /var/www/html/.env | head -n 1)
    if [ -n "${ENV_TZ:-}" ]; then
        APP_TZ="$ENV_TZ"
    fi
fi

export TZ="$APP_TZ"
if [ -f "/usr/share/zoneinfo/$APP_TZ" ]; then
    ln -snf "/usr/share/zoneinfo/$APP_TZ" /etc/localtime
    echo "$APP_TZ" > /etc/timezone
fi

mkdir -p storage/cache storage/logs
[ -f storage/news.json ] || printf "[]\n" > storage/news.json
[ -f storage/logs/update.log ] || touch storage/logs/update.log

cat > /tmp/portal-noticias-abi.cron <<EOF
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
CRON_TZ=$APP_TZ
TZ=$APP_TZ

*/10 * * * * cd /var/www/html && /usr/local/bin/php bin/update-news.php >> storage/logs/update.log 2>&1
EOF
crontab /tmp/portal-noticias-abi.cron
rm -f /tmp/portal-noticias-abi.cron

php /var/www/html/bin/update-news.php || true

cron

APP_PORT="${PORT:-3003}"
exec php -S "0.0.0.0:$APP_PORT" -t /var/www/html/public /var/www/html/router.php
