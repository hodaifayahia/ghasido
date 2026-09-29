#!/bin/bash
# Post-deploy check: today's migrations, key pages, and errors logged since the deploy.
APP=/home/u673635734/domains/ghasido.com/guesvia
URL=https://ghasido.com
cd "$APP" || exit 1
echo "== migrations (today)"
php artisan migrate:status --no-interaction 2>&1 | grep '2026_09_25' | sed 's/\.\.\.*/ /'
php artisan migrate:status --no-interaction 2>&1 | grep -i pending || echo "(none pending)"
echo "== pages"
for p in / /login /up /owner/login /owner /dashboard; do
  printf '%-14s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url} %{time_total}s' --max-time 60 "$URL$p")"
done
echo "== errors logged in the last 20 minutes"
since=$(date -d '-20 minutes' '+%Y-%m-%d %H:%M')
awk -v s="[$since" '/^\[20[0-9-]+ [0-9:]+\]/ { keep = ($0 >= s) } keep && /\.(ERROR|CRITICAL|ALERT|EMERGENCY)/' storage/logs/laravel-*.log | cut -c1-300 | tail -n 10
echo "(end)"
