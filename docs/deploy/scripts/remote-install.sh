#!/bin/bash
# Server-side install for Guesvia on Hostinger. Prints no secrets.
# DOM (the domain folder) can be set by the caller; by default it is the one
# folder under ~/domains that holds guesvia/artisan.
set -uo pipefail
if [ -z "${DOM:-}" ]; then
  found=()
  for f in "$HOME"/domains/*/guesvia/artisan; do [ -f "$f" ] && found+=("$f"); done
  if [ "${#found[@]}" -eq 1 ]; then
    DOM=$(dirname "$(dirname "${found[0]}")")
  else
    DOM=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com
  fi
fi
APP=$DOM/guesvia
echo "== app: $APP"
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
test ! -f database/database.sqlite || chmod 640 database/database.sqlite

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
URL=$(grep '^APP_URL=' .env | cut -d= -f2- | tr -d '"')
for p in / /login /up; do
  printf '%-8s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code} %{size_download}B %{time_total}s' --max-time 60 "$URL$p")"
done
echo "== recent log errors"
ls storage/logs/ 2>/dev/null
tail -n 20 storage/logs/laravel-*.log 2>/dev/null | cut -c1-300
