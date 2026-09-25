#!/bin/bash
# Server-side install for Guesvia on Hostinger. Prints no secrets.
set -uo pipefail
DOM=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com
APP=$DOM/guesvia
cd "$APP" || exit 1
chmod 600 .env

echo "== composer install"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1 | tail -n 12
test -f vendor/autoload.php || { echo "COMPOSER FAILED"; exit 1; }

echo "== platform check"
composer check-platform-reqs --no-dev 2>&1 | grep -v -E ' success$' | tail -n 8

echo "== artisan"
php artisan --version
php artisan migrate --force --no-interaction 2>&1 | tail -n 5

echo "== storage link (shell; PHP symlink() is disabled here)"
ln -sfn ../storage/app/public public/storage
ls -la public/storage

echo "== permissions"
chmod -R u+rwX storage bootstrap/cache database
chmod 640 database/database.sqlite

echo "== document root"
if [ -d "$DOM/public_html" ] && [ ! -L "$DOM/public_html" ]; then
  mv "$DOM/public_html" "$DOM/public_html.hostinger-default-$(date +%Y%m%d%H%M)"
fi
ln -sfn guesvia/public "$DOM/public_html"
ls -la "$DOM"

echo "== caches"
php artisan optimize:clear >/dev/null 2>&1
php artisan optimize 2>&1 | tail -n 8

echo "== smoke (from the server)"
for p in / /login /up; do
  printf '%-8s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code} %{size_download}B %{time_total}s' --max-time 60 "https://lightgrey-dinosaur-781122.hostingersite.com$p")"
done
echo "== recent log errors"
ls storage/logs/ 2>/dev/null
tail -n 20 storage/logs/laravel-*.log 2>/dev/null | cut -c1-300
