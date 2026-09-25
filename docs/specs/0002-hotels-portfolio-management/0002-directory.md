# 0002 · Directory: real rows, stat cards, sidebar, search and paging

_Child of [index.md](index.md). The shared contract (data model, state machine, value sourcing, invariants, security, configuration) lives there and is not restated here._

Covers scope features 4 and 6. Satisfies **AC-1**, **AC-3**, **AC-4**, **AC-11**, **AC-18**.

## Summary

Takes the page from sample data to real rows. Every stat card, every table row and the whole sidebar read the database, then search, both filters, Reset and the pager move to the server with their state in the query string.

The frontend contract in `resources/js/types/hotels.ts` is already built and is treated as the shape the controller fills, not something to redesign. It does need two additions: `HotelContractStatus` has no `pending` and no `archived`, and both are now real.

## Why this shape

The stat cards come first because they are the cheapest way to prove the derived status rules work, and they are what makes a wrong rule obvious. If Total Hotels does not equal the sum of the other buckets, something in the derivation is wrong and it shows immediately.

Search and paging come after the page is already reading real data, rather than alongside, because a filter built against sample data cannot be checked. With the seeded portfolio of eight hotels in place, each filter has something real to select from.

## Build plan

1. Extend `HotelContractStatus` in `resources/js/types/hotels.ts` to `pending | active | expiring | paused | ended | archived`, and add `archived` to the status filter option list. Run `npm run types:check` to find everything the widened union breaks, satisfies **AC-3**.
2. Build `HotelResource` and `HotelOverviewResource` shaping exactly the existing prop contract, so the Vue side needs no change beyond the widened type. Resources shape, they do not query, satisfies **AC-20**.
3. Replace the five stat cards with real aggregates per the index's Value sourcing table. The buckets are mutually exclusive and archived hotels are excluded, so the four state counts plus Pending sum to Total Hotels, satisfies **AC-1**, **AC-3**.
4. Read the expiring window from `guesvia.hotels.expiring_within_days` in one place and use it for the card, the row pill and the filter, so the "Next 30 days" caption cannot disagree with the count it sits under, satisfies **AC-4**.
5. Replace the table rows with real data: name, capacity pill, manager, city, department count, seats used over allowed, contract end (or the word Paused), days left, and the derived status pill, satisfies **AC-1**, **AC-8**.
6. Replace the sidebar: overview fields, contract health, the two alert sentences, the seat occupancy figures and the per department quota list with its live used counts, satisfies **AC-1**.
7. Implement the two alert rules exactly as the index's Value sourcing table names them: the renewal sentence only when the derived status is expiring, with weeks as days remaining divided by 7 rounded up; the seat limit sentence only when at least one department is at or over quota, naming those departments. Both wrapped in `__()`, satisfies **AC-1**.
8. Select the first row of the current page into the sidebar on load, and swap it when View is clicked, keeping the selected hotel in the query string so a refresh holds it, satisfies **AC-18**.
9. Add the empty and error states: the table when nothing matches, the sidebar when no hotel is selectable. Skeletons while loading. Never a blank card, satisfies **AC-18**.
10. Move search to the server across hotel name, manager name and city, matching what the input's placeholder promises. Sort by `name` ascending with `id` as a tiebreaker, so the `#` column and the auto selected first row stay stable across identical requests, satisfies **AC-11**, **AC-18**.
11. Move both filters to the server. **Both are derived, not just status**: expiring and ended become date comparisons against the configured window, and the capacity filter compares a live account count against a summed quota. Budget for both being subqueries rather than column matches, satisfies **AC-11**, **AC-4**.
12. Move the pager to the server at `guesvia.hotels.per_page`, keep search, both filters and the page in the query string, and make Reset clear them. Use Inertia partial reloads so the shell does not repaint on every keystroke, satisfies **AC-11**.
13. Screenshot the page at 1280×853 and compare it against the current sample data page region by region, then re check at 1240×698 and 390×844 for overflow, satisfies **AC-1**.

## Watch out for

- The status filter is the part most likely to go subtly wrong, because expiring and ended are derived. Test each filter value against a seeded hotel that should and should not match.
- Search plus filters plus paging have to compose. Filtering then paging then searching must give the same result set in any order, and the pager's total has to reflect the filters, not the whole table.
- Debounce the search input, and use `router.reload({ only: [...] })` rather than a full visit, or every keystroke repaints the sidebar.
- The `#` column is the paginator's `from` plus the row position, not a stored rank. On page two it starts at 16. It is only meaningful because the sort order is fixed; without it the same hotel gets a different number on each load.
- Two boundary cases the seeded data will not show you: a hotel with no quotas must read as Seats Available with `0/0`, and an ended contract shows Ended rather than a negative day count.
