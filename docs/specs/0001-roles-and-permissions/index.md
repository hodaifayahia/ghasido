# 0001. Roles and permissions, enforced server side with spatie

**Date**: 2026-09-16
**Status**: In Progress

_Why this decision was made, the options weighed and the sources: [rationale.md](rationale.md). How to check it really works: [verify.md](verify.md)._

## Summary

Guesvia gets three fixed roles (Super Admin, Manager, Employee) and twenty named permissions behind them, provided by the `spatie/laravel-permission` package. Every admin route is guarded by permission middleware on the server, so a manager who types `/hotels` gets a 403 rather than a page, and hiding a nav item is presentation only, never protection. The signed in role and the permission list travel to Vue through the shared Inertia props, and the sidebar hides what the user cannot open. This feature deliberately stops at role level access: stopping one hotel from reading another hotel's rows needs columns that arrive in spec 0002.

## Requirements

**User stories**:

- As the platform owner, I want every admin screen to check my permission on the server, so that a hidden link is never the only thing between a hotel manager and the whole platform's data.
- As a hotel manager, I want to be refused clearly when I reach outside my remit, so that I read it as a boundary rather than a bug.
- As the engineer, I want one enforcement pattern settled before the hotels work, so that the seven features behind it add a middleware argument instead of each inventing an access model.

**Acceptance criteria**:

- **AC-1**: Each of the seven admin routes is guarded by `permission:` middleware. A signed in user lacking that permission receives HTTP **403**, not a redirect and not a 404.
- **AC-2**: A user holding `super_admin` passes every authorization check, including abilities with no permission row, through a single `Gate::before` in `AppServiceProvider`, and that override demonstrably reaches spatie's `PermissionMiddleware`.
- **AC-3**: Three roles and twenty permissions exist with exactly the matrix in Feature design, created by an idempotent seeder that is safe to run repeatedly.
- **AC-4**: A user holds at most one role. Assigning a second replaces the first rather than adding to it.
- **AC-5**: `auth.user.role` (string or `null`) and `auth.permissions` (array of strings) appear in the shared Inertia props on every authenticated response, and are declared in `resources/js/types/`.
- **AC-6**: The sidebar hides any nav item whose permission the user lacks. A `super_admin` sees every item, and the sidebar at 1280×853 is pixel identical to the approved mockup, with the divider and spacing unchanged.
- **AC-7**: `/dashboard` renders the Super Admin dashboard only for `super_admin`. Every other user, including one with no role, gets the placeholder page, and the admin figures are absent from the Inertia payload rather than hidden in the UI.
- **AC-8**: A blocked request renders a minimal Guesvia styled error page built only from existing `@theme` tokens, carrying the correct 403 status.
- **AC-9**: A user with no role can sign in, lands on the placeholder, and receives 403 on every admin route.
- **AC-10**: The first Super Admin is created by a seeder reading `SUPER_ADMIN_*` environment variables, and that seeder does nothing when they are blank, so `composer setup` succeeds on a fresh clone.
- **AC-11**: The permission tables migrate clean on both SQLite (CI) and MySQL 8.4 (local), and every existing feature test passes.
- **AC-12**: Root `AGENTS.md` records the switch from a `role` enum plus policies to spatie, so a later agent reading it builds against this model.

## Decision

**Chosen option**: Option 3: spatie, named permissions behind three roles, `permission:` middleware.

Install `spatie/laravel-permission` ^8.3, seed three roles and twenty permissions, guard every admin route group with `permission:` middleware, give the Super Admin a blanket override through one `Gate::before`, and ship the role plus the permission list to Vue in the shared Inertia props.

**Implementation skills**: `laravel-permission-development` (`spatie/laravel-permission`, `.agents/skills/laravel-permission-development/`) · `larastan` (`ohnotnow/agentic-stuff`, `.agents/skills/larastan/`)

## Feature design

**Data model sketch**:

No column is added to `users`. The schema is spatie's five published tables, taken unmodified so the package can be upgraded without owning a fork.

| Table                   | Key columns                                             | Role here                                                                           |
| ----------------------- | ------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `roles`                 | `id`, `name`, `guard_name`; unique(`name`,`guard_name`) | three rows, seeded                                                                  |
| `permissions`           | `id`, `name`, `guard_name`; unique(`name`,`guard_name`) | twenty rows, seeded                                                                 |
| `role_has_permissions`  | `permission_id` FK, `role_id` FK; PK both               | the matrix below                                                                    |
| `model_has_roles`       | `role_id` FK, `model_type`, `model_id`; PK all three    | the single source of truth for a user's role                                        |
| `model_has_permissions` | `permission_id` FK, `model_type`, `model_id`            | published, deliberately left empty: a permission reaches a user only through a role |

Roles: `super_admin`, `manager`, `employee`, cast through `App\Enums\Role` (a backed string enum). Permissions are the cases of `App\Enums\Permission`.

| Permission                  | `super_admin` | `manager` | `employee` |
| --------------------------- | :-----------: | :-------: | :--------: |
| `hotels.view`               |      yes      |    no     |     no     |
| `hotels.manage`             |      yes      |    no     |     no     |
| `hotels.approve`            |      yes      |    no     |     no     |
| `departments.view`          |      yes      |    yes    |     no     |
| `departments.manage`        |      yes      |    no     |     no     |
| `employees.view`            |      yes      |    yes    |     no     |
| `employees.manage`          |      yes      |    yes    |     no     |
| `lessons.view`              |      yes      |    yes    |     no     |
| `lessons.manage`            |      yes      |    no     |     no     |
| `scenarios.view`            |      yes      |    no     |     no     |
| `scenarios.manage`          |      yes      |    no     |     no     |
| `tests.view`                |      yes      |    no     |     no     |
| `tests.manage`              |      yes      |    no     |     no     |
| `messages.view`             |      yes      |    yes    |     no     |
| `messages.manage`           |      yes      |    yes    |     no     |
| `reports.view`              |      yes      |    yes    |     no     |
| `reports.export`            |      yes      |    yes    |     no     |
| `reports.export_anonymised` |      yes      |    no     |     no     |
| `transcripts.view`          |      yes      |    no     |     no     |
| `progress.reset`            |      yes      |    no     |     no     |

The `manager` column transcribes the capability table in root `AGENTS.md` (ROLE-01 to ROLE-04), with one addition decided here: `lessons.view`, so a manager can see the curriculum their staff are working through without being able to change it (`lessons.manage` stays with the Super Admin). `employee` holds none of these, correctly: the learner routes are a different surface that this feature does not build.

**State transitions**: none. A role is assigned or replaced, and there is no lifecycle to model.

**API surface**: no new endpoints. The change is the middleware stack on existing routes, plus a branch inside `DashboardController`. `verified` is dropped from both groups, since `User` never implements `MustVerifyEmail` and AUTH-04 makes email optional, so it guards nothing.

| Route                 | Method    | Middleware after this change          | Auth       | Key errors                    |
| --------------------- | --------- | ------------------------------------- | ---------- | ----------------------------- |
| `/dashboard`          | GET       | `auth`                                | signed in  | none; the role picks the page |
| `/hotels`             | GET       | `auth`, `permission:hotels.view`      | permission | 403                           |
| `/departments`        | GET       | `auth`, `permission:departments.view` | permission | 403                           |
| `/employees`          | GET       | `auth`, `permission:employees.view`   | permission | 403                           |
| `/lessons-content`    | GET       | `auth`, `permission:lessons.view`     | permission | 403                           |
| `/ai-scenarios`       | GET       | `auth`, `permission:scenarios.view`   | permission | 403                           |
| `/messages-reminders` | GET       | `auth`, `permission:messages.view`    | permission | 403                           |
| `/reports-export`     | GET       | `auth`, `permission:reports.view`     | permission | 403                           |
| `/settings/*`         | GET/PATCH | `auth` (unchanged)                    | signed in  | unchanged                     |

Middleware arguments are written as `'permission:'.Permission::HotelsView->value`, never as a bare string, so a typo fails at analysis time rather than as a runtime 403.

One behaviour to expect rather than fix: an Inertia `<Link>` visit to a guarded route receives a plain Blade 403, which is not an Inertia response, so Inertia's client hard reloads to that URL to recover. AC-8 is still satisfied and the status is still 403. Do not paper over the extra round trip with bespoke JavaScript.

Sidebar nav item to permission map, carried on a new optional `permission?: string` field on `SidebarNavItem`:

| Nav item                | Permission                                                    |
| ----------------------- | ------------------------------------------------------------- |
| Dashboard               | none, always shown                                            |
| Hotels                  | `hotels.view`                                                 |
| Departments             | `departments.view`                                            |
| Employees               | `employees.view`                                              |
| Lessons & Content       | `lessons.view`                                                |
| AI Scenarios            | `scenarios.view`                                              |
| Pre-test & Post-test    | `tests.view` (no route yet; the item is a coming soon button) |
| Messages & Reminders    | `messages.view`                                               |
| Reports & Export        | `reports.view`                                                |
| Settings, Help, Log out | none, always shown                                            |

**Value sourcing**:

| Action                          | Value produced / displayed                              | Source                                                                                                                                                                                                                                                                    |
| ------------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Guard an admin route            | the required permission name                            | the `permission:` middleware argument, from `App\Enums\Permission`                                                                                                                                                                                                        |
| Guard an admin route            | whether this user holds it                              | `role_has_permissions` reached through `model_has_roles`, read via spatie's cached registrar                                                                                                                                                                              |
| Super Admin override            | "is this the platform owner"                            | `$user->hasRole(Role::SuperAdmin)` inside `Gate::before` in `AppServiceProvider::boot()`                                                                                                                                                                                  |
| Share auth props                | `auth.user.role`                                        | `$request->user()?->getRoleNames()->first()`, `null` for a guest and for a user holding no role                                                                                                                                                                           |
| Share auth props                | `auth.permissions`                                      | `$request->user()?->getAllPermissions()->pluck('name')->all() ?? []`, resolved once per request with the role relation eager loaded                                                                                                                                       |
| Share auth props, guest request | role and permissions on `/` and `/login`                | fixed: `null` and `[]`. `share()` runs on every request including unauthenticated ones, so both reads are null safe and both keys are always present, which keeps the TypeScript fields required rather than optional                                                     |
| Filter the sidebar              | which permission each item needs                        | the nav map above, carried on a new optional `permission?: string` on `SidebarNavItem`                                                                                                                                                                                    |
| Filter the sidebar              | whether to show an item                                 | `auth.permissions`, read through `useCan(): { can(permission: string): boolean }` in `resources/js/composables/useCan.ts`, reading `usePage().props.auth.permissions`. Fixed here because build step 7 writes it and step 8 consumes it                                   |
| Render `/dashboard`             | which page to render                                    | `$user->hasRole(Role::SuperAdmin)` inside `DashboardController`                                                                                                                                                                                                           |
| Render the placeholder          | the Inertia page name and file                          | `Inertia::render('Placeholder')` resolving to `resources/js/pages/Placeholder.vue`. The name has no area prefix, so `app.ts`'s central `switch` falls through to `default: AppLayout` and the user gets the filtered sidebar shell                                        |
| Render the placeholder          | title and body copy                                     | fixed strings defined here, wrapped in `__()`: title "Your area is being prepared", body "Your training space is not ready yet. Your hotel administrator will let you know when it opens."                                                                                |
| Render the 403 page             | the message shown                                       | Laravel's abort message, defaulting to `__('You do not have access to this page.')`                                                                                                                                                                                       |
| Seed the Super Admin            | name, email, password                                   | `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`                                                                                                                                                                                                           |
| Seed the Super Admin            | whether to run at all                                   | whether all three variables are non empty; blank means skip                                                                                                                                                                                                               |
| Seed the Super Admin            | what a second run does                                  | `User::updateOrCreate` keyed on `email`. Name and role are refreshed every run; the password is written only on create, so a re run never resets a password the owner has since changed                                                                                   |
| Run the seeders outside tests   | when roles and the Super Admin exist on a real database | `DatabaseSeeder::run()` calls both seeders, and `composer.json`'s `setup` script gains `php artisan db:seed --force` after `migrate --force`. Without this, nothing creates them on a fresh clone, a staging box or production, because only `Tests\TestCase` seeds today |
| Run the seeders outside tests   | a valid permission cache afterwards                     | `PermissionRegistrar::forgetCachedPermissions()` as the last line of `DatabaseSeeder::run()`. `DatabaseSeeder` uses `WithoutModelEvents`, which mutes spatie's own cache invalidation for every row seeded through it, including through a nested `$this->call(...)`      |
| Every feature test              | the roles present in the test database                  | `RolesAndPermissionsSeeder`, called from `Tests\TestCase::setUp()`                                                                                                                                                                                                        |
| Every feature test              | a clean permission cache                                | `PermissionRegistrar::forgetCachedPermissions()` in the same `setUp()`                                                                                                                                                                                                    |
| AC-2's override test            | a Super Admin holding no permission rows                | `Role::findByName(Role::SuperAdmin->value)->syncPermissions([])` followed by an explicit `forgetCachedPermissions()`. Permissions hang off the role, not the user (invariant 2), so the role's rows are what the test strips                                              |

**Key invariants**:

1. A user holds at most one role. `User::setRole()` calls `syncRoles()`, and nothing else assigns a role.
2. A permission reaches a user only through a role. `model_has_permissions` stays empty and no code calls `givePermissionTo()` on a `User`.
3. `super_admin` holds every permission row **in addition to** the `Gate::before` override, so `auth.permissions` is never empty for the platform owner.
4. Authorization is decided on the server. A hidden nav item is presentation and never the only thing preventing access.
5. Permission names exist only as `App\Enums\Permission` cases. No bare permission string appears in a route file, controller, policy or Vue file.
6. **No admin screen serves real hotel data until the tenant scoping from spec 0002 is enforced.** Role permission alone does not stop a manager reading another hotel's rows.
7. Any code path that writes roles or permissions while model events are muted clears the permission cache itself. `DatabaseSeeder` uses `WithoutModelEvents`, so spatie's automatic invalidation does not fire for anything seeded through it.
8. The shared Inertia props are null safe. `share()` runs for guests, so `auth.user.role` and `auth.permissions` are always present, as `null` and `[]` when nobody is signed in.

**Security model**:

Three roles, capabilities transcribed from the `AGENTS.md` role table (ROLE-01). Enforcement is `permission:` middleware at the route boundary plus `Gate::before` for the Super Admin, both server side (SEC-01). A refusal is 403, never a redirect and never a 404, so a blocked user can tell a boundary from a broken link.

Explicitly **not** covered here, each named so nobody reads this feature as delivering it:

- **Hotel and department scoping (ROLE-02)** belongs to spec 0002. A manager's `employees.view` currently reaches the platform wide Employees screen. Nothing real leaks while every admin controller returns a hardcoded array; the ordering constraint in Consequences keeps it that way.
- **Audit logging of role changes (SEC-06, ADM-03)** waits for `audit_logs` in spec 0002. No role changes happen outside the seeder in this feature, so there is nothing to log yet.
- **Username login and the removal of self registration (AUTH-01, AUTH-02)** stay deferred in the scope.

No compliance scope is triggered. Personal data stays limited to name, email, hotel and department (PRIV-03) and this feature adds no personal field.

**Configuration required**:

- `SUPER_ADMIN_NAME`: display name of the first platform owner account. Blank by default.
- `SUPER_ADMIN_EMAIL`: login email of that account. Blank by default; blank means the seeder skips.
- `SUPER_ADMIN_PASSWORD`: initial password, hashed on write, never committed. Blank by default.

All three go into `.env.example` with empty values, so `composer setup` succeeds on a fresh clone without creating an account.

One script change goes with them: `composer.json`'s `setup` script gains `php artisan db:seed --force` after `migrate --force`. Today nothing outside PHPUnit ever runs a seeder, so without this the roles and permissions simply do not exist on any real database and AC-10 would pass for the wrong reason.

**Critical test scenarios**:

- Happy path: a `super_admin` receives 200 on all seven admin routes and sees every nav item, verifies **AC-1**, **AC-2**, **AC-6**.
- Failure case: the `Gate::before` override genuinely reaches spatie's `PermissionMiddleware`. Strip the role's rows with `Role::findByName(Role::SuperAdmin->value)->syncPermissions([])`, forget the cache, then assert a guarded route still returns 200. This is the one behaviour the package documentation does not state outright, verifies **AC-2**.
- Auth/permission: a `manager`, an `employee` and a user with no role each receive 403 on `/hotels` by direct URL, verifies **AC-1**, **AC-9**.
- Invariant: assigning a second role leaves exactly one, verifies **AC-4**.
- Payload: a manager's `/dashboard` response carries no `stats` and no `trainingOverview` prop at all, rather than hiding them in the UI, verifies **AC-7**.
- Shared props: an authenticated response carries `auth.user.role` and `auth.permissions` matching the user's role, and an unauthenticated GET of `/` returns 200 with `role` `null` and `permissions` `[]` rather than erroring, verifies **AC-5**.
- Error page: a blocked request returns status 403 and renders the branded error view, not Laravel's default, verifies **AC-8**.
- Seeder branches: with the three `SUPER_ADMIN_*` values set, the account exists afterwards and holds `super_admin`; with any of them blank, no account is created and the seeder still exits cleanly. Running it twice leaves one account and does not rewrite its password, verifies **AC-10**.
- Portability: the published migration runs clean on SQLite, verifies **AC-11**.
- Idempotency: running `RolesAndPermissionsSeeder` twice leaves twenty permissions, not forty, verifies **AC-3**.

## Build plan

Ordered as a Tracer Bullet: steps 1 to 8 push a single route, `/hotels`, through every layer from migration to filtered sidebar, so the whole pipe is proven before any of it is widened. Steps 9 onward thicken it.

Note the placement of step 3. The test harness has to know about roles before the first test is written, or step 6's tests fail for a reason that has nothing to do with the code under test.

1. Install `spatie/laravel-permission` ^8.3, publish its config and migration, register the `role`, `permission` and `role_or_permission` aliases in `bootstrap/app.php`, and confirm the migration runs on both SQLite and MySQL 8.4, satisfies **AC-11**.
2. Add `App\Enums\Role` and `App\Enums\Permission`, and write `RolesAndPermissionsSeeder` creating three roles and twenty permissions with the matrix above, idempotent via `firstOrCreate` plus `syncPermissions`, satisfies **AC-3**.
3. Add a `setUp()` to `Tests\TestCase` that runs `RolesAndPermissionsSeeder` and then `PermissionRegistrar::forgetCachedPermissions()`, so every test from here on starts with the roles present and a clean cache, satisfies **AC-9**, **AC-11**.
4. Add the `HasRoles` trait and a `setRole()` method backed by `syncRoles()` to `User`, with the `@property` PHPDoc PHPStan level 7 needs, plus `superAdmin()`, `manager()` and `employee()` factory states that go through `setRole()`, satisfies **AC-4**.
5. Add `Gate::before` to `AppServiceProvider::boot()`, returning `true` for `super_admin` and `null` otherwise so other abilities still evaluate, satisfies **AC-2**.
6. **The thread**: guard `/hotels` alone with `permission:hotels.view`, drop `verified` from that group, and write two tests, a manager gets 403, and a Super Admin whose role has had `syncPermissions([])` applied with the cache forgotten still gets 200, satisfies **AC-1**, **AC-2**.
7. Share `auth.user.role` and `auth.permissions` from `HandleInertiaRequests::share()` using the null safe reads in Value sourcing, declare both in `types/auth.ts` and `global.d.ts`, add `useCan` at `resources/js/composables/useCan.ts` with the fixed signature, and assert an unauthenticated GET of `/` still returns 200, satisfies **AC-5**.
8. Add `permission?: string` to `SidebarNavItem`, filter the Hotels item in `NavMain` through `useCan`, and screenshot the Super Admin sidebar at 1280×853 against the mockup to prove the geometry is untouched, satisfies **AC-6**.
9. Guard the remaining six admin routes and drop `verified` from `routes/web.php` as well, satisfies **AC-1**.
10. Apply the full nav map, and re check the sidebar at 1280×853, 1240×698 and 390×844, satisfies **AC-6**.
11. Branch `DashboardController` on the role and add `resources/js/pages/Placeholder.vue`, rendered as `Inertia::render('Placeholder')` so `app.ts` falls through to `AppLayout`, built from `PageHeader` and the approved chrome, with the admin props absent from its payload, satisfies **AC-7**.
12. Add the minimal branded 403 Blade error view using only existing `@theme` tokens, satisfies **AC-8**.
13. Add `SuperAdminSeeder` reading `SUPER_ADMIN_*` with `updateOrCreate` keyed on email, skipping when any value is blank; call both seeders from `DatabaseSeeder::run()` and finish that method with `forgetCachedPermissions()` because of its `WithoutModelEvents` trait; add `php artisan db:seed --force` to `composer.json`'s `setup` script; add the three blank entries to `.env.example`, satisfies **AC-10**.
14. Update `DashboardTest` and any other test asserting a 200 from `/dashboard` to use `->superAdmin()`, satisfies **AC-11**.
15. Write the negative suite: 403 by direct URL for manager, employee and roleless user on every guarded route; one role only; the dashboard payload test; the branded 403 view; seeder idempotency and both seeder branches, satisfies **AC-1**, **AC-3**, **AC-4**, **AC-7**, **AC-8**, **AC-9**, **AC-10**.
16. Run `/sync` so root `AGENTS.md` §6 records the switch to spatie, drops `role` from the `users` schema row, and adds the two installed skills to `## Agent skills`, satisfies **AC-12**.

## Consequences

**Positive**:

- Features 4 to 10 add a middleware argument instead of inventing an access model each.
- A fourth role, a research auditor for the PhD study or a read only client viewer, becomes a seeder edit rather than a sweep through route files.
- The negative tests turn the `AGENTS.md` capability table from documentation into something executable.
- The permission list doubles as a plain description of what each role may do, reviewable by the client without reading code.

**Negative / tradeoffs**:

- Five tables, a permission cache and a package upgrade path, for three roles the requirements fix. If the client never adds a fourth role, this is capacity bought and unused.
- **ROLE-02 is not satisfied.** A manager holds `employees.view` and today reaches the platform wide Employees screen. It serves sample data so nothing real leaks, and that ends the moment an admin screen reads the database. Spec 0002's scoping must land before feature 4 connects real data. Treat that as an ordering constraint, not a preference.
- Root `AGENTS.md` §6 currently instructs every agent to use a `role` enum and states spatie is not installed. Until step 15 runs, an agent reading the doc builds against the wrong model.
- The permission cache is real state. A permission changed outside an Eloquent model event does not clear it, and the symptom is a 403 that will not reproduce locally.
- Filtering the sidebar edits chrome that `AGENTS.md` §0 locks. A Super Admin sees it unchanged so the mockup still matches, but the file is no longer purely the approved artefact, and every future mockup check has to be run as a Super Admin to be meaningful.
- The 403 page has no mockup behind it. It is built from tokens only, and it is still layout invented without the client.
- PHPStan level 7 may resolve spatie's `HasRoles` trait methods (`hasRole`, `getRoleNames`, `getAllPermissions`, `syncRoles`) as `mixed` and fail where one feeds a typed parameter. `@property` PHPDoc on `User` covers columns, not trait return types. If the `larastan` skill's defaults do not resolve it, the fallback is a targeted `ignoreErrors` entry with a stated reason, never lowering `level`, per `AGENTS.md` §8.

**Neutral**:

- `composer.json`'s `setup` script gains a `db:seed --force` step, so CI now seeds as well as migrates. Both seeders are idempotent, so a repeat run is safe.
- `verified` disappears from both route groups. No behaviour change today, since `User` never implemented `MustVerifyEmail`.
- `users` gains no columns. `hotel_id` and `department_id` arrive with spec 0002.
- Existing tests asserting a 200 from `/dashboard` need `->superAdmin()`.
- The error view is the project's first Blade view outside the Inertia root template.

## Follow-up

- [ ] Run `/sync` so root `AGENTS.md` records the spatie switch (also tracked as build step 15 and **AC-12**).
- [ ] Ask the client whether a hotel manager should read the lesson library. This spec grants `lessons.view` on the reasoning that a manager cannot support staff on content they cannot see; the `AGENTS.md` role table is silent on it, so the grant is a judgement call, reversible in one seeder line.
- [ ] Ask the client for a mockup of the error and empty state screens. The 403 page here is invented from tokens, which `AGENTS.md` §0.2 rule 8 says to raise rather than decide silently.
- [ ] Spec 0002 must define hotel and department scoping before feature 4 connects any admin screen to real data. Hard ordering constraint.
- [ ] Wire audit logging of role grants and revokes (SEC-06) once `audit_logs` exists in spec 0002 and role assignment gets a real surface.
- [ ] `laravel-permission-development` and `larastan` conventions are not yet in root `AGENTS.md`. Both are project wide, authorization touches every route and PHPStan runs on every file, so they belong in root `## Agent skills` rather than a nested area file.
- [ ] `laravel/agent-skills` installed three skills: `starter-kit-upgrade`, `deploying-to-cloud` and `configure-nightwatch`. None applies to this feature. Either remove the two irrelevant ones or record why they are kept, so a later run does not treat them as project conventions.
