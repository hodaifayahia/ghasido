#!/bin/bash
# After the site moved to ghasido.com (2026-09-25): Hostinger renamed the
# domain folder, so .env and the cached config still pointed at the old
# folder and URL. Rewrites them, rebuilds the caches and restarts the queue
# workers from the new folder. Keeps a dated copy of the old .env.
set -uo pipefail
OLD=lightgrey-dinosaur-781122.hostingersite.com
NEW=ghasido.com
APP=/home/u673635734/domains/$NEW/guesvia
cd "$APP" || { echo "no app at $APP"; exit 1; }

echo "== .env"
cp -p .env ".env.bak-$(date +%Y%m%d-%H%M%S)"
sed -i "s#$OLD#$NEW#g" .env
chmod 600 .env
grep -E '^(APP_URL|DB_DATABASE)=' .env
grep -c "$OLD" .env | sed 's/^/old-domain lines left: /'

echo "== caches"
php artisan optimize:clear >/dev/null 2>&1
php artisan optimize 2>&1 | tail -n 5
grep -c "$OLD" bootstrap/cache/config.php | sed 's/^/old-domain paths in cached config: /'

echo "== old queue workers (running from the old folder)"
for pid in $(pgrep -u "$(id -u)" -f '[a]rtisan queue:work'); do
  cwd=$(readlink "/proc/$pid/cwd" 2>/dev/null)
  case "$cwd" in
    *"$OLD"*|*"$NEW/guesvia"*) echo "stopping $pid ($cwd)"; kill "$pid" ;;
    *) echo "leaving $pid ($cwd)" ;;
  esac
done
sleep 2
for pid in $(pgrep -u "$(id -u)" -f '[f]lock -n storage/framework/queue-'); do
  cwd=$(readlink "/proc/$pid/cwd" 2>/dev/null)
  case "$cwd" in *"$OLD"*|*"$NEW/guesvia"*) kill "$pid" 2>/dev/null ;; esac
done
