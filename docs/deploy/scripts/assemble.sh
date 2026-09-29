#!/bin/bash
# Assemble the production tree in /tmp/guesvia-deploy/app. Prints no secrets.
set -euo pipefail
R=~/clickDz/ghasido
S=/tmp/guesvia-deploy/app
REMOTE_APP=/home/u673635734/domains/ghasido.com/guesvia
rm -rf "$S"
mkdir -p "$S"
cd "$R"

rsync -a --relative \
  --exclude='bootstrap/cache/*.php' \
  --exclude='public/build' --exclude='public/hot' --exclude='public/storage' \
  --exclude='database/*.sqlite*' \
  app bootstrap config database lang public resources routes artisan composer.json composer.lock \
  "$S/"

cp -r /tmp/guesvia-deploy/build "$S/public/build"

mkdir -p "$S/storage/app/public" "$S/storage/app/private" \
  "$S/storage/framework/cache/data" "$S/storage/framework/sessions" "$S/storage/framework/views" \
  "$S/storage/logs"
rsync -a storage/app/private/ "$S/storage/app/private/"
cp storage/app/public/.gitignore "$S/storage/app/public/.gitignore" 2>/dev/null || true
if [ "${INITIAL:-0}" = 1 ]; then cp storage/logs/deploy/database.sqlite "$S/database/database.sqlite"; fi
test ! -e storage/logs/deploy/database.sqlite-wal || echo "WARNING: wal file present"

# Production .env from the local one.
drop='^(DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD|FORWARD_[A-Z_]+|PHP_CLI_SERVER_WORKERS|REDIS_[A-Z_]+|MEMCACHED_HOST|MAIL_HOST|MAIL_PORT|MAIL_USERNAME|MAIL_PASSWORD|MAIL_SCHEME|SUPER_ADMIN_[A-Z_]+|APP_ENV|APP_DEBUG|APP_URL|LOG_STACK|LOG_LEVEL|DB_CONNECTION|DB_DATABASE|SESSION_DRIVER|SESSION_DOMAIN|SESSION_SECURE_COOKIE|CACHE_STORE|QUEUE_CONNECTION|MAIL_MAILER|BCRYPT_ROUNDS)='
grep -v -E "$drop" .env | tr -d '\r' > "$S/.env"
cat >> "$S/.env" <<EOF

# ---- production overrides (Hostinger) ----
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ghasido.com
LOG_STACK=daily
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=$REMOTE_APP/database/database.sqlite
SESSION_DRIVER=file
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=database
MAIL_MAILER=log
BCRYPT_ROUNDS=12
EOF
chmod 600 "$S/.env"

echo "== .env keys (names only)"
grep -E '^[A-Z_]+=' "$S/.env" | cut -d= -f1 | tr '\n' ' '; echo
echo "== APP_KEY present: $(grep -c '^APP_KEY=base64:' "$S/.env")"
echo "== tree"
du -sh "$S"; du -sh "$S"/* | sort -rh | head -12
ls "$S/public"
echo "files: $(find "$S" -type f | wc -l)"
