#!/bin/bash
# Post-deploy check: migrations, key pages, and errors logged since the deploy.
APP=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia
URL=https://lightgrey-dinosaur-781122.hostingersite.com
cd "$APP" || exit 1
echo "== migrations"
php artisan migrate:status --no-interaction 2>&1 | tail -n 3
echo "== pages"
for p in / /login /up /owner/login /owner; do
  printf '%-14s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url} %{time_total}s' --max-time 60 "$URL$p")"
done
echo "== routes"
php artisan route:list --path=owner 2>&1 | grep -c 'owner\.' | sed 's/^/owner routes: /'
echo "== errors logged in the last 20 minutes"
since=$(date -d '-20 minutes' '+%Y-%m-%d %H:%M')
awk -v s="[$since" '/^\[20[0-9-]+ [0-9:]+\]/ { keep = ($0 >= s) } keep && /\.(ERROR|CRITICAL|ALERT|EMERGENCY)/' storage/logs/laravel-*.log | cut -c1-300 | tail -n 10
echo "(end)"
