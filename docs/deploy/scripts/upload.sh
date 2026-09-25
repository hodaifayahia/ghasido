#!/bin/bash
# Upload the assembled tree (/tmp/guesvia-deploy/app) to Hostinger.
#
# Redeploy (default): code, config, resources and public/build only. The live
# SQLite database, .env and storage/ are NEVER touched, because production
# data diverges from local data as soon as people use the site.
#
# First install only: INITIAL=1 also uploads database/database.sqlite, .env,
# storage/ and the referenced media listed by deploy-export.php.
set -euo pipefail
S=/tmp/guesvia-deploy/app
R=~/clickDz/ghasido
REMOTE=u673635734@89.117.116.239
REMOTE_APP=/home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia
SSH="ssh -i $HOME/.ssh/clickdz_hostinger_deploy -p 65002 -o BatchMode=yes -o ServerAliveInterval=30"

rm -f "$S/public/fonts-manifest.dev.json"

if [ "${INITIAL:-0}" = 1 ]; then
  rsync -az --partial --stats -e "$SSH" "$S/" "$REMOTE:$REMOTE_APP/" | grep -E 'files transferred|Total bytes sent'
  rsync -a --partial --stats -e "$SSH" --files-from="$R/storage/logs/deploy/media-list.txt" \
    "$R/storage/app/public/" "$REMOTE:$REMOTE_APP/storage/app/public/" | grep -E 'files transferred|Total bytes sent'
else
  rsync -az --partial --stats -e "$SSH" \
    --exclude='/.env' --exclude='/database/*.sqlite*' --exclude='/storage/' \
    "$S/" "$REMOTE:$REMOTE_APP/" | grep -E 'files transferred|Total bytes sent'
  # Replace the whole build so stale hashed assets do not pile up.
  rsync -az --delete -e "$SSH" "$S/public/build/" "$REMOTE:$REMOTE_APP/public/build/"
fi

$SSH "$REMOTE" "cd $REMOTE_APP && du -sh . && ls -la database/database.sqlite"
