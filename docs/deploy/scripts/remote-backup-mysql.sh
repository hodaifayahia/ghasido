#!/bin/bash
# Dump the live MySQL database before a deploy (the live database is MySQL
# since 2026-09-26; remote-backup-db.sh only snapshots the old SQLite file).
# Credentials go through a chmod-600 temp option file, never the command line,
# and nothing secret is printed. Backups land in database/backups/.
#
#   bash docs/deploy/scripts/run-remote.sh docs/deploy/scripts/remote-backup-mysql.sh
set -euo pipefail
APP=/home/u673635734/domains/ghasido.com/guesvia
cd "$APP"
mkdir -p database/backups

# .env values are wrapped in single quotes on this server.
val() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e "s/^'//" -e "s/'\$//" -e 's/^"//' -e 's/"$//'; }

test "$(val DB_CONNECTION)" = mysql || { echo "DB_CONNECTION is not mysql; nothing dumped"; exit 1; }

cnf=$(mktemp)
chmod 600 "$cnf"
trap 'rm -f "$cnf"' EXIT
{
  echo '[client]'
  echo "user=$(val DB_USERNAME)"
  printf 'password="%s"\n' "$(val DB_PASSWORD)"
  host=$(val DB_HOST); [ -n "$host" ] && echo "host=$host"
  port=$(val DB_PORT); [ -n "$port" ] && echo "port=$port"
} > "$cnf"

out="database/backups/mysql-$(date +%Y%m%d-%H%M%S).sql.gz"
mysqldump --defaults-extra-file="$cnf" --single-transaction --quick --no-tablespaces "$(val DB_DATABASE)" | gzip > "$out"
chmod 640 "$out"

echo "== backup"
ls -la "$out"
echo "tables in dump: $(gunzip -c "$out" | grep -c '^CREATE TABLE')"
echo "== pending migrations on the live code"
php artisan migrate:status --no-interaction 2>&1 | grep -i pending || echo "(none)"
