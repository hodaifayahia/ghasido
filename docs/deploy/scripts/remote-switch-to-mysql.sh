#!/bin/bash
# One-time switch of the live site from SQLite to MySQL (client request
# 2026-09-26). Run with run-remote.sh AFTER:
#   1. creating the MySQL database and user in hPanel → Databases, and
#   2. setting these in the server's .env (keep a copy of the old two lines):
#        DB_CONNECTION=mysql
#        DB_HOST=localhost            (the host hPanel shows)
#        DB_PORT=3306
#        DB_DATABASE=u673635734_xxx
#        DB_USERNAME=u673635734_xxx
#        DB_PASSWORD=...
#   3. deploying the code that contains db:copy-sqlite-to-mysql.
#
# The SQLite file is only read, never changed, so rolling back is putting the
# two old lines back in .env (DB_CONNECTION=sqlite and DB_DATABASE=<path>)
# and running `php artisan optimize`. Safe to run again: the MySQL tables are
# emptied and refilled from SQLite each time. Prints no secrets.
set -euo pipefail
APP=${APP:-/home/u673635734/domains/ghasido.com/guesvia}
SQLITE=${SQLITE:-$APP/database/database.sqlite}
cd "$APP"

grep -q '^DB_CONNECTION=mysql' .env || { echo "Set DB_CONNECTION=mysql and the DB_* lines in .env first."; exit 1; }
test -f "$SQLITE" || { echo "SQLite file not found: $SQLITE"; exit 1; }
commands=$(php artisan list --raw)
grep -q '^db:copy-sqlite-to-mysql' <<<"$commands" || { echo "Deploy the code with db:copy-sqlite-to-mysql first."; exit 1; }

on_error() {
  echo
  echo "!! The switch stopped. The site stays in maintenance mode."
  echo "   To go back to SQLite: in .env set DB_CONNECTION=sqlite and DB_DATABASE=$SQLITE,"
  echo "   then run: php artisan optimize && php artisan up"
}
trap on_error ERR

# Maintenance first: the live config is cached, so until the next line the
# site still runs on SQLite. Once the cache is cleared it would read the new
# (still empty) MySQL settings, so no visitor may get through from here on.
echo "== maintenance mode"
php artisan down --retry=60
php artisan config:clear >/dev/null

echo "== MySQL reachable?"
if ! info=$(php artisan db:show 2>&1); then
  echo "$info" | tail -n 3
  echo "Cannot connect to MySQL with the .env values."
  false
fi
echo "$info" | head -n 8

echo "== stop the queue workers (nothing may write to SQLite while we copy)"
php artisan queue:restart >/dev/null 2>&1 || true
for i in $(seq 1 60); do
  pgrep -u "$(id -u)" -f '[a]rtisan queue:work' >/dev/null || break
  sleep 1
done
if pgrep -u "$(id -u)" -f '[a]rtisan queue:work' >/dev/null; then
  echo "A queue worker is still running after 60 s (a long job?). Wait for it, then run this again."
  false
fi

echo "== backup of the SQLite file"
mkdir -p database/backups
backup="database/backups/before-mysql-$(date +%Y%m%d-%H%M%S).sqlite"
php -r '$db = new PDO("sqlite:" . $argv[1]); $db->exec("VACUUM INTO " . $db->quote($argv[2]));' "$SQLITE" "$backup"
chmod 640 "$backup"
ls -la "$backup"

echo "== create the tables in MySQL"
php artisan migrate --force --no-interaction 2>&1 | tail -n 5

echo "== check, then copy every row"
php artisan db:copy-sqlite-to-mysql --sqlite-path="$SQLITE" --dry-run | tail -n 2
php artisan db:copy-sqlite-to-mysql --sqlite-path="$SQLITE" --fresh | tail -n 4

echo "== caches"
php artisan optimize 2>&1 | tail -n 3

trap - ERR
php artisan up

echo "== smoke (from the server)"
URL=$(grep '^APP_URL=' .env | cut -d= -f2-)
for p in / /login /up /contact; do
  printf '%-10s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code} %{time_total}s' --max-time 60 "$URL$p")"
done
echo
echo "Now restart the workers: run-remote.sh docs/deploy/scripts/remote-workers.sh"
