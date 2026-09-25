#!/bin/bash
# Replace the 55-minute workers with open-ended bridge workers (same locks the cron will use).
APP=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia
cd "$APP" || exit 1
php artisan queue:restart >/dev/null 2>&1   # current workers finish their job and exit
for i in $(seq 1 20); do
  pgrep -u "$(id -u)" -f '[a]rtisan queue:work' >/dev/null || break
  sleep 1
done
setsid nohup flock -n storage/framework/queue-main.lock \
  /usr/bin/php artisan queue:work database --queue=interactive,default --sleep=1 --tries=3 --timeout=900 \
  >> storage/logs/queue-main.log 2>&1 < /dev/null &
setsid nohup flock -n storage/framework/queue-media.lock \
  /usr/bin/php artisan queue:work database --queue=media --sleep=3 --tries=3 --timeout=900 \
  >> storage/logs/queue-media.log 2>&1 < /dev/null &
sleep 3
ps -u "$(id -u)" -o pid,etime,args | grep '[a]rtisan queue:work'
