# Load test

`load.mjs` signs in many different employees at the same time and has each
of them browse the learner pages (full page loads, then Inertia visits like
the app makes) and tap Show Meaning. It prints latency per page (p50 / p95 /
max), errors and requests per second. Node 22, no packages.

## Run it

1. Give some employee accounts a known password on the target (a local or
   staging copy — **never production passwords**), and list their usernames:

    ```bash
    php artisan tinker --execute '
    $ids = App\Models\User::whereHas("roles", fn ($q) => $q->where("name", "employee"))->where("status", "active")->whereNotNull("first_login_completed_at")->limit(100)->pluck("id");
    App\Models\User::whereIn("id", $ids)->update(["password" => Hash::make("password"), "welcomed_at" => now()]);
    file_put_contents("tests/load/users.json", App\Models\User::whereIn("id", $ids)->pluck("username")->toJson());'
    ```

2. Run it (users, rounds per user):

    ```bash
    BASE=http://127.0.0.1:8000 USERS_FILE=tests/load/users.json node tests/load/load.mjs 50 3
    ```

    `OFFSET=50` starts at the 51st account, so back-to-back runs do not hit
    the login limit of 5 attempts per user per minute.

## Results (2026-09-26, 4-CPU sandbox, PHP built-in server, 8 workers)

| Setup                                                 | Users at once | Errors                   | Requests/s |
| ----------------------------------------------------- | ------------- | ------------------------ | ---------- |
| Before: SQLite sessions + cache, no OPcache           | 20            | 0                        | 17         |
| OPcache, SQLite sessions + cache, SQLite untuned      | 20            | 8 × "database is locked" | 46         |
| Same, SQLite tuned (busy timeout, WAL, IMMEDIATE)     | 20            | 0                        | 48         |
| File sessions + cache (Hostinger today), SQLite tuned | 100           | 0                        | 26–35      |
| Redis sessions + cache, SQLite tuned                  | 100           | 0                        | 30         |

A single user sees 60–80 ms per page and ~450 ms to sign in (password
hashing). Under 100 users firing with no pause the server is CPU-bound and
pages queue to ~3 s, but nothing fails. Redis is no faster than files on a
single server; it helps once there are several web servers or the disk is
slow. For many simultaneous writers, MySQL is the next step after SQLite.
