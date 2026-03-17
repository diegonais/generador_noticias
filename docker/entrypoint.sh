#!/bin/sh
set -eu

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

0 */6 * * * cd /var/www/html && /usr/local/bin/php scripts/update_news.php >> storage/logs/update.log 2>&1
EOF
crontab /tmp/portal-noticias-abi.cron
rm -f /tmp/portal-noticias-abi.cron

php /var/www/html/scripts/update_news.php || true

cron

exec php -S 0.0.0.0:3003 -t /var/www/html/public /var/www/html/router.php