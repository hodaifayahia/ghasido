#!/bin/bash
# Snapshot the live SQLite database before a migration (VACUUM INTO gives a
# consistent copy even in WAL mode). Keeps backups in database/backups/.
set -euo pipefail
APP=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia
cd "$APP"
mkdir -p database/backups
out="database/backups/database-$(date +%Y%m%d-%H%M%S).sqlite"
php -r '$db = new PDO("sqlite:" . $argv[1]); $db->exec("VACUUM INTO " . $db->quote($argv[2]));' database/database.sqlite "$out"
chmod 640 "$out"
ls -la database/backups | tail -n 5
php artisan --version
php artisan migrate:status --no-interaction 2>&1 | tail -n 4
