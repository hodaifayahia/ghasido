# 0002 · Foundation: tables, scoping, audit log and seeder

_Child of [index.md](index.md). The shared contract (data model, state machine, value sourcing, invariants, security, configuration) lives there and is not restated here._

Covers scope feature 2. Satisfies **AC-2**, **AC-6**, **AC-8**, **AC-9**, **AC-10**, **AC-16**, **AC-19**, **AC-20**, and opens **AC-1**.

## Summary

The data layer everything else stands on: four new tables, three columns on `users`, the models with the methods the pages call, tenant scoping, and a seeder that reproduces the portfolio currently on screen. It is split into two steps on purpose. Step one runs a single thin thread from migration to one number on the page, so the whole pipe is proven before any of it is filled in. Step two thickens it.

This is also where spec 0001's ordering constraint is cleared. `users.hotel_id` and the global scope land in step one, which is what lifts the ban on admin screens reading real data.

## Why this shape

The thread first ordering is the scope's Tracer Bullet approach applied literally. The risky parts here are not the columns, they are the joins between layers: a global scope that also has to let a Super Admin through, a policy that has to return 403 rather than redirect, and an Inertia prop that has to survive the existing frontend contract. Proving those with one stat card costs an hour and turns every later step into filling in a known shape.

The seeder reproduces the six hotels already on screen, with their real seat numbers, so the page looks identical after the swap to real data. Any visible difference is then a bug rather than a question. Two extra hotels, one pending and one archived, exist because the sample data never had those states and they would otherwise go untested until the approval work.

## Build plan

1. Create the `hotels` migration with the columns in the index's data model sketch. String columns cast to enums, no `$table->enum()`, no `->after()`. Confirm it runs on SQLite and MySQL 8.4 before moving on, satisfies **AC-19**.
2. Add `App\Enums\HotelAccessState` (`pending`, `active`, `paused`, `archived`) and the `Hotel` model with `#[Fillable]` and `#[Hidden]` class attributes plus a `casts()` method, per Laravel 13 style. Add `@property` PHPDoc for PHPStan level 7, satisfies **AC-2**.
3. Add the `users.hotel_id`, `users.department_id` and `users.status` migration, the `App\Enums\AccountStatus` enum, and the `hotel()` and `department()` relations on `User`. Existing rows take null, which is correct for a Super Admin, satisfies **AC-10**.
4. Add the `ScopedToHotel` trait (a global scope constraining to the signed in user's `hotel_id`, letting a Super Admin through) and apply it to `Hotel`. Add `HotelPolicy` with `viewAny`, `view`, `create`, `update`, `approve`, `archive`, `manageContract` and `manageSeats`, registered through `#[UsePolicy]` or the policy map, satisfies **AC-10**, **AC-20**.
5. **The thread.** Replace only the Total Hotels stat card in `HotelsController` with a real count, leaving every other figure hardcoded. Add a feature test asserting the card matches the seeded rows, and a second asserting a manager of another hotel gets 403 on a hotel that is not theirs, satisfies **AC-1**, **AC-10**.
6. Thicken the schema: the `departments`, `seat_quotas` and `audit_logs` migrations, with the indexes and unique constraints in the index's sketch. Add the `Department`, `SeatQuota` and `AuditLog` models and their relations, satisfies **AC-8**, **AC-9**.
7. Add the global department slug uniqueness rule (`Rule::unique('departments','slug')->whereNull('hotel_id')`) in a shared trait under `app/Concerns/`, since create and edit both need it, satisfies **AC-9**.
8. Add the model methods the pages call, so no controller ever writes a query: `Hotel::usedSeats()`, `allowedSeats()`, `daysRemaining()`, `derivedStatus()`, `capacityState()`, and `SeatQuota::usedSeats()`. Seat counts are active employee accounts only. Two boundaries to get right from the start: a hotel with no `seat_quotas` rows reads as Seats Available showing `0/0`, never At Capacity, and every day calculation uses whole calendar days in the app timezone with both sides taken through `startOfDay()`, satisfies **AC-6**, **AC-2**, **AC-20**.
9. Add `AuditLog::record()` and call it from a single place in the service layer, so a mutation cannot land without one. Capture the model's dirty attributes before and after, plus actor and IP. Give it a second shape for a batch change, since a seat quota save alters several rows and must still produce one audit row: the quotas shape named in the umbrella's Value sourcing table. Settle both shapes here, rather than letting two different call styles appear later, satisfies **AC-16**.
10. Add `HotelFactory`, `DepartmentFactory` and `SeatQuotaFactory` with states for each access state, then `HotelPortfolioSeeder`: the six hotels from the current page plus one pending and one archived. **It must also create the active employee accounts behind each seat figure**, because usage is counted from `users`. Without them every hotel seeds at zero seats used and the page looks broken. The over quota hotel gets 50 active employees against a quota of 48, satisfies **AC-19**, **AC-6**.
    The current sample data is internally inconsistent, so the seeder reproduces its **shape**, not its contradictions: the paused hotel keeps real contract dates (the page shows the word Paused in place of the date, the date itself is not null), and every seeded hotel is a state the model can actually represent.
11. Add `guesvia.hotels.expiring_within_days` (30) and `guesvia.hotels.per_page` (15) to `config/guesvia.php`, satisfies **AC-4**.
12. Write the cross tenant tests: a manager and an employee of hotel A each get 403 on hotel B by direct URL and by form POST, satisfies **AC-10**.

## Watch out for

- The seat count query is the one thing that will be quietly wrong at this size. Write it as a constrained `withCount` or a subquery select from the start; eight seeded hotels will never reveal an N plus 1.
- `ScopedToHotel` on `Hotel` has to let a Super Admin see everything. Test that before trusting it, because the failure mode is an empty page rather than an error.
- `users.hotel_id` is null for every existing account including the platform owner. Nothing may assume it is set.
- Nothing in this spec creates a hotel owned department. The schema allows one, the seeder fills the shared catalogue, and the screen that creates them is the deferred Departments feature.
