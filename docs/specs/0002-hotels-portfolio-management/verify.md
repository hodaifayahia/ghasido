# Verify: hotels portfolio management · spec 0002 · updated 2026-09-18

_Steps derived from spec 0002 acceptance criteria. `/check verify` runs these; `/test` locks the durable ones. Each slice appends its own section; this one covers the foundation (scope feature 2)._

## Foundation (feature 2)

Sign in as a Super Admin for the manual steps. On a local database, `php artisan db:seed --force` gives you the eight hotel portfolio; the harness account `harness@guesvia.test` / `password` holds `super_admin`.

### UI / manual

- [ ] Open `/hotels` as a Super Admin → the Total Hotels card reads **7** (eight seeded hotels, one archived and left out), not the old sample value of 6 → AC-1, AC-3
- [ ] Still on `/hotels` → the other four cards and the table still show sample data. This is expected: only the Total Hotels thread is live in this slice → AC-1
- [ ] Sign in as a manager and type `/hotels` into the address bar → the branded 403 page, not a redirect → AC-10

### Commands

- [ ] `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test` → all green on SQLite, which is what CI runs → AC-19
- [ ] `php artisan test` → all green on MySQL 8.4 → AC-19
- [ ] `php artisan migrate --force` on a fresh SQLite file and on MySQL → the five `2026_09_18_*` migrations run clean on both → AC-19
- [ ] `php artisan test --filter=HotelTenancyTest` → the Super Admin sees every hotel through the scope, a manager sees only their own, a user with no hotel sees none, and the policy refuses a manager another hotel's record → AC-10
- [ ] `php artisan test --filter=HotelModelTest` → derived status, days remaining, seat counts and capacity boundaries all hold → AC-2, AC-4, AC-6, AC-8
- [ ] `php artisan test --filter=HotelFoundationTest` → the audit log's two shapes, the department slug rule, and the seeded portfolio → AC-9, AC-16, AC-19
- [ ] `./vendor/bin/phpstan analyse` → level 7, no errors → AC-20

### Value sourcing checks

_One per row of the spec's Value sourcing table that this slice touches. Each exercises where a value really comes from, especially the edge that breaks if the source is wrong._

- [ ] **Total Hotels** is `hotels` where `access_state` is not `archived`: archive one hotel in tinker and reload `/hotels` → the card drops by one. Nothing was deleted, so the row still exists → AC-3
- [ ] **Row order** is `name` ascending with `id` as a tiebreaker: seed two hotels with the same name → they keep the same relative order on every reload → AC-11
- [ ] **Expiring Soon** reads the configured window: set `guesvia.hotels.expiring_within_days` to 14 and a hotel with 20 days left moves from expiring to active with no code change → AC-4
- [ ] **daysRemaining** uses whole calendar days, both sides through `startOfDay()`: freeze the clock at 23:59 and again at 00:01 on the same day → the count is identical → invariant 8
- [ ] **daysRemaining** on an ended contract is negative and shows as Ended: the seeded Sunrise Dunes Hotel reads −18 → AC-2
- [ ] **usedSeats** counts active employee accounts only: deactivate one employee → their hotel's used seats drop by one and no row is deleted; a manager in the same department never counts → AC-6, invariant 9
- [ ] **capacityState** with no quotas reads Seats Available showing `0/0`, never At Capacity: create a hotel with no seat quota rows → AC-1 boundary
- [ ] **Over quota** is reached only by lowering: lower a department's quota below its live usage → it reads Over, and no account changes → AC-7
- [ ] **Department count** is the number of `seat_quotas` rows: the seeded hotels read 7, 6, 6, 5, 4 and 4 departments → AC-8
- [ ] **Audit `changes`** is the Eloquent diff for one model, and the batch `{"quotas": {...}}` shape for a quota save, one row either way → AC-16
- [ ] **Actor and IP** on an audit row come from the signed in user and the request: record a change while signed in as the owner → `actor_id` is the owner's id → AC-16

### Acceptance criteria coverage (this slice)

- AC-1 · Total Hotels only; the rest of the page is the directory slice
- AC-2 · covered by `HotelModelTest` and the ended contract check
- AC-3 · covered by the archive check and the seeded buckets test
- AC-4 · covered by the configured window check
- AC-6 · covered by the seat counting checks
- AC-7 · the capacity side here; the refusal at account creation is the seats slice
- AC-8 · covered by the department count check
- AC-9 · covered by the department slug rule tests
- AC-10 · covered by `HotelTenancyTest`; the by form POST half arrives with the write routes in later slices
- AC-16 · the audit log exists and is tested in both shapes; its callers are the service layer in later slices
- AC-19 · covered by the migration and test runs on both databases
- AC-20 · model methods carry every read; the controller writes no query
