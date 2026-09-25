# 0002. Hotels portfolio management

**Date**: 2026-09-17
**Status**: In Progress

_Why this decision was made, the options weighed and the tradeoffs: [rationale.md](rationale.md)._

## Summary

The Hotels screen is built but every number on it is a hardcoded array. This spec settles the tables behind it and the rules those numbers follow, then takes the whole page to real data: the directory, search and filters, creating and approving a hotel, contract periods, and seat quotas per department.

Two choices shape everything else. First, anything that could drift is **computed when it is read**, never stored: seat usage, days remaining, whether a contract is expiring or ended. Second, a user now carries a hotel and a department, which is the **tenant scoping** (stopping one hotel reading another hotel's rows) that spec 0001 named as a hard prerequisite before any admin screen reads real data.

For building, this is an umbrella: four child specs, ordered so a single thin thread runs end to end before any part is filled in.

## Structure

| Child                                                      | What it is                                                                                                    | Scope features |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | -------------- |
| [0002-foundation.md](0002-foundation.md)                   | The tables, models, tenant scoping, audit log and seeder, plus one thin thread proving the whole pipe         | 2              |
| [0002-directory.md](0002-directory.md)                     | The table, the five stat cards and the sidebar reading real rows, then server side search, filters and paging | 4, 6           |
| [0002-create-and-approve.md](0002-create-and-approve.md)   | The Add Hotel button and dialog, the pending state, approve and reject, and the login block                   | 5, 7           |
| [0002-contracts-and-seats.md](0002-contracts-and-seats.md) | Edit and archive, extend, pause and resume, and seat quotas per department                                    | 8, 9, 10       |

The contract every child builds against (data model, state machine, value sourcing, invariants, security, configuration) lives in `## Feature design` below, once. Children do not restate it.

## Requirements

**User stories**:

- As the platform owner, I want the Hotels page to read real rows, so that the numbers I make contract decisions on are true rather than a mockup.
- As the platform owner, I want to create a hotel, approve it, set its seats and manage its contract without a developer, so that onboarding is my job and not an engineering ticket.
- As the platform owner, I want every access and contract change traced, so that I can answer who changed what and when.
- As a hotel manager, I want to see my own hotel and nothing else, so that the platform is safe to give to a customer.

**Acceptance criteria**:

- **AC-1**: Every figure on `/hotels` (the five stat cards, every table row, the sidebar) is computed from the database. No hardcoded array remains in `HotelsController`.
- **AC-2**: A hotel's stored state is one of `pending`, `active`, `paused`, `archived`. `expiring` and `ended` are never stored: both are derived from `contract_ends_on` against today.
- **AC-3**: The displayed buckets are mutually exclusive. Active excludes hotels inside the warning window, and Total Hotels equals Pending plus Active plus Expiring Soon plus Paused plus Ended. Archived hotels appear in no stat card and in no default list, and can be filtered back in.
- **AC-4**: The warning window is read from configuration and defaults to 30 days. Changing that one value moves the stat card, the row pill and the status filter together, with no code change.
- **AC-5**: Pausing records `paused_at`. Resuming moves `contract_ends_on` forward by exactly the paused duration, so days remaining is unchanged across a pause and resume.
- **AC-6**: Seat usage is counted live as active employee accounts in that hotel and department. No `used_seats` column exists on any table.
- **AC-7**: A quota may be lowered below current usage. Existing accounts are untouched, the hotel is flagged over quota, and creating a new account in that department is refused on the server.
- **AC-8**: A hotel's departments are its `seat_quotas` rows. The department count on a row equals the number of those rows, which is why one hotel shows 7 and another 4.
- **AC-9**: Departments come from a shared catalogue (`hotel_id` null) and the schema allows a hotel to hold its own (`hotel_id` set). A global department slug is unique among global departments. Creating a hotel owned department has no screen in this spec: the catalogue is seeded, and that screen arrives with the deferred Departments feature.
- **AC-10**: A manager or an employee cannot read another hotel's rows. A direct URL to another hotel's record returns **403**, not 404 and not a redirect, enforced by a policy with a global scope behind it.
- **AC-11**: Search covers hotel name, manager name and city. Both filters and the page number live in the query string and survive a refresh, Reset clears them, and the table pages 15 rows at a time.
- **AC-12**: Create Hotel collects name, city, manager name, manager email, contract start and contract end, refuses bad input with errors beside the fields, and the saved hotel lands `pending`.
- **AC-13**: Approving a pending hotel sets `contract_starts_on` to the approval date and moves `contract_ends_on` by the same offset, so the agreed duration survives a late approval.
- **AC-14**: Rejecting a pending hotel archives it with a reason. No `rejected` state exists.
- **AC-15**: Anyone attached to a hotel that is pending, paused, ended or archived is refused at login with a message naming which of those applies, and every one of their rows is preserved.
- **AC-16**: Approve, reject, archive, edit, extend, pause, resume and every quota change writes an `audit_logs` row carrying actor, action, target, the before and after values, and the IP.
- **AC-17**: Create Hotel is a button at the top of the page, the Quick Actions panel is gone, and Extend Contract, Pause and Resume are row actions that collapse into an overflow menu rather than widening the row.
- **AC-18**: The sidebar opens on the first row of the current page and swaps when View is clicked. When a search matches nothing, the table and the sidebar each render their own empty state rather than a blank card.
- **AC-19**: Migrations run clean on both SQLite (CI) and MySQL 8.4 (local). The seeder creates the six hotels currently on the page plus one pending and one archived, **and the active employee accounts behind their seat numbers**, so the counted figures match the page rather than reading zero.
- **AC-20**: No controller writes a query. A form request validates, a service performs the change, model methods do the reading, a resource shapes the payload, and a policy plus middleware guard it.

## Decision

**Chosen option**: Option 3: stored access state with everything time based and count based derived at read time.

Store only what an administrator sets (`pending`, `active`, `paused`, `archived`) and compute everything else when the page is read: seat usage from live account counts, and expiring and ended from the contract dates against today. Scope every tenant owned model with a `ScopedToHotel` global scope behind real policies, and record every mutation in one polymorphic `audit_logs` table.

**Implementation skills**: `laravel-permission-development` (`spatie/laravel-permission`, `.agents/skills/laravel-permission-development/`) · `larastan` (`ohnotnow/agentic-stuff`, `.agents/skills/larastan/`)

## Rationale

See [rationale.md](rationale.md).

## Feature design

**Data model sketch**:

Four new tables and three columns added to `users`. Every enumerated column is a `string` cast to a PHP enum, never `$table->enum()`, because the same migration has to run on SQLite in CI and MySQL 8.4 locally.

| Table             | Columns                                                                                                                                                                                                                                                                                                                                            | Constraints and indexes                                          |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `hotels`          | `id`, `name`, `slug`, `city`, `manager_name`, `manager_email`, `access_state`, `contract_starts_on` date nullable, `contract_ends_on` date nullable, `paused_at` datetime nullable, `approved_at` datetime nullable, `approved_by` FK users nullable, `archived_at` datetime nullable, `archive_reason` text nullable, `settings` json, timestamps | unique(`slug`); index(`access_state`), index(`contract_ends_on`) |
| `departments`     | `id`, `name`, `slug`, `hotel_id` FK nullable, `position`, `is_active`, timestamps                                                                                                                                                                                                                                                                  | unique(`hotel_id`,`slug`); index(`hotel_id`)                     |
| `seat_quotas`     | `id`, `hotel_id` FK, `department_id` FK, `allowed_seats` unsigned int, timestamps                                                                                                                                                                                                                                                                  | unique(`hotel_id`,`department_id`)                               |
| `audit_logs`      | `id`, `actor_id` FK users nullable, `action`, `auditable_type`, `auditable_id`, `changes` json, `ip` nullable, `created_at`                                                                                                                                                                                                                        | index(`auditable_type`,`auditable_id`), index(`actor_id`)        |
| `users` (altered) | adds `hotel_id` FK nullable, `department_id` FK nullable, `status` string                                                                                                                                                                                                                                                                          | index(`hotel_id`,`department_id`,`status`)                       |

Relationships: `Hotel` 1:N `SeatQuota` N:1 `Department` · `Hotel` 1:N `User` · `Department` 1:N `User` · `Hotel` 0:N `Department` (only its own) · `User` 1:N `AuditLog` as actor · `AuditLog` N:1 anything auditable.

`departments.hotel_id` being nullable means a composite unique index cannot constrain the global rows (both MySQL and SQLite allow repeated nulls in a unique index). Uniqueness among global departments is therefore a validation rule, `Rule::unique('departments', 'slug')->whereNull('hotel_id')`, and that is a deliberate limitation rather than an oversight.

`users.status` is added here because seat counting needs it. The other columns `AGENTS.md` lists for `users` (`participant_code`, `cohort`, `first_login_completed_at` and the rest) belong to their own features and are deliberately not added.

**State transitions**:

Stored state, set by an administrator:

```
(created) ──▶ pending
pending   ──▶ active     approve: start the clock, preserve the duration
pending   ──▶ archived   reject, with a reason
active    ──▶ paused     pause: record paused_at
paused    ──▶ active     resume: push contract_ends_on by the paused duration
active    ──▶ archived   archive
paused    ──▶ archived   archive
archived  ──▶ (terminal; restoring an archived hotel is out of scope)
```

Derived overlay, never stored, applied only to a hotel whose state is `active`:

| Derived status | Rule                                                             |
| -------------- | ---------------------------------------------------------------- |
| `ended`        | `contract_ends_on` is before today                               |
| `expiring`     | days remaining is between 0 and the configured window, inclusive |
| `active`       | days remaining is greater than the window                        |

A hotel that has ended needs no state change to come back: extending its contract moves the date, and the derived status returns to `active` on its own.

**API surface**:

All routes are web routes rendering Inertia responses, inside the existing `auth` group, each guarded by the permission spec 0001 already seeded. There is no `routes/api.php`.

| Endpoint                      | Method | Key inputs                                                                                | Key outputs                                            | Auth                        | Key errors                |
| ----------------------------- | ------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------------ | --------------------------- | ------------------------- |
| `/hotels`                     | GET    | `search`, `status`, `capacity`, `page`, `hotel` (all optional query params)               | `stats`, `filters`, `hotels`, `pagination`, `overview` | `permission:hotels.view`    | 403                       |
| `/hotels`                     | POST   | `name`, `city`, `manager_name`, `manager_email`, `contract_starts_on`, `contract_ends_on` | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 422                  |
| `/hotels/{hotel}`             | PATCH  | same fields as create                                                                     | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 422                  |
| `/hotels/{hotel}/approve`     | POST   | none                                                                                      | redirect plus flash toast                              | `permission:hotels.approve` | 403, 409 not pending      |
| `/hotels/{hotel}/reject`      | POST   | `reason` (req)                                                                            | redirect plus flash toast                              | `permission:hotels.approve` | 403, 409 not pending, 422 |
| `/hotels/{hotel}/archive`     | POST   | `reason` (opt)                                                                            | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 409 already archived |
| `/hotels/{hotel}/contract`    | PATCH  | `contract_ends_on` (req)                                                                  | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 422 end before start |
| `/hotels/{hotel}/pause`       | POST   | none                                                                                      | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 409 not active       |
| `/hotels/{hotel}/resume`      | POST   | none                                                                                      | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 409 not paused       |
| `/hotels/{hotel}/seat-quotas` | PUT    | `quotas[]` of `department_id` plus `allowed_seats`                                        | redirect plus flash toast                              | `permission:hotels.manage`  | 403, 422                  |

**Value sourcing**:

| Action            | Value produced / displayed                                 | Source                                                                                                                                                                                                                                             |
| ----------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Directory read    | row order                                                  | `ORDER BY name ASC`, with `id` as a tiebreaker, so paging is deterministic and identical requests cannot reorder                                                                                                                                   |
| Directory read    | Total Hotels                                               | count of `hotels` where `access_state` is not `archived`                                                                                                                                                                                           |
| Directory read    | Pending count                                              | count where `access_state` is `pending`. Not a stat card: it exists for the AC-3 identity and the status filter                                                                                                                                    |
| Directory read    | Ended count                                                | count where `access_state` is `active` and `contract_ends_on` is before today. Not a stat card, same reason                                                                                                                                        |
| Directory read    | Active Contracts                                           | count where `access_state` is `active` and days remaining is greater than the window                                                                                                                                                               |
| Directory read    | Expiring Soon                                              | count where `access_state` is `active` and days remaining is between 0 and the window                                                                                                                                                              |
| Directory read    | the "Next 30 days" caption under it                        | `config('guesvia.hotels.expiring_within_days')`, so the caption and the count can never disagree                                                                                                                                                   |
| Directory read    | Paused Access                                              | count where `access_state` is `paused`                                                                                                                                                                                                             |
| Directory read    | Used Seats                                                 | sum of active employee accounts across all non archived hotels                                                                                                                                                                                     |
| Directory read    | the "176 / 198 allocated" caption                          | that same sum, over `SUM(seat_quotas.allowed_seats)` for non archived hotels                                                                                                                                                                       |
| Row               | `rank`, the `#` column                                     | the paginator's `from` plus the row's position on the page, not a stored column                                                                                                                                                                    |
| Row               | `name`, `manager`, `city`                                  | `hotels.name`, `hotels.manager_name`, `hotels.city`                                                                                                                                                                                                |
| Row               | `departments`, the "7 depts" figure                        | count of `seat_quotas` rows for that hotel                                                                                                                                                                                                         |
| Row               | `usedSeats` / `totalSeats`, the "58/64" figure             | live count of active employee accounts in the hotel, over the sum of its `allowed_seats`                                                                                                                                                           |
| Row               | `capacityState` (Seats Available, At Capacity, Over Quota) | derived by comparing those two numbers: under, equal, over. A hotel with no `seat_quotas` rows at all reads as Seats Available and shows `0/0`, never At Capacity                                                                                  |
| Row               | `contractEnd`                                              | `hotels.contract_ends_on` formatted, or the literal word Paused when `access_state` is `paused`                                                                                                                                                    |
| Row               | `daysRemaining`                                            | whole calendar days in the app timezone, both sides taken through `startOfDay()`, signed. `null` when paused or when no contract is set. A contract that has ended gives a negative number, which the page renders as Ended rather than as a count |
| Row               | `status`                                                   | the derived overlay in State transitions, falling back to the stored `access_state` for `pending` and `paused`                                                                                                                                     |
| Sidebar           | `employees`                                                | count of active employee accounts in that hotel                                                                                                                                                                                                    |
| Sidebar           | `departments`                                              | count of that hotel's `seat_quotas` rows                                                                                                                                                                                                           |
| Sidebar           | Seat Occupancy percent and the "6 free" figure             | derived from used and allowed; percent rounds half up, and is capped for display at 100 while the over quota pill carries the overflow                                                                                                             |
| Sidebar           | `alerts[0]`, the renewal sentence                          | emitted only when the derived status is `expiring`; the number of weeks is days remaining divided by 7, rounded up                                                                                                                                 |
| Sidebar           | `alerts[1]`, the seat limit sentence                       | emitted only when at least one department is at or over its quota; the names are those departments, joined                                                                                                                                         |
| Sidebar           | `quotas[]`                                                 | `seat_quotas` joined to `departments`, each carrying its live used count and its derived state                                                                                                                                                     |
| Filters           | the status and capacity option lists                       | fixed lists built from the enums, translated through `__()`                                                                                                                                                                                        |
| Create            | the new hotel's state                                      | fixed: always `pending`, never taken from the form                                                                                                                                                                                                 |
| Create            | `slug`                                                     | derived from `name`, made unique by appending a counter                                                                                                                                                                                            |
| Create            | field limits                                               | `name`, `city` and `manager_name` max 120 characters, `manager_email` max 255 and validated as an email. `manager_email` is deliberately **not** unique: one person may run two hotels                                                             |
| Approve           | the new `contract_starts_on`                               | the approval date, which is today on the server clock                                                                                                                                                                                              |
| Approve           | the new `contract_ends_on`                                 | the old end date moved by the same offset the start date moved, so the duration is preserved                                                                                                                                                       |
| Resume            | the new `contract_ends_on`                                 | the old end date plus the whole calendar days between `paused_at` and now, both taken through `startOfDay()` in the app timezone. A pause shorter than one calendar day therefore adds zero days                                                   |
| Any mutation      | the audit `changes` payload                                | the model's dirty attributes before and after the change, taken from Eloquent rather than rebuilt by hand                                                                                                                                          |
| Seat quota change | the audit `changes` payload                                | the batch shape `{"quotas": {"<department_id>": {"from": N, "to": M}}}`, listing only the departments whose allowed seats actually changed, so one save is one audit row                                                                           |
| Any mutation      | the audit `actor_id` and `ip`                              | the signed in user and the request IP                                                                                                                                                                                                              |
| Login             | which refusal message a blocked user sees                  | the hotel's `access_state`, plus the derived ended check when that state is `active`                                                                                                                                                               |

**Key invariants**:

1. **Nothing derived is stored.** There is no `used_seats` column, no stored `expiring` or `ended`, and no nightly job keeping a status column honest. Every one of those numbers is computed when the page is read.
2. **Nothing is deleted.** Archive sets `archived_at` and preserves every row, per DATA-10. No `SoftDeletes` trait is used, because an `archived_at` timestamp is explicit and does not silently filter later queries.
3. A user belongs to at most one hotel and at most one department (ORG-03).
4. **Every mutation writes an audit row.** A change that reaches the database without one is a defect (SEC-06, ADM-03).
5. Authorization is decided on the server. The global scope is a safety net, not the authorization; the policy is the authorization (ROLE-01, ROLE-02, SEC-01).
6. Enumerated columns are `string` casts to PHP enums, and no migration uses `$table->enum()` or `->after()`, so the same migration runs on SQLite and MySQL 8.4 (AC-19).
7. **Lowering a quota never deletes or deactivates an account.** It only refuses the next one (SUB-03).
8. **Days remaining is invariant across a pause and resume.** All day arithmetic in this spec is whole calendar days in the app timezone, with both sides taken through `startOfDay()`. That single rule is what makes the invariant testable rather than flaky around midnight, and it is why a pause of a few hours returns the same number of days it started with.
9. The seat count means active employee accounts. Managers do not consume employee seats.

**Security model**:

Three roles, already seeded by spec 0001. `hotels.view`, `hotels.manage` and `hotels.approve` are held by the Super Admin alone, so in practice this whole screen is the platform owner's. The manager and employee roles reach it only through the tenant scope, which matters for the features behind this one.

`ScopedToHotel` is a trait adding a global scope: for a user who is not a Super Admin it constrains the query to their own `hotel_id`. Applied to `Hotel`, `SeatQuota` and `User`. Behind it, `HotelPolicy` authorizes `viewAny`, `view`, `create`, `update`, `approve`, `archive`, `manageContract` and `manageSeats`, and `Gate::before` from spec 0001 still gives the Super Admin everything.

A refused request is **403**, never a 404 and never a redirect, so a blocked user can tell a boundary from a broken link. Hiding a row or a button is presentation only.

Personal data stays within PRIV-03: a manager's name and email, nothing more. No new compliance scope is triggered.

**Configuration required**:

No new environment variables and no credentials. Two values are added to `config/guesvia.php`, which spec 0001 created:

- `guesvia.hotels.expiring_within_days`: how many days before a contract ends counts as expiring soon. Default `30`.
- `guesvia.hotels.per_page`: rows per page in the directory. Default `15`.

**Critical test scenarios**:

- Happy path: a Super Admin loads `/hotels` and every stat card, row and sidebar figure matches the seeded portfolio, verifies **AC-1**, **AC-3**, **AC-8**.
- Derived status: a hotel whose contract ends inside the window reads as `expiring` with no column written, and moving the configured window moves it back to `active`, verifies **AC-2**, **AC-4**.
- Pause invariant: pause a hotel, travel the clock forward, resume, and assert days remaining is exactly what it was before the pause, verifies **AC-5**.
- Over quota: lower a department's quota below its live usage, assert no account changed, the hotel reads over quota, and creating one more account in that department is refused, verifies **AC-6**, **AC-7**.
- Boundary: a hotel with no seat quotas at all reads as Seats Available showing `0/0`, not At Capacity, and a hotel whose contract ended yesterday shows Ended rather than a negative day count, verifies **AC-1**, **AC-6**.
- Auth/permission: a manager of hotel A requests hotel B by direct URL and by form POST, and receives 403 both times, verifies **AC-10**.
- Approval timing: approve a pending hotel some days after it was created and assert the contract duration is unchanged while both dates moved, verifies **AC-13**.
- Blocked login: a user attached to a paused hotel is refused at login, the message names the reason, and their rows still exist afterwards, verifies **AC-15**.
- Audit: each of approve, reject, archive, extend, pause, resume and a quota change writes exactly one audit row with before and after values, verifies **AC-16**.
- Portability: the full migration set runs clean on SQLite and the seeder produces the eight hotels, verifies **AC-19**.

## Build plan

Ordered as a **Tracer Bullet**, the approach your scope sets: slice 1 runs one thin thread through every layer, from migration to a number on the page, before anything is filled in. Atomic tasks live in each child spec.

1. **[0002-foundation.md](0002-foundation.md), the thread**: the `hotels` table, the `Hotel` model, `ScopedToHotel`, `HotelPolicy`, and the single Total Hotels stat card reading the database. One number, every layer, proven, satisfies **AC-1**, **AC-10**, **AC-19**, **AC-20**.
2. **[0002-foundation.md](0002-foundation.md), thicken**: `departments`, `seat_quotas`, `audit_logs`, the three `users` columns, the model methods for seat usage and contract state, and the seeder, satisfies **AC-2**, **AC-6**, **AC-8**, **AC-9**, **AC-16**, **AC-19**.
3. **[0002-directory.md](0002-directory.md)**: all five stat cards, every row, the sidebar with its alerts and quota list, and the empty states, satisfies **AC-1**, **AC-3**, **AC-4**, **AC-18**.
4. **[0002-directory.md](0002-directory.md)**: server side search, both filters, Reset and the pager, with state in the query string, satisfies **AC-11**.
5. **[0002-create-and-approve.md](0002-create-and-approve.md)**: the Add Hotel button at the top, the Quick Actions panel removed, the create dialog and the pending state, satisfies **AC-12**, **AC-17**.
6. **[0002-create-and-approve.md](0002-create-and-approve.md)**: approve and reject, the duration preserving clock start, and the login block, satisfies **AC-13**, **AC-14**, **AC-15**.
7. **[0002-contracts-and-seats.md](0002-contracts-and-seats.md)**: edit and archive, then extend, pause and resume as row actions, satisfies **AC-5**, **AC-17**.
8. **[0002-contracts-and-seats.md](0002-contracts-and-seats.md)**: seat quotas per department, including the over quota path, satisfies **AC-6**, **AC-7**.

## Consequences

**Positive**:

- Spec 0001's ordering constraint is cleared. `users.hotel_id` and the scope land in slice 1, so the ban on admin screens reading real data lifts for every feature behind this one.
- The numbers cannot drift. There is no cached count to reconcile and no scheduled job that quietly stops running.
- One audit table serves hotels now and progress resets, exports and role grants later, with no further migration.
- Deriving `ended` means an expired contract is repaired by moving a date, with no state to correct and no way for the state and the dates to disagree.

**Negative / tradeoffs**:

- **Counting live costs joins.** Every row needs a seat count, and the directory is fifteen rows plus five aggregate cards. Done naively this is an N plus 1 query problem. It has to be `withCount` with constraints, or subquery selects, and the seeded portfolio is too small to notice if it is done wrong. Revisit if the portfolio passes a few hundred hotels.
- **Both filters are derived, not just one.** Capacity compares a live account count against a summed quota, exactly as status compares dates against a configured window. So the directory query carries two derived filters plus a search and a pager, all of which have to compose. That is the real cost of this page, and it is more than the status filter alone.
- **Status cannot be filtered with a plain `WHERE status = ?`.** Because expiring and ended are derived, the status filter becomes a date comparison against a configured window, which is more query code and harder to index than one column would be.
- The frontend contract needs extending. `HotelContractStatus` in `resources/js/types/hotels.ts` is `active | expiring | paused | ended` today and has no `pending` and no `archived`, both of which these rules require.
- Removing the Quick Actions panel contradicts a ground rule in your scope that says the built page is the design reference and its layout does not change. That is your call to make, but the rule should be amended rather than quietly broken.
- Extend, pause, resume, approve, archive and seats all becoming row actions means the Actions column needs an overflow menu, and on a phone the whole row collapses to a card, so those controls need a second home there.
- **AC-7's quota rule has no live caller yet.** Account creation is the deferred Employees feature, so the refusal is tested at the service level here and only gets its end to end test when that screen lands. Do not read a passing unit test as proof that a blocked account creation actually behaves correctly in the product.
- A rejected hotel is indistinguishable from an old archived one except through the audit log and `archive_reason`. If rejection reporting ever matters, that becomes a migration.

**Neutral**:

- `users` gains three columns. Existing accounts get `hotel_id` and `department_id` as null, which is correct for a Super Admin and is what the login block must tolerate.
- `config/guesvia.php` grows a `hotels` section. It already exists from spec 0001, so nothing new is introduced.
- This spec fixes the backend layering for hotels (form request, service, model methods, resource, policy) ahead of scope feature 3, which was meant to settle that pattern generally.

## Follow-up

- [ ] Scope feature 3, the backend layering convention, is effectively decided here for hotels. Either run `/architect backend layering convention` to generalise it, or close feature 3 as covered and let `/sync` record the pattern in `AGENTS.md`.
- [ ] Amend the scope ground rule that freezes the Hotels page layout, now that Create Hotel has moved to the top and Quick Actions is gone. `/scope` owns that file.
- [ ] Root `AGENTS.md` still says spatie is not installed and prescribes a `role` enum. Run `/sync` so the next agent does not design against the wrong authorization model.
- [ ] Confirm with the client whether an ended contract should hard block or allow read only access. This spec chose hard block, which your `AGENTS.md` lists as an open question, and SUB-05 permits either.
- [ ] Decide where the row actions live on a phone, once the table collapses to stacked cards.
- [ ] Manager accounts stay deferred, so `manager_name` and `manager_email` are plain contact fields. Linking them to a real user later is a migration plus a backfill.
