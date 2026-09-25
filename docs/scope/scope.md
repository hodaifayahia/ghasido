# Scope: Guesvia Hotels

Hotels is the Super Admin corner of Guesvia, the English training platform for hotel staff. It is where the client onboards a hotel, approves it, decides how many employee seats each department gets, and watches contract periods run down.

**Build approach:** Tracer Bullet (prove the whole pipe works end to end before building any part of it fully).
**Workflow:** Beta (after `/develop`, run `/check verify`, then `/test`). The project default level of rigor. `/architect` is the recommended first stop for a feature with a real decision, but skippable when you already know the build. Any feature can carry its own tag (e.g. `· GA`) to do more or less.

_These are recommendations to keep your build orderly, not requirements. Skip anything that does not fit: if you already know how to build a feature, use `/develop` and skip `/architect`. You decide when a feature is `done`._

## Ground rules you set

Every feature below is built inside these. `/architect` reads them and does not reopen them.

- **Layering:** a form request validates, a service does the work, model methods do the reading, a resource shapes what goes out, a policy and middleware guard it. Controllers stay thin and write no queries. A custom query only where a model method genuinely cannot carry it.
- **Roles:** `spatie/laravel-permission`, with middleware on the routes. Your `AGENTS.md` currently recommends a role enum plus policies instead, so feature 1 records the switch and updates the doc.
- **Responses:** resources shape the data, but it travels as Inertia page props, not as JSON from a separate `routes/api.php`. Same resource classes, same form requests, one auth path.
- **New hotels:** the Super Admin creates them. No public signup page. A new hotel lands pending and nobody under it can sign in until the Super Admin approves the hotel record.
- **Delete:** archive only. Access stops, every row stays (your `AGENTS.md` rule DATA-10).
- **New forms:** create, edit, contracts and seat quotas open as dialogs over the Hotels page, built from the approved tokens, becoming bottom sheets on a phone. There is no client mockup for Hotels, so the built page is the design reference and nothing about its layout changes.

## At a glance

| #   | Feature                              | Phase         | Status      |
| --- | ------------------------------------ | ------------- | ----------- |
| A   | App shell, design system and tokens  | Built already | existing    |
| B   | Authentication (Fortify)             | Built already | existing    |
| C   | Admin screens on sample data         | Built already | in-progress |
| 1   | Roles and permissions                | Foundation    | in-progress |
| 2   | Hotels data model                    | Foundation    | in-progress |
| 3   | Backend layering convention          | Foundation    | planned     |
| 4   | Hotels directory on real data        | Slice 1       | in-progress |
| 5   | Create hotel                         | Slice 1       | in-progress |
| 6   | Directory search, filters and paging | Slice 2       | in-progress |
| 7   | Hotel approval gate                  | Slice 3       | in-progress |
| 8   | Edit and archive a hotel             | Slice 4       | in-progress |
| 9   | Contract periods                     | Slice 4       | in-progress |
| 10  | Seat quotas per department           | Slice 5       | in-progress |

## Built already

Here for context, so nothing below plans them again.

### A. App shell, design system and tokens · existing

The approved sidebar, topbar, page header, script accent, brand assets and the whole `@theme` token set. Locked by `AGENTS.md` section 0. code in `resources/js/components/`, `resources/css/app.css`

### B. Authentication (Fortify) · existing

Login, password reset, two factor, passkeys and the settings screens, all rendered as Inertia pages. Still keyed on email, not on a username. code in `app/Providers/FortifyServiceProvider.php`, `routes/settings.php`

### C. Admin screens on sample data · in-progress

All seven admin screens are built and match their mockups, but every controller returns a hardcoded array and the buttons raise a coming soon toast. Features 4 to 10 take the Hotels screen the rest of the way. The other six wait their turn. code in `resources/js/pages/admin/`, `app/Http/Controllers/Admin/`

## Foundations

### 1. Roles and permissions · in-progress · GA

Three fixed roles and the permissions behind them, enforced by middleware and policies on the server, never by hiding a button. Everything else in this scope sits on top of it, which is why it carries a heavier tier than the rest.
**Done when:** the admin routes sit behind role middleware and a policy, a manager or an employee who types `/hotels` gets a 403 rather than a redirect, the Super Admin overrides every ability, the signed in role reaches Vue through the shared Inertia props, and `AGENTS.md` reflects the package switch.

spec [0001](../specs/0001-roles-and-permissions/index.md) · code in `app/Enums/`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/admin.php`, `database/seeders/`, `resources/js/composables/useCan.ts`, `resources/views/errors/403.blade.php`

- [x] Design it (spec): `/architect roles and permissions`
- [ ] Build it: `/develop roles and permissions`
    - [x] Package, permission catalogue and test harness: install spatie, seed three roles and twenty permissions, and teach `Tests\TestCase` about them before the first test is written (AC-3, AC-11)
    - [x] The `/hotels` thread end to end: one guarded route, the Super Admin override, the shared Inertia props and the filtered nav item, all proven on a single route (AC-1, AC-2, AC-4, AC-5, AC-6)
    - [x] Widen to every admin route and the whole nav map, re checked at 1280×853, 1240×698 and 390×844 (AC-1, AC-6)
    - [x] Where blocked users land: the dashboard branch, the placeholder page and the branded 403 view (AC-7, AC-8)
    - [ ] Seeding on a real database, the existing tests, the negative suite, then `/sync` for `AGENTS.md` (AC-9, AC-10, AC-12) — built and green; only the `/sync` of root `AGENTS.md` (AC-12) is left
- [ ] Verify it: `/check verify roles and permissions`
- [ ] Test it: `/test roles and permissions`
- [ ] Review it (fresh model): `/check review roles and permissions`
- [ ] Document it: `/document roles and permissions`

### 2. Hotels data model · in-progress

The tables every number on the page reads from: hotels, departments, seat quotas, the hotel and department columns on users, and a small audit log so approvals, archives and contract changes leave a trace (your rule SEC-06 asks for one and there is nowhere to write it today).
**Done when:** migrations run clean on both SQLite and MySQL, the models carry the methods the pages call (seat usage, days remaining, contract state) so no controller writes a query, seat usage is counted live instead of stored in a column that drifts, and a seeder fills a realistic portfolio to build against.

spec [0002](../specs/0002-hotels-portfolio-management/0002-foundation.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md) · code in `app/Models/Hotel.php`, `app/Models/`, `app/Concerns/ScopedToHotel.php`, `app/Policies/HotelPolicy.php`, `database/migrations/2026_09_18_*`, `database/seeders/HotelPortfolioSeeder.php`

- [x] Design it (spec): `/architect hotels data model`
- [x] Build it: `/develop hotels data model`
    - [x] The thread: the hotels table, the Hotel model, ScopedToHotel, HotelPolicy, and the Total Hotels card reading the database (AC-1, AC-10, AC-19, AC-20)
    - [x] The rest of the schema: departments, seat_quotas, audit_logs and the three users columns (AC-8, AC-9, AC-16)
    - [x] Model methods for seat usage, days remaining and contract state, including the zero and boundary cases (AC-2, AC-6, AC-20)
    - [x] Factories, the portfolio seeder with the employee accounts behind its seat numbers, and the two config values (AC-4, AC-6, AC-19)
    - [x] The cross tenant 403 tests (AC-10) · covers the route and the policy; the by form POST half arrives with the first write routes in features 5, 7, 8, 9 and 10, each of which carries its own cross tenant test
- [ ] Verify it: `/check verify hotels data model`
- [ ] Test it: `/test hotels data model`

### 3. Backend layering convention · needs a decision

The one pattern every admin feature follows, written down once so the seven features after it just follow it instead of each inventing a shape.
**Done when:** the pattern is recorded with one worked example running the whole way through, and root `AGENTS.md` carries it so every later run reads it without being told.

- [ ] Design it (spec): `/architect backend layering convention`

## Slice 1: Create a hotel and see it listed

The thin thread. One real action travels the entire pipe: fill the dialog, save through the service, and watch the row appear in a directory that is reading from the database.

### 4. Hotels directory on real data · in-progress

Replace the hardcoded array in `HotelsController` so the table, the five stat cards and the sidebar overview all read real rows. The View button on a row picks that hotel into the sidebar, which is pinned to one hotel today.
**Done when:** every number on the page comes from the database, the five stats are computed rather than typed, clicking View swaps the sidebar to that hotel, and the empty, loading and error states render instead of a blank card.

spec [0002](../specs/0002-hotels-portfolio-management/0002-directory.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect hotels directory on real data`
- [ ] Build it: `/develop hotels directory on real data`
    - [ ] Widen the frontend status type for pending and archived, and build the resources (AC-3, AC-20)
    - [ ] The five stat cards and every table row on real data (AC-1, AC-3, AC-4, AC-8)
    - [ ] The sidebar: overview, the two alert rules, seat occupancy and the quota list (AC-1)
    - [ ] First row selection, the empty and error states, and the screenshot check at 1280x853, 1240x698 and 390x844 (AC-18, AC-1)
- [ ] Verify it: `/check verify hotels directory on real data`
- [ ] Test it: `/test hotels directory on real data`

### 5. Create hotel · in-progress

The Create Hotel button opens a dialog instead of raising a toast. The new hotel saves through the service and lands pending, waiting for approval.
**Done when:** the button opens a real dialog, the form request refuses bad input with the errors shown beside the fields, a saved hotel appears in the directory carrying a pending pill, and the dialog becomes a bottom sheet below 768px.

spec [0002](../specs/0002-hotels-portfolio-management/0002-create-and-approve.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect create hotel`
- [ ] Build it: `/develop create hotel`
    - [ ] Add Hotel button at the top of the page, Quick Actions panel removed (AC-17)
    - [ ] Form request, service, the unique slug and the forced pending state (AC-12, AC-16)
    - [ ] The dialog, becoming a bottom sheet below 768px, with the new Pending pill (AC-12, AC-3)
- [ ] Verify it: `/check verify create hotel`
- [ ] Test it: `/test create hotel`

## Slice 2: Find a hotel

### 6. Directory search, filters and paging · in-progress

Search, both filter dropdowns, Reset and the pager are local only today and never reach the server, so they stop being useful the moment the portfolio passes one page.
**Done when:** search, the status filter and the capacity filter all run on the server, the current filter and page live in the URL and survive a refresh, Reset clears them, and the pager walks real pages with real counts.

spec [0002](../specs/0002-hotels-portfolio-management/0002-directory.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect directory search, filters and paging`
- [ ] Build it: `/develop directory search, filters and paging`
    - [ ] Server side search across name, manager and city, with the fixed sort order behind it (AC-11, AC-18)
    - [ ] Both derived filters and Reset (AC-11, AC-4)
    - [ ] The pager with search, filters and page in the query string, on partial reloads (AC-11)
- [ ] Verify it: `/check verify directory search, filters and paging`
- [ ] Test it: `/test directory search, filters and paging`

## Slice 3: Approve and gate access

### 7. Hotel approval gate · in-progress

A new hotel is pending until the Super Admin approves it. Pending means visible in the directory, with no contract running and nobody able to sign in under it.
**Done when:** a Super Admin can approve or reject a pending hotel, approval starts the contract window, anyone attached to a pending or rejected hotel is refused at login with a message that says why, the decision lands in the audit log, and a feature test proves the login block.

spec [0002](../specs/0002-hotels-portfolio-management/0002-create-and-approve.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect hotel approval gate`
- [ ] Build it: `/develop hotel approval gate`
    - [ ] Approve, with the duration preserving clock start (AC-13, AC-16)
    - [ ] Reject as an archive with a reason, plus both row actions (AC-14, AC-17)
    - [ ] The login block for all four blocked states, plus the middleware for a live session (AC-15)
    - [ ] Tests for each blocked situation and each audit row (AC-15, AC-16)
- [ ] Verify it: `/check verify hotel approval gate`
- [ ] Test it: `/test hotel approval gate`

## Slice 4: Edit, archive and contracts

### 8. Edit and archive a hotel · in-progress

The row Edit button opens the same dialog with the hotel loaded, and More actions offers Archive. Archive stops access and keeps every row, as you decided.
**Done when:** edit saves through the same request and service as create, archive marks the hotel inactive without deleting anything, archived hotels drop out of the default directory view and can be filtered back in, and the archive lands in the audit log.

spec [0002](../specs/0002-hotels-portfolio-management/0002-contracts-and-seats.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): covered by the hotels umbrella
- [ ] Build it: `/develop edit and archive a hotel`
    - [ ] Edit through the same request and service as create (AC-16, AC-20)
    - [ ] Archive that keeps every row and drops out of the default view (AC-3, AC-16)
    - [ ] The row actions overflow menu, and where it goes once the table becomes cards (AC-17)
- [ ] Verify it: `/check verify edit and archive a hotel`
- [ ] Test it: `/test edit and archive a hotel`

### 9. Contract periods · in-progress

Extend Contract and Pause Access are stubs today. Give a hotel a real contract window, with extend, pause, resume and end, feeding the days remaining figure, the status pill and the Expiring Soon stat.
**Done when:** the quick action buttons work on the selected hotel, contract state drives the pill and the stat row, a paused or an ended hotel refuses login while keeping all of its data, and every change lands in the audit log.

spec [0002](../specs/0002-hotels-portfolio-management/0002-contracts-and-seats.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect contract periods`
- [ ] Build it: `/develop contract periods`
    - [ ] Extend contract, including bringing an ended hotel back with no state change (AC-16)
    - [ ] Pause and resume, on the whole calendar day freeze rule (AC-5, AC-16)
    - [ ] The invariant test: days remaining unchanged across a pause and resume (AC-5)
- [ ] Verify it: `/check verify contract periods`
- [ ] Test it: `/test contract periods`

## Slice 5: Seat quotas

### 10. Seat quotas per department · in-progress

The Manage seats row button and the sidebar quota list. Each hotel gets an allowed seat count per department, and usage is counted from real accounts rather than from a stored number.
**Done when:** a Super Admin can set allowed seats per hotel per department, the sidebar shows live used against allowed with the available, full and over states, lowering a quota below current usage is allowed and simply blocks new accounts without touching the existing ones, and the Used Seats stat matches the sum.

spec [0002](../specs/0002-hotels-portfolio-management/0002-contracts-and-seats.md) · part of the [hotels umbrella](../specs/0002-hotels-portfolio-management/index.md)

- [x] Design it (spec): `/architect seat quotas per department`
- [ ] Build it: `/develop seat quotas per department`
    - [ ] The quota sync service, with one batch audit row per save (AC-7, AC-16)
    - [ ] The Manage seats dialog with live used counts and the three capacity states (AC-6, AC-7)
    - [ ] The server side refusal at account creation, tested at service level (AC-7)
- [ ] Verify it: `/check verify seat quotas per department`
- [ ] Test it: `/test seat quotas per department`

## Deferred

Out of scope for this pass, kept here so the plan stays honest.

- **Hotel manager accounts**: create the manager login alongside the hotel · needs a decision
- **Username login and no self registration**: the AUTH-01 and AUTH-02 switch away from email login · needs a decision
- **Departments screen on real data**: `/departments` still runs on a sample array · needs a decision
- **Employees screen on real data**: `/employees` plus the seat quota check at account creation time · needs a decision
- **Audit log viewer**: the table lands in feature 2, a screen to read it does not · needs a decision
- **Public hotel signup page**: you chose Super Admin creation only, so this stays parked

## Legend

**The decision box.** Every feature carries exactly one, the sub task whose label ends with `(spec)`. Skills find it by that `(spec)` suffix, never by an exact label. Every other box is an execution box and `/architect` never ticks one.

**Feature lifecycle**: the scope updates as a feature moves.

| State                        | Set by                       | The feature shows                                                                                                                                       |
| ---------------------------- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `planned` · needs a decision | `/scope`                     | one box: `Design it (spec): /architect <feature>`                                                                                                       |
| `in-progress` (designed)     | `/architect` at spec capture | `Design it` ticked, the spec linked, `Build it: /develop <feature>` with 2 to 5 milestones, then `Verify it` and `Test it` for this project's Beta tier |
| `in-progress` (building)     | `/develop`                   | the milestone boxes tick one by one, and the code pointer fills in                                                                                      |
| `in-progress` (verified)     | `/check verify`              | `Build it` and its milestones ticked, `Verify it` ticked                                                                                                |
| `done`                       | you, when you say so         | the boxes you ran ticked, the ones you skipped marked skipped. At Beta, `/test` is the natural point to call it done                                    |

- **Next step** is always the first unticked box, and it is always a command or a tracked milestone.
- **needs a decision** means run `/architect` first. Without the tag, go straight to `/develop`. The tag drops once the spec is captured.
- The atomic build tasks live in the spec's `## Build plan`, never here. The scope carries only the rollup.
- **Status** runs `planned` then `in-progress` then `done`, plus `existing` for what predates this workflow and `dropped` for anything taken out of scope, which is kept for history rather than deleted.
- A **tier tag** beside a heading (`· GA` on feature 1) raises or lowers that one feature's rigor above the project default. No tag means it inherits Beta.
- The **pointer line** (`spec <n> · code in <path>`) is added by `/architect` and `/develop` as those exist.
