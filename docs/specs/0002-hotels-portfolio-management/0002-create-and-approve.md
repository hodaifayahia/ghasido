# 0002 · Create and approve: the Add Hotel button, the pending state and the login block

_Child of [index.md](index.md). The shared contract (data model, state machine, value sourcing, invariants, security, configuration) lives there and is not restated here._

Covers scope features 5 and 7. Satisfies **AC-12**, **AC-13**, **AC-14**, **AC-15**, **AC-16**, **AC-17**.

## Summary

The first writes. Create Hotel becomes a real button at the top of the page opening a real dialog, the Quick Actions panel is removed, and a saved hotel lands pending. Then approval: the Super Admin approves or rejects, approval starts the contract clock while preserving the agreed duration, and anybody attached to a hotel that is not open for business is refused at login with a message saying why.

## Why this shape

Create comes before approve because approve needs something pending to act on, and the seeder's pending hotel is the only other source.

The clock starting at approval rather than at creation is the rule that makes the dates entered in the dialog a plan rather than a promise. A hotel sold a sixty day contract and approved two weeks late should still get sixty days, and the alternative quietly short changes the customer for an internal delay.

Rejection archives rather than adding a fifth state. The audit log already records who rejected it and why, and a `rejected` state would have to be carried through every query, filter and pill to express something already captured. The cost, named in the umbrella's Consequences, is that a rejected hotel reads as archived unless you look at its reason.

## Build plan

1. Move Create Hotel to a button at the top of the page, in the page header area beside the title, and delete the Quick Actions panel and its component. Its Extend Contract and Pause buttons move to row actions in the next child spec, satisfies **AC-17**.
2. Add `StoreHotelRequest` validating name, city, manager name, manager email, contract start and contract end. End must be after start. `name`, `city` and `manager_name` are capped at 120 characters and `manager_email` at 255. The email is validated as an email but is contact data, not a login, and is deliberately **not** unique, because one person may run two hotels, satisfies **AC-12**.
3. Add `HotelService::create()`: derive a unique slug from the name, force `access_state` to `pending` regardless of anything in the request, and write the audit row. The state is never taken from the form, satisfies **AC-12**, **AC-16**.
4. Build the create dialog on `ui/dialog`, becoming a `ui/sheet` with `side="bottom"` below 768px, using the Inertia v3 `<Form>` bound to the Wayfinder form variant, with errors shown beside each field. Give every control a `data-test` attribute, satisfies **AC-12**.
5. Show the saved hotel in the directory carrying a Pending pill, and make sure the new pill renders in both the row and the sidebar, satisfies **AC-12**, **AC-3**.
6. Add `HotelService::approve()`: set `approved_at` and `approved_by`, move `contract_starts_on` to today, move `contract_ends_on` by the same offset so the duration is preserved, flip the state to `active`, and write the audit row. Refuse with 409 when the hotel is not pending, satisfies **AC-13**, **AC-16**.
7. Add `HotelService::reject()`: require a reason, archive the hotel, store the reason in `archive_reason`, and write the audit row naming the action as a rejection so it can be told apart later, satisfies **AC-14**, **AC-16**.
8. Add Approve and Reject as row actions shown only on a pending hotel, both behind `permission:hotels.approve`, with Reject collecting its reason, satisfies **AC-14**, **AC-17**.
9. Extend the login check in `FortifyServiceProvider::authenticateUsing()` to refuse a user whose hotel is pending, paused, archived, or active with a contract end date in the past. Each case gets its own message. The callback replaces the whole credential check, so it must still verify the password with `Hash::check` and return the user or false, satisfies **AC-15**.
10. Add a middleware on the authenticated groups that applies the same check to an already signed in session, since the login callback only guards new logins, satisfies **AC-15**.
11. Write the tests: creating lands pending; approving late preserves the duration while moving both dates; rejecting archives with a reason and adds no new state; each of the four blocked situations refuses login with its own message and leaves every row intact; each mutation writes exactly one audit row, satisfies **AC-12**, **AC-13**, **AC-14**, **AC-15**, **AC-16**.

## Watch out for

- A Super Admin has no `hotel_id`, so the login block must not lock the platform owner out of their own platform. Test that first.
- The login refusal message says which situation applies, which is a small information disclosure to somebody who already holds valid credentials. That is the intended trade: a hotel manager who cannot sign in needs to know whether to call their administrator or wait for approval.
- `authenticateUsing()` replaces the entire credential check. Forgetting `Hash::check` there turns the login form into an open door.
- Approval moves both dates. Assert the duration in the test, not the individual dates, or the test will pass for the wrong reason.
