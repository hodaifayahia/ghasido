# 0001 · Rationale: roles and permissions

_The decision record behind [index.md](index.md): the situation it was decided in, the options weighed, why this one won, and the sources._

## Context

> ⚠️ Premise note: five database tables, a permission cache and a package upgrade path is more machinery than three roles that the requirements say will never change, and root `AGENTS.md` §6 argues exactly that. Your scope ground rule chose the package anyway, which is defensible: the permission layer is what makes a fourth role (a research auditor for the PhD study, a read only client viewer) a seeder edit instead of a sweep through every route file. It pays off only if every check goes through a permission name rather than a role name, so that is written into the invariants below.
>
> A second, larger point: **this feature does not satisfy ROLE-02.** A manager holding `employees.view` reaches the platform wide Employees screen. Nothing real leaks today because every admin controller returns a hardcoded array, and that stops being true the moment feature 4 connects a screen to the database. The ordering constraint in Consequences is not a preference.

`app/` is the Laravel Vue starter kit plus seven invokable admin controllers serving sample data. There is one model, `User`, with no role, no hotel and no department. Every admin route today is guarded by `auth` and `verified`, and `verified` does nothing because `User` never implements `MustVerifyEmail`. The practical position is that any account that can sign in can read every admin screen, and `/dashboard` renders the Super Admin dashboard to whoever asks for it.

Three forces shape the decision. First, `AGENTS.md` states the negative permissions as the security relevant ones and demands they be enforced in policies and query scopes, never by hiding UI (ROLE-01, SEC-01). Second, the platform is the data collection instrument for a PhD study, so the roles that may read full AI transcripts and reset progress are a privacy boundary, not a convenience (ROLE-04, PRIV-04, PROG-06). Third, seven features queue behind this one in `docs/scope/scope.md`; each will guard routes, and without a settled pattern each will invent its own.

Two constraints this spec accepts rather than solves. Tenant scoping is blocked: `users.hotel_id` and `users.department_id` do not exist and arrive with spec 0002, so cross hotel isolation cannot be enforced here. Audit logging is blocked for the same reason: `audit_logs` arrives with spec 0002, and no role changes happen outside the seeder in this feature, so there is nothing to log yet.

The decision also reverses a written project rule. `AGENTS.md` §6 says spatie "is not installed and is not the default choice" and prescribes a `role` column plus policies, and its schema table puts `role` on `users`. The scope ground rules override both. Root `AGENTS.md` therefore carries instructions that contradict this spec until `/sync` corrects it.

## Options considered

### Option 1: `role` enum column plus Laravel policies and gates

What `AGENTS.md` §6 already prescribes. One string column on `users` cast to an `App\Enums\Role`, `Gate::before` for the Super Admin, and a policy per model.

**Pros**:

- No dependency, no new tables, no cache. Three roles fit a three case enum exactly.
- The role is one column, so filtering and reporting on it is a plain `where`.
- Nothing to upgrade, and nothing to relearn when Laravel 14 lands.

**Cons**:

- Every access rule is a role name in a route file or a policy method. Adding a fourth role means finding and editing each one.
- No permission vocabulary, so the client can never be shown what a role may actually do without reading the code.
- It contradicts the scope ground rule you set.

### Option 2: spatie, roles only, `role:` middleware

Install the package but use only its role half: `role:super_admin` on the groups, permission tables installed and left empty.

**Pros**:

- Reads plainly in a route file, and honours the ground rule with the least code.
- Keeps the door open to permissions later without another migration.

**Cons**:

- `RoleMiddleware` checks the role directly and never consults the Gate, so the `Gate::before` Super Admin override silently stops applying at the route boundary. Every group must then list every role that may pass, which is the maintenance burden of Option 1 with a dependency added.
- Five tables installed, two of them permanently empty.

### Option 3: spatie, named permissions behind three roles, `permission:` middleware (chosen)

Twenty permissions seeded in code, held by three roles, checked by `permission:` middleware on the route groups, with `Gate::before` giving the Super Admin everything.

**Pros**:

- Route files name a capability rather than a person, so a new role is a seeder edit.
- `PermissionMiddleware` calls `canAny()`, which routes through the Gate, so one `Gate::before` line covers the Super Admin everywhere.
- The permission list is a readable description of what each role may do, which the client can review without reading code.
- The same names drive the sidebar filter, so the UI cannot drift from the server rules.

**Cons**:

- The heaviest option: five tables, a cache with its own failure mode, and a package to keep current, for three roles that the requirements fix.
- The permission catalogue must be kept honest. A permission that no route checks is worse than no permission at all, because it reads like protection.
- A stale permission cache produces a 403 that does not reproduce locally.

## Rationale

Context names three forces and Option 3 is the only one that answers all three. `AGENTS.md` demands enforcement in server side checks rather than hidden UI, and permission middleware puts that check at the route boundary where it cannot be forgotten (basis: your `AGENTS.md`, ROLE-01 and SEC-01). The PhD privacy boundary needs abilities that are not role shaped: `transcripts.view` and `progress.reset` exist because ROLE-04 and PROG-06 name them specifically, and expressing those as role names would mean re editing checks the first time the client asks for a research auditor who may read transcripts but nothing else. And seven queued features need a pattern that costs one middleware argument to reuse.

Option 2 fails on a mechanical detail worth spelling out: spatie's `RoleMiddleware` checks `hasRole()` directly, so `Gate::before` never runs for it, and your criterion that the Super Admin overrides every ability would quietly not hold at the route boundary. `PermissionMiddleware` calls `canAny()`, which does route through the Gate. That is read from the package source rather than stated in its documentation, so AC-2 makes it an explicit test rather than an assumption (basis: spatie `PermissionMiddleware` source, verified during this design).

Option 1 is the honest runner up and is what `AGENTS.md` still argues for (basis: your `AGENTS.md` §6, roles and authorization). For three roles that never change it is genuinely simpler, and the premise note says so. You chose the package, the reasoning holds, and the cost is named in Consequences rather than hidden.

Two smaller calls made here rather than asked. `super_admin` is granted all twenty permission rows in the seeder, not left empty to lean on `Gate::before`: an empty `auth.permissions` array would make the new sidebar filter hide every item from the one person who can see everything. And permission names live in an `App\Enums\Permission` backed enum rather than as loose strings, so PHPStan level 7 catches a typo that would otherwise surface as a silent 403 (runner up: a constants class, rejected because an enum can be iterated by the seeder).

## References

**Project sources** (verifiable, in this repo):

- Root `AGENTS.md` §6, roles and authorization: the `role` enum plus policies convention this spec reverses, and the `Gate::before` snippet it already prescribes.
- Root `AGENTS.md`, the role capability table (ROLE-01 to ROLE-04) and the negative permission rules: the source of the manager column in the matrix.
- `docs/scope/scope.md`, ground rules: fixed `spatie/laravel-permission` with middleware on the routes, and feature 1's "done when" list, which seeded the acceptance criteria.
- `.agents/skills/laravel-permission-development/` (`spatie/laravel-permission`) and `.agents/skills/larastan/` (`ohnotnow/agentic-stuff`), installed during this design.
- Existing code this feature edits: `routes/admin.php`, `routes/web.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/DashboardController.php`, `resources/js/components/AppSidebar.vue`, `resources/js/types/navigation.ts`.

**Practices & standards**:

- Deny by default: a permission is granted explicitly and never inherited from a missing check.
- Authorization at the server boundary, never by hiding UI (OWASP broken access control).
- Idempotent seeders, so re running one is always safe.
- Explicit test states over a generous factory default, so a denial test cannot pass by accident.

**Links** (verified during this design, for a human to follow):

- spatie/laravel-permission `composer.json`, Laravel 13 support: https://github.com/spatie/laravel-permission/blob/main/composer.json
- Packagist, current release and PHP requirement: https://packagist.org/packages/spatie/laravel-permission
- Published migration stub, checked for SQLite portability: https://github.com/spatie/laravel-permission/blob/main/database/migrations/create_permission_tables.php.stub
- Middleware aliases for the Laravel 11+ `bootstrap/app.php` style: https://spatie.be/docs/laravel-permission/v8/basic-usage/middleware
- `PermissionMiddleware` source, the `canAny()` call behind the `Gate::before` question: https://github.com/spatie/laravel-permission/blob/main/src/Middleware/PermissionMiddleware.php
- Permission cache and how to reset it in tests: https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache
- The installed agent skill: https://skills.sh/spatie/laravel-permission
