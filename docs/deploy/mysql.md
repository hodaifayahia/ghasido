# Moving the live site from SQLite to MySQL

Client request 2026-09-26: the platform must be able to grow past one
server and many simultaneous writers, which SQLite cannot do. The code runs
on both; this is the one-time move of the live data.

## What was checked before this was written

- All 72 migrations run on MySQL 8 from an empty database.
- The whole test suite runs on MySQL: 913 of 925 pass and 10 are skipped.
  The 2 that fail are the `storage:link` checks, which also fail on SQLite
  in a checkout without `public/storage`.
- Two MySQL differences were fixed in the code, not worked around:
    - MySQL's JSON type reorders object keys, so an unchanged activity looked
      "edited" and got a new version on every save (DATA-11).
      `Activity::contentChanged()` now compares content, not key order.
    - Evaluation criteria came back in MySQL's key order on the feedback
      screens. The `JsonInOrder` cast puts them back in their defined order.
- `php artisan db:copy-sqlite-to-mysql` was rehearsed on a copy of the
  data with deliberately awkward rows (ISO dates with time zones, Arabic
  and emoji, JSON): every table's row count matched and the values were
  identical. The browser check of every page (102 checks) and the load test
  (100 simultaneous users, 0 errors) then passed on MySQL.
- `scripts/remote-switch-to-mysql.sh` was rehearsed end to end on a copy of
  the app set up like the server.

## What the copy command does

`php artisan db:copy-sqlite-to-mysql --sqlite-path=… --fresh`

1. Refuses to run unless both databases are on the same migrations.
2. Checks every row against MySQL's stricter rules — text longer than its
   column (SQLite ignores lengths), `TEXT` over 64 KB, invalid JSON — and
   stops before writing anything if one would not fit, naming the table and
   column.
3. Copies every table with its ids (foreign-key checks off during the copy),
   rewriting dates into MySQL's format (same instant).
4. Compares the row count of every table and fails if any differs.

It never changes the SQLite file. `sessions`, `cache` and `cache_locks` are
not copied (the live site keeps sessions and cache in files).

## The switch, step by step

Plan 10 minutes; the site shows the maintenance page for about a minute.

1. **hPanel → Databases → MySQL Databases**: create a database and a user
   with all privileges on it. Note the database name, user, password and the
   host hPanel shows (usually `localhost`).
2. **Deploy** this code as usual (`build-frontend.sh`, `assemble.sh`,
   `upload.sh`, `remote-install.sh`). The site still runs on SQLite.
3. **Edit the server's `.env`** (SSH or hPanel File Manager). Keep a copy of
   the two current lines, then set:

    ```
    DB_CONNECTION=mysql
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=u673635734_…
    DB_USERNAME=u673635734_…
    DB_PASSWORD=…
    ```

    The site keeps running on SQLite until the next step, because the config
    is cached.

4. **Run the switch** right away:

    ```bash
    bash docs/deploy/scripts/run-remote.sh docs/deploy/scripts/remote-switch-to-mysql.sh
    ```

    It puts the site in maintenance mode, checks the MySQL connection, stops
    the queue workers, backs up the SQLite file to `database/backups/`,
    creates the tables in MySQL, checks and copies every row, rebuilds the
    caches, brings the site back and smoke-tests it. If anything fails it
    stops with the site still in maintenance mode and prints how to go back.

5. **Restart the workers**:

    ```bash
    bash docs/deploy/scripts/run-remote.sh docs/deploy/scripts/remote-workers.sh
    ```

6. **Check**: sign in as the Super Admin, an employee and a manager; open
   Reports & Export and a lesson. New activity now lands in MySQL.

## Going back

The SQLite file is untouched. Put the two old lines back in `.env`
(`DB_CONNECTION=sqlite`, `DB_DATABASE=<path to database.sqlite>`), then run
`php artisan optimize && php artisan up`. Anything written after the switch
exists only in MySQL.

## After the move

- Remove the SQLite-only lines from any local `.env` you copy to the server.
- Back up MySQL instead of the SQLite file (hPanel has daily backups; a
  `mysqldump` before each migration is still wise).
- Redis for cache, sessions and queues is a `.env` change only
  (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`,
  `REDIS_CLIENT=phpredis`, `REDIS_HOST=…`), once the host offers Redis and
  PHP has the `redis` extension. It pays off when there is more than one web
  server; see `tests/load/README.md`.
