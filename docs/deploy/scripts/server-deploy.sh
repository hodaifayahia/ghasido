#!/bin/bash
# Deploy from a git clone ON the Hostinger server, over SSH only: no
# developer PC and no GitHub Actions. Builds the frontend on the server
# (installs Node into ~/.local/node the first time), copies the release into
# the live app, installs it and restarts the workers. Optionally moves the
# live data from SQLite to MySQL (docs/deploy/mysql.md).
#
#   cd ~/ghasido-src && git pull            # always pull first, separately
#   bash docs/deploy/scripts/server-deploy.sh
#   SWITCH_TO_MYSQL=1 bash docs/deploy/scripts/server-deploy.sh   # one time
#
# DOMAIN=ghasido.com picks the domain folder when more than one holds an app.
# The live .env, SQLite database and storage/ are never overwritten. The
# MySQL password is asked for (not echoed) and passed on stdin only.
set -euo pipefail
SRC=$(cd "$(dirname "$0")/../../.." && pwd)
cd "$SRC"
test -f artisan || { echo "Run this from the git clone of the project."; exit 1; }

# ---- which app
if [ -n "${DOMAIN:-}" ]; then
  DOM=$HOME/domains/$DOMAIN
else
  # A glob, not <(...): this host has no /dev/fd for process substitution.
  found=()
  for f in "$HOME"/domains/*/guesvia/artisan; do [ -f "$f" ] && found+=("$f"); done
  if [ "${#found[@]}" -ne 1 ]; then
    echo "Found ${#found[@]} apps; run again with DOMAIN=<folder under ~/domains>:"
    printf '  %s\n' "${found[@]}"
    exit 1
  fi
  DOM=$(dirname "$(dirname "${found[0]}")")
fi
APP=$DOM/guesvia
test -f "$APP/artisan" || { echo "No app at $APP"; exit 1; }
echo "== deploying $(git rev-parse --short HEAD) ($(git rev-parse --abbrev-ref HEAD)) to $APP"

# ---- MySQL details up front, so the run does not stop half-way to ask
if [ "${SWITCH_TO_MYSQL:-0}" = 1 ]; then
  if grep -q '^DB_CONNECTION=mysql' "$APP/.env"; then
    echo "The site already runs on MySQL; run without SWITCH_TO_MYSQL."; exit 1
  fi
  read -rp "MySQL database name (as hPanel shows it): " MYSQL_DATABASE
  read -rp "MySQL user name (as hPanel shows it): " MYSQL_USERNAME
  read -rsp "MySQL password (not shown): " MYSQL_PASSWORD; echo
  read -rp "MySQL host [localhost]: " MYSQL_HOST
  MYSQL_HOST=${MYSQL_HOST:-localhost}
  php -r 'try { new PDO("mysql:host=".$argv[1].";dbname=".$argv[2], $argv[3], rtrim(stream_get_contents(STDIN), "\n")); echo "MySQL login OK\n"; } catch (Throwable $e) { fwrite(STDERR, "MySQL login failed: ".$e->getMessage()."\n"); exit(1); }' \
    "$MYSQL_HOST" "$MYSQL_DATABASE" "$MYSQL_USERNAME" <<<"$MYSQL_PASSWORD" || exit 1
  # The password is only in the here-string above, not on a command line.
fi

# ---- Node (Hostinger has none; the official build goes into ~/.local/node)
export PATH=$HOME/.local/node/bin:$PATH
if ! command -v node >/dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]; then
  echo "== installing Node 22 into ~/.local/node"
  tmp=$(mktemp -d)
  base=https://nodejs.org/dist/latest-v22.x
  curl -fsSL "$base/SHASUMS256.txt" -o "$tmp/sums"
  file=$(grep -o 'node-v22[^ ]*-linux-x64.tar.xz' "$tmp/sums" | head -n 1)
  curl -fsSL "$base/$file" -o "$tmp/$file"
  (cd "$tmp" && grep " $file\$" sums | sha256sum -c -)
  rm -rf "$HOME/.local/node" && mkdir -p "$HOME/.local/node"
  tar -xJf "$tmp/$file" -C "$HOME/.local/node" --strip-components=1
  rm -rf "$tmp"
fi
echo "node $(node -v), npm $(npm -v)"

# ---- build (in the clone; its own throwaway .env and SQLite file)
echo "== build"
composer install --no-dev --no-interaction --no-progress --prefer-dist 2>&1 | tail -n 3
if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --no-interaction >/dev/null
fi
php artisan migrate --force --no-interaction >/dev/null
export NODE_OPTIONS=--max-old-space-size=1536
npm ci --no-audit --no-fund 2>&1 | tail -n 2
npm run build 2>&1 | tail -n 3
test -f public/build/manifest.json || { echo "BUILD FAILED"; exit 1; }

# ---- copy the release into the live app
echo "== copy"
rsync -a --relative \
  --exclude='bootstrap/cache/*.php' \
  --exclude='public/build' --exclude='public/hot' --exclude='public/storage' \
  --exclude='public/fonts-manifest.dev.json' \
  --exclude='database/*.sqlite*' \
  app bootstrap config database public resources routes artisan composer.json composer.lock \
  "$APP/"
rsync -a --delete public/build/ "$APP/public/build/"

# ---- install, then the optional MySQL switch, then workers
DOM=$DOM bash docs/deploy/scripts/remote-install.sh

if [ "${SWITCH_TO_MYSQL:-0}" = 1 ]; then
  {
    printf 'APP=%q\n' "$APP"
    printf 'MYSQL_HOST=%q\n' "$MYSQL_HOST"
    printf 'MYSQL_PORT=%q\n' 3306
    printf 'MYSQL_DATABASE=%q\n' "$MYSQL_DATABASE"
    printf 'MYSQL_USERNAME=%q\n' "$MYSQL_USERNAME"
    printf 'MYSQL_PASSWORD=%q\n' "$MYSQL_PASSWORD"
    cat docs/deploy/scripts/remote-set-mysql-env.sh
    cat docs/deploy/scripts/remote-switch-to-mysql.sh
  } | bash -s
fi

APP=$APP bash docs/deploy/scripts/remote-workers.sh
echo "== done"
