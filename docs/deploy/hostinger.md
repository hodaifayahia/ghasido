# Deploying Guesvia to Hostinger

> **Moving to MySQL (2026-09-26):** the code runs on MySQL and the one-time move of the live data is ready. Follow [`mysql.md`](mysql.md).

First deployed 2026-09-25 to **https://lightgrey-dinosaur-781122.hostingersite.com** (Hostinger Cloud Startup, account `u673635734`, SSH `89.117.116.239:65002`). Scripts are in [`scripts/`](scripts/); they hold no secrets.

## Layout on the server

```
~/domains/lightgrey-dinosaur-781122.hostingersite.com/
├─ guesvia/                         the Laravel app (outside the web root)
│  ├─ .env                          production env, chmod 600
│  ├─ database/database.sqlite      the live database (WAL mode)
│  ├─ public/storage -> ../storage/app/public
│  └─ storage/logs/                 laravel-YYYY-MM-DD.log, queue-main.log, queue-media.log
├─ public_html -> guesvia/public    web root is a symlink to the app's public/
└─ public_html.hostinger-default-*  Hostinger's placeholder, kept aside
```

## Decisions and why

| Decision                           | Why                                                                                                                                                                                                                                                                                                                                                                   |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **SQLite, not MySQL**              | The Hostinger API available to agents lists the site but answers 404 for every account endpoint (databases, PHP settings, cron). Two other Laravel sites on this account already run on SQLite. To move to MySQL: create a DB in hPanel, set `DB_CONNECTION=mysql` + credentials in `.env`, run `php artisan migrate --force`, copy the data, `php artisan optimize`. |
| **Frontend built locally**         | The server has PHP 8.3.33 and Composer but no Node. `build-frontend.sh` builds into `/tmp/guesvia-deploy/build`, so the local `public/build` (where source images were once kept) is never emptied.                                                                                                                                                                   |
| **Only referenced media uploaded** | Local `storage/app/public` holds ~76k files (2.7 GB), mostly orphaned generated audio. `deploy-export.php` lists the ~2k files the data references (~250 MB).                                                                                                                                                                                                         |
| **Seed passwords locked**          | Every account whose password was the seed `password` got an unknown random password in the uploaded copy; the owner Super Admin got a new strong one (given to the user, not stored here).                                                                                                                                                                            |
| **`APP_KEY` shared with local**    | Encrypted columns (2FA secrets, provider settings) were copied, so the key had to match. Rotate both together if needed.                                                                                                                                                                                                                                              |
| **`MAIL_MAILER=log`**              | No SMTP configured yet; reminder emails are written to the log.                                                                                                                                                                                                                                                                                                       |

## Hostinger constraints to know

- PHP (CLI and web) disables `exec`, `shell_exec`, `proc_open`, `popen`, `symlink`. Consequences: `php artisan storage:link` fails (use `ln -sfn`), and Composer's `@php artisan package:discover` hook fails; run `php artisan package:discover` by hand after `composer install`.
- No `crontab` over SSH. Cron jobs must be added in **hPanel → Advanced → Cron Jobs**.
- The site's PHP version is 8.3 (required: `^8.3`).

## Background work (queues and scheduler)

The app needs a scheduler and two queue workers (same queues as `compose.yaml`). Add these three cron jobs in hPanel, each **every minute** (`* * * * *`):

```bash
cd /home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
cd /home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && flock -n storage/framework/queue-main.lock /usr/bin/php artisan queue:work database --queue=interactive,default --sleep=1 --tries=3 --timeout=900 --max-time=3300 >> storage/logs/queue-main.log 2>&1
cd /home/u673635734/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && flock -n storage/framework/queue-media.lock /usr/bin/php artisan queue:work database --queue=media --sleep=3 --tries=3 --timeout=900 --max-time=3300 >> storage/logs/queue-media.log 2>&1
```

`flock` keeps one worker per queue. Until the cron exists, `remote-workers.sh` starts detached "bridge" workers over SSH using the same lock files; they stop if the server restarts them or they hit the memory limit.

## Redeploy (code changes)

From WSL, in the repo:

```bash
bash docs/deploy/scripts/build-frontend.sh
bash docs/deploy/scripts/assemble.sh                 # never copies the database unless INITIAL=1
bash docs/deploy/scripts/upload.sh                   # skips .env, database/*.sqlite and storage/
bash docs/deploy/scripts/run-remote.sh docs/deploy/scripts/remote-install.sh
bash docs/deploy/scripts/run-remote.sh docs/deploy/scripts/remote-workers.sh
```

`remote-install.sh` runs Composer (`--no-dev`), `migrate --force`, the storage link, and `optimize`, then smoke-tests `/`, `/login`, `/up`. `remote-workers.sh` restarts the workers so they load the new code.

### Owner console (spec 0007), once after the first deploy that includes it

The migration creates its tables; the owner login does not exist until you make it over SSH (run it again to reset the password):

```bash
cd ~/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && /usr/bin/php artisan owner:create you@example.com --name="Your name"
```

Then sign in at `/owner/login`, set a price for every model the console lists as unpriced, and recharge. Until an account has a recharge it has no limit, so the deploy itself never switches AI off. Keys saved on the console override `.env` and are encrypted with `APP_KEY`: keep `APP_KEY` stable, or the stored keys stop decrypting and the app falls back to `.env`.

### English Essentials (2026-10-18), once after the first deploy that includes it

`migrate --force` (run by `remote-install.sh`) adds the columns. Then create the five starter topics as drafts (idempotent, safe to run again):

```bash
cd ~/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && /usr/bin/php artisan db:seed --class=EnglishEssentialsSeeder --force
```

Learners see nothing until the Super Admin fills and publishes them in **Lessons & Content → Department: English Essentials (all departments)**.

### Client round 2026-11-05, once after the first deploy that includes it

`migrate --force` (run by `remote-install.sh` / `server-deploy.sh`) adds the hotel groups of shared courses (`courses.hotel_scope`, `course_hotel`) and the other new tables. Nothing else is needed: existing courses stay "All hotels". Two behaviours change for the Super Admin:

- A course is shown to employees only when the admin shows it (**Course Structure → ⋮ → Show to employees**). Publishing a lesson no longer shows its course by itself; courses already published stay visible.
- A published course or Essentials topic with no published lesson is no longer listed for learners.

If AI features look stopped, run the read-only check and fix what it reports (provider on `fake`, missing key, spent/paused credit on `/owner`, or no queue worker):

```bash
cd ~/domains/lightgrey-dinosaur-781122.hostingersite.com/guesvia && /usr/bin/php artisan ai:doctor
```

Every AI job (lesson and image generation, role-play feedback, voice checks, translations) runs on the queue, so the two queue cron jobs above must exist; the bridge workers stop whenever the server restarts them.

### Changing an AI key from SSH (2026-11-07)

`ai:set-key` stores a key exactly as the owner console does (encrypted with `APP_KEY`, used by the next call, and it takes priority over `.env`). The key is typed at a hidden prompt, so it never lands in the shell history:

```bash
cd ~/domains/ghasido.com/guesvia && /usr/bin/php artisan ai:set-key anthropic
```

`qwen` and `deepgram` work the same way; `--clear` goes back to the `.env` key. Then check with `php artisan ai:doctor`.

## First install only

Run `deploy-export.php` inside the Sail container (`docker exec -u sail -w /var/www/html ghasido-laravel.test-1 php docs/deploy/scripts/deploy-export.php`) to produce `storage/logs/deploy/database.sqlite` and `media-list.txt`, then `INITIAL=1 assemble.sh` and `INITIAL=1 upload.sh`. **Doing this again overwrites the live database with local data.**

## Known gaps

- **Email is logged, not sent** until the Super Admin saves **Settings → Email** (SMTP mailbox contact@ghasido.com, stored encrypted in the database, overrides `.env`). Steps, DNS records and testing: [`email.md`](email.md). With "Send emails immediately" on, emails do not need the queue workers.

- The three cron jobs above are not created yet (API refused); the bridge workers cover the queues meanwhile, and the daily `RunAutomationRules` job will not run until the scheduler cron exists.
- No domain connected; the temporary `*.hostingersite.com` URL has Hostinger's SSL.
- Deployed from the working tree on 2026-09-25, including uncommitted work in progress at that time.
