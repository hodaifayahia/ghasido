#!/bin/bash
# Read-only diagnosis after a domain change: where the app lives now, what
# the web root points at, which paths .env and the caches still use, and the
# latest errors. Prints no secrets (only non-secret .env keys).
cd ~ || exit 1
echo "== domains"
ls -la ~/domains/
for d in ~/domains/*/; do
  echo "--- $d"
  ls -la "$d" | head -20
done

for app in ~/domains/*/guesvia; do
  [ -d "$app" ] || continue
  echo "== app: $app"
  cd "$app" || continue
  grep -E '^(APP_URL|APP_ENV|APP_DEBUG|DB_CONNECTION|DB_DATABASE|SESSION_DOMAIN|SESSION_SECURE_COOKIE|ASSET_URL)=' .env
  ls -la public/storage database/database.sqlite 2>&1
  echo "-- cached config paths"
  [ -f bootstrap/cache/config.php ] && grep -o "/home/[^']*" bootstrap/cache/config.php | sed 's#/guesvia.*#/guesvia#' | sort | uniq -c | head
  echo "-- latest log"
  ls -t storage/logs/laravel-*.log 2>/dev/null | head -1
  f=$(ls -t storage/logs/laravel-*.log 2>/dev/null | head -1)
  [ -n "$f" ] && grep -a -E '\.(ERROR|CRITICAL)' "$f" | tail -n 5 | cut -c1-400
done

echo "== http"
for u in https://ghasido.com/ https://www.ghasido.com/ https://ghasido.com/up https://ghasido.com/up; do
  printf '%-58s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' --max-time 30 "$u")"
done
