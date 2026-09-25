# Verify: roles and permissions · spec 0001 · updated 2026-09-17

_Steps derived from spec 0001 acceptance criteria. `/check verify` runs these; `/test` locks the durable ones._

Sign in as one of these to run the manual steps. Create them with the factory
states (`User::factory()->superAdmin()` / `->manager()` / `->employee()`), or
by hand plus `setRole()`. A fourth user with **no role at all** is needed too.

## UI / manual

- [ ] Sign in as `super_admin` → visit `/hotels`, `/departments`, `/employees`, `/lessons-content`, `/ai-scenarios`, `/messages-reminders`, `/reports-export` → every one renders, none refuses → AC-1, AC-2
- [ ] Sign in as `manager` → type `/hotels` straight into the address bar → the branded Guesvia 403 page, not a redirect to the dashboard and not a 404 → AC-1, AC-8
- [ ] Same as `manager` → `/ai-scenarios` refuses, while `/departments`, `/employees`, `/lessons-content`, `/messages-reminders`, `/reports-export` all open → AC-1
- [ ] Sign in as `employee` → every one of the seven admin URLs refuses → AC-1
- [ ] Sign in as the user with **no role** → signing in works, `/dashboard` shows the placeholder, and all seven admin URLs refuse → AC-9
- [ ] As `super_admin` at 1280×853 → the sidebar shows all nine main items plus Settings, Help, Log out, and matches `desginphotos/photo_2026-09-15_18-13-24.jpg`: same order, same divider position, same palm footer → AC-6
- [ ] As `manager` → the sidebar hides Hotels, AI Scenarios and Pre-test & Post-test, and keeps everything else. As `employee` → only Dashboard, Settings, Help, Log out → AC-6
- [ ] Re-check the sidebar at 1240×698 and 390×844 → no horizontal scrollbar, no scrolling sidebar, no clipped or wrapped nav label → AC-6
- [ ] As `super_admin` → `/dashboard` shows the admin dashboard with the six stat cards. As `manager` → the placeholder, titled "Your area is being prepared" → AC-7
- [ ] On the `manager` dashboard, open devtools and read the Inertia payload (`data-page` on `#app`) → `stats` and `trainingOverview` are **absent**, not merely unrendered → AC-7
- [ ] In devtools on any signed-in page → `auth.user.role` is the role name and `auth.permissions` lists the permissions. Sign out and load `/` → `auth.user` is `null` and `auth.permissions` is `[]`, and the page still renders → AC-5
- [ ] Hide a nav item, then type its URL anyway as that same user → still refused, proving the sidebar filter is presentation and the server decides → AC-1, invariant 4

## Commands

- [ ] `php artisan test` → all green on the local database → AC-11
- [ ] `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test` → all green on SQLite, which is what CI runs → AC-11
- [ ] `php artisan test --filter=RoutePermissionTest` → a super admin whose role has had `syncPermissions([])` applied still gets 200 on `/hotels`, proving `Gate::before` reaches spatie's `PermissionMiddleware` → AC-2
- [ ] `php artisan test --filter=RolesAndPermissionsTest` → three roles, twenty permissions, one role per user, `model_has_permissions` empty → AC-3, AC-4
- [ ] `php artisan test --filter=DatabaseSeederTest` → seeding through `DatabaseSeeder` (which mutes model events) still builds the full matrix, the regression guard for the permission cache → AC-3, invariant 7
- [ ] On a throwaway database: `php artisan migrate --force && php artisan db:seed --force` twice with `SUPER_ADMIN_*` blank → runs clean both times, 3 roles, 20 permissions, no owner account created → AC-3, AC-10, AC-11
- [ ] Same, with the three `SUPER_ADMIN_*` values set → the owner exists and holds `super_admin`. Change that account's password by hand, seed again → still one account, the name refreshed, the password **not** reset → AC-10
- [ ] `composer ci:check` → the full gate → AC-11

## Value-sourcing checks

_One per row of the spec's Value sourcing table: each exercises where a value really comes from, especially the edge that breaks if the source is wrong._

- [ ] Change a route's middleware argument to a permission no role holds → that route refuses everyone except the Super Admin, whose 200 comes from `Gate::before`. Put it back → the source is the middleware argument, not a role name in the controller
- [ ] Add a permission row directly in the database and assign it to `manager` **without** clearing the cache → the manager is still refused until `php artisan permission:cache-reset` → proves the check reads spatie's cached registrar, and is the symptom to recognise behind a 403 that will not reproduce
- [ ] Give a user two roles with `assignRole()` twice through `setRole()` → exactly one role remains, and `auth.user.role` shows the last one set → the source is `model_has_roles`, one row per user
- [ ] Sign in as a user whose role holds no permissions (`employee`) → `auth.permissions` is `[]` and the sidebar keeps only the items with no `permission` field → the filter reads the shared prop, not a hardcoded list
- [ ] Strip the Super Admin role's permission rows → `auth.permissions` goes empty **and the sidebar empties**, while access still works → this is exactly why invariant 3 grants the owner every row as well as the override. Re-seed to restore
- [ ] Set `SUPER_ADMIN_EMAIL` to an address that already exists → the seeder updates that account rather than creating a second → keyed on email
- [ ] Set `APP_ENV=production` and seed a fresh database → no `test@example.com` account is created; set `APP_ENV=local` and seed → it is → the starter's convenience account is confined to a developer machine
- [ ] Switch the interface language → the placeholder title and body, and the 403 page's own copy, follow it, because the server wraps them in `__()`. Note the refusal sentence itself comes from spatie and is not translated (see the report's open point)

## Acceptance-criteria coverage

- AC-1 · covered by the four manual route walks and `RoutePermissionTest`
- AC-2 · covered by the super admin walk and the `syncPermissions([])` override test
- AC-3 · covered by `RolesAndPermissionsTest` and `DatabaseSeederTest`, plus the double seed on a throwaway database
- AC-4 · covered by the one role only test and the two roles value-sourcing check
- AC-5 · covered by the devtools payload check and `SharedAuthPropsTest`
- AC-6 · covered by the three sidebar checks at 1280×853, 1240×698 and 390×844
- AC-7 · covered by the dashboard branch check and the absent props payload check
- AC-8 · covered by the manager 403 walk and the branded error page test
- AC-9 · covered by the no role walk
- AC-10 · covered by the two `SUPER_ADMIN_*` seeder runs and `SuperAdminSeederTest`
- AC-11 · covered by the two `php artisan test` runs and `composer ci:check`
- AC-12 · **not covered: still owed.** Root `AGENTS.md` still tells agents spatie is not installed and prescribes a `role` enum. Run `/sync` to correct it
