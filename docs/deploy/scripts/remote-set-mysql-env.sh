#!/bin/bash
# Point the server's .env at MySQL (client request 2026-09-26). Not run on its
# own: the "Deploy to Hostinger" workflow sends it over SSH with these
# variables defined in front of it, taken from the repository secrets, and
# remote-switch-to-mysql.sh right after it:
#   APP MYSQL_HOST MYSQL_PORT MYSQL_DATABASE MYSQL_USERNAME MYSQL_PASSWORD
# Keeps a copy of the old .env next to it and prints no secrets. Sets SQLITE
# to the file the site was using, for the switch script.
set -euo pipefail
cd "$APP"

if grep -q '^DB_CONNECTION=mysql' .env; then
  echo "The site already runs on MySQL. Not switching again: it would replace"
  echo "the MySQL data with the old SQLite data."
  exit 3
fi

for name in MYSQL_HOST MYSQL_PORT MYSQL_DATABASE MYSQL_USERNAME MYSQL_PASSWORD; do
  value=${!name}
  if [ -z "$value" ]; then echo "The $name secret is empty."; exit 1; fi
  case "$value" in
    *"'"* | *$'\n'*) echo "The $name secret contains a ' or a line break, which .env cannot hold safely here."; exit 1 ;;
  esac
done

# The SQLite file the site uses now, whatever .env called it.
old=$(grep '^DB_DATABASE=' .env | tail -n 1 | cut -d= -f2- | tr -d "\"'" || true)
case "$old" in
  "") old=$APP/database/database.sqlite ;;
  /*) ;;
  *) old=$APP/$old ;;
esac
test -f "$old" || old=$APP/database/database.sqlite
SQLITE=$old
echo "== SQLite file in use: $SQLITE"

backup=".env.before-mysql-$(date +%Y%m%d-%H%M%S)"
cp -p .env "$backup"
chmod 600 "$backup"

grep -v -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD|DB_SOCKET)=' .env > .env.new
{
  echo
  echo "# ---- MySQL (switched $(date +%Y-%m-%d)); the SQLite settings are in $backup ----"
  echo "DB_CONNECTION=mysql"
  echo "DB_HOST='$MYSQL_HOST'"
  echo "DB_PORT='$MYSQL_PORT'"
  echo "DB_DATABASE='$MYSQL_DATABASE'"
  echo "DB_USERNAME='$MYSQL_USERNAME'"
  echo "DB_PASSWORD='$MYSQL_PASSWORD'"
} >> .env.new
chmod 600 .env.new
mv .env.new .env
echo "== .env now points at MySQL (old copy: $backup)"
