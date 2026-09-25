# 0002 · Contracts and seats: edit, archive, pause, resume and quotas

_Child of [index.md](index.md). The shared contract (data model, state machine, value sourcing, invariants, security, configuration) lives there and is not restated here._

Covers scope features 8, 9 and 10. Satisfies **AC-5**, **AC-6**, **AC-7**, **AC-16**, **AC-17**.

## Summary

The remaining actions, all of them on the row. Edit reopens the create dialog with the hotel loaded, Archive stops access while keeping every row, Extend Contract moves the end date, Pause freezes the clock and Resume gives back exactly the days that were paused. Then seat quotas per department, including the over quota path.

## Why this shape

Pause and resume are the delicate part, so they come with the contract work rather than bolted on. The freeze rule is what makes Paused Access safe to offer a paying customer: they keep the days they bought. That turns into one invariant worth testing directly, that days remaining is unchanged across a pause and a resume, because every other way of testing it passes for the wrong reason.

Seat quotas come last because they are the only part that needs real accounts to mean anything, and because the over quota path is deliberately permissive in a way that is easy to get backwards. Lowering a quota below current usage is allowed. It blocks the next account and touches none of the existing ones, which is SUB-03 and is the opposite of what a validation rule would naturally do.

## Build plan

1. Add `UpdateHotelRequest` reusing the create rules through a shared trait in `app/Concerns/`, and `HotelService::update()` writing the audit row. Edit opens the same dialog with the hotel loaded, satisfies **AC-16**, **AC-20**.
2. Add `HotelService::archive()`: set `archived_at`, take an optional reason, write the audit row, and delete nothing. Archived hotels drop out of the default directory view and can be filtered back in, satisfies **AC-3**, **AC-16**.
3. Add `HotelService::extendContract()`: move `contract_ends_on`, refuse an end date before the start date, and write the before and after values to the audit row. A hotel whose contract had ended returns to active on its own, because the status is derived, satisfies **AC-16**.
4. Add `HotelService::pause()`: record `paused_at`, flip the state to `paused`, refuse with 409 when the hotel is not active. While paused the row shows the word Paused in place of both the contract end date and the days left, satisfies **AC-5**, **AC-16**.
5. Add `HotelService::resume()`: move `contract_ends_on` forward by the whole calendar days between `paused_at` and now, both taken through `startOfDay()` in the app timezone, then clear `paused_at`, flip back to `active`, and refuse with 409 when not paused. A pause shorter than one calendar day adds zero days, which is the rule that keeps the invariant test stable around midnight, satisfies **AC-5**, **AC-16**.
6. Build the row Actions column as an overflow menu on `ui/dropdown-menu`: View, Edit, Manage seats, Extend Contract, Pause or Resume, Archive, plus Approve and Reject on a pending hotel. Only the actions the user's permissions allow are rendered, and the server checks each one again regardless, satisfies **AC-17**.
7. Decide and build where those actions live below 768px, where the table becomes stacked cards. The overflow menu becomes a bottom sheet, satisfies **AC-17**.
8. Add `SeatQuotaService::sync()` taking the full set of department quotas for a hotel, writing **one** audit row for the whole save in the batch quotas shape named in the umbrella's Value sourcing table, listing only the departments whose allowed seats actually changed. Allow an `allowed_seats` value below current usage, satisfies **AC-7**, **AC-16**.
9. Build the Manage seats dialog listing every department in the hotel's catalogue with its live used count and its allowed seats, showing the Available, Full and Over quota states as the sidebar does, satisfies **AC-6**, **AC-7**.
10. Enforce the quota on the server at account creation time: creating an employee in a department at or over its quota is refused with a message naming the department. This is the rule that gives the quota teeth; the dialog itself never blocks lowering, satisfies **AC-7**.
11. Write the tests: the pause and resume invariant with the clock moved forward; archive keeps every row and drops out of the default view; extend on an ended hotel brings it back with no state change; lowering a quota below usage changes no account and refuses the next one; each action writes exactly one audit row, satisfies **AC-5**, **AC-6**, **AC-7**, **AC-16**.

## Watch out for

- The quota check at account creation time belongs to the Employees feature, which is deferred in your scope. Build the server side rule here so the quota is real, and test it at the service level. Do not mistake that passing test for end to end proof: nothing calls the rule in the product until the Employees screen lands.
- Resume uses whole calendar days in the app timezone, through `startOfDay()` on both sides, so a pause of a few hours adds zero days. That rule is settled in the umbrella spec; do not re invent it here.
- Archiving a hotel does not deactivate its accounts. Access is stopped by the login block from the previous child spec, not by touching user rows, which is what keeps DATA-10 true.
- The Actions column now carries up to eight items. If it is built as buttons rather than a menu it will break the row at 1240 wide, which is the width you work at.
