#!/usr/bin/env bash
# Throwaway app server for visual verification (spec 0003, Part I).
#
# Runs OUTSIDE Sail against its own SQLite file, so the developer database is
# never touched. Assets come from whatever Vite `public/hot` points at (the Sail
# container's Vite on :5173 serves this same source tree).
#
#   bash storage/harness/serve.sh reset   # migrate:fresh --seed, then (re)start
#   bash storage/harness/serve.sh start   # (re)start only
#   bash storage/harness/serve.sh stop
#   bash storage/harness/serve.sh status
set -euo pipefail
cd "$(dirname "$0")/../.."

# Each build lane uses its own port and database: HARNESS_PORT=8093 HARNESS_DB=~/.guesvia-harness/employees.sqlite bash storage/harness/serve.sh reset
PORT="${HARNESS_PORT:-8090}"
DB="${HARNESS_DB:-$HOME/.guesvia-harness/spec3.sqlite}"
LOG="storage/harness/php-server-${PORT}.log"
PIDFILE="storage/harness/php-server-${PORT}.pid"
mkdir -p "$HOME/.guesvia-harness" storage/harness

export APP_ENV=local APP_DEBUG=true APP_URL="http://127.0.0.1:${PORT}"
export DB_CONNECTION=sqlite DB_DATABASE="$DB"
export SESSION_DRIVER=file CACHE_STORE=array QUEUE_CONNECTION=sync
export FILESYSTEM_DISK=local MAIL_MAILER=log LOG_CHANNEL=single
export AI_PROVIDER=fake TTS_PROVIDER=fake STT_PROVIDER=fake
export PHP_CLI_SERVER_WORKERS=6

stop() {
  if [[ -f "$PIDFILE" ]]; then
    kill "$(cat "$PIDFILE")" 2>/dev/null || true
    rm -f "$PIDFILE"
  fi
  # belt and braces: anything else on the port
  pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
}

start() {
  stop
  php artisan config:clear >/dev/null 2>&1 || true
  (
    cd public
    nohup php -S "127.0.0.1:${PORT}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php \
      > "../$LOG" 2>&1 &
    echo $! > "../$PIDFILE"
  )
  sleep 1
  curl -s -o /dev/null -w "harness http://127.0.0.1:${PORT} -> %{http_code}\n" "http://127.0.0.1:${PORT}/login" || true
}

case "${1:-start}" in
  reset)
    stop
    rm -f "$DB"; touch "$DB"
    php artisan migrate:fresh --seed --force
    php artisan storage:link >/dev/null 2>&1 || true
    start
    ;;
  start) start ;;
  stop) stop; echo stopped ;;
  status)
    curl -s -o /dev/null -w "harness -> %{http_code}\n" "http://127.0.0.1:${PORT}/login" || echo "harness down"
    curl -s -o /dev/null -w "vite -> %{http_code}\n" "$(cat public/hot 2>/dev/null || echo http://localhost:5173)/@vite/client" || echo "vite down"
    ;;
  *) echo "usage: serve.sh reset|start|stop|status"; exit 1 ;;
esac
