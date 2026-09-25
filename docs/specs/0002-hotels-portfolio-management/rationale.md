# 0002 · Rationale: hotels portfolio management

_The decision record behind [index.md](index.md): the situation it was decided in, the options weighed, and why this one won._

## Context

The Hotels screen exists and matches what the client expects to see, but nothing on it is real. `HotelsController` returns a hardcoded array of six hotels, five stat cards and a sidebar, shaped to a frontend contract in `resources/js/types/hotels.ts` that was built UI first. Every button raises a coming soon toast. There is no `hotels` table, no `departments` table, no seat quota anywhere, and `users` carries no hotel and no department.

That last gap is the pressing one. Spec 0001 shipped roles and permissions and was explicit that it stopped short of tenant scoping: a manager holding `employees.view` currently reaches the platform wide Employees screen, and the only reason nothing leaks is that every admin controller returns sample data. Spec 0001 recorded the consequence as an ordering constraint rather than a preference: no admin screen serves real hotel data until scoping is enforced. So the first real screen to be connected is also the one that has to carry the scoping.

The sample data itself encodes rules nobody has written down, and it contradicts itself. The Expiring Soon card is captioned "Next 30 days" and counts two hotels, yet a row with 29 days left is labelled Active while one with 11 days is labelled Expiring Soon. One hotel shows 50 seats used against 48 allowed, with nothing saying how that is reached or what it prevents. One hotel shows 7 departments and another 4, with no model that could produce a difference. A hotel is shown Paused with no contract end date at all. Each of these is a decision waiting to be made, and building the page without making them means inventing them in code where nobody will find them again.

There is also real commercial weight here. A hotel buys a fixed number of employee seats per department for a fixed contract period, so seat counts and contract dates are what the client bills against. A number that drifts is not a cosmetic bug: it is an argument with a customer. The platform is also the data collection instrument for the client's PhD research, so DATA-10 forbids anything being deleted, which rules out the obvious ways of freeing a seat or removing a rejected hotel.

## Options considered

### Option 1: store every status, keep a cached seat count

One `status` column carrying `pending`, `active`, `expiring`, `paused`, `ended` and `archived`, plus a `used_seats` integer on each hotel and each quota row, updated whenever an account is created or deactivated. A scheduled job walks the table nightly and moves hotels between active, expiring and ended.

**Pros**:

- The directory becomes trivial to query: filtering and sorting are one indexed column, and the five stat cards are five plain counts with no date arithmetic.
- Seat usage costs nothing to read, so fifteen rows and five aggregates are one cheap query even with thousands of hotels.
- The status filter maps directly onto what is stored, which keeps the query code short and obvious.

**Cons**:

- Both stored values go stale, and they go stale silently. If the nightly job stops running, hotels sit in the wrong status and the dashboard lies without raising anything.
- The cached seat count has to be maintained on every path that touches an account: creation, deactivation, reactivation, a hotel transfer, a bulk import. Missing one path leaves a number that is wrong forever with no way to notice.
- It contradicts an instruction already in the scope, which says seat usage is counted live rather than stored in a column that drifts, and `AGENTS.md` refuses a `used_seats` field for the same reason.
- Reconciling a drifted count means a repair job, which is more machinery to write and to trust than the thing it repairs.

### Option 2: store access state, derive time, cache the seat count

The middle path. Store only what an administrator sets, and derive expiring and ended from the dates, but keep a denormalised seat count for speed because it is the expensive part.

**Pros**:

- Removes the nightly job and the worst source of staleness, since dates cannot disagree with themselves.
- Keeps the directory read cheap at any portfolio size, which is the one genuine performance concern here.
- A smaller change than Option 1 to get wrong, because only one value is cached rather than two kinds.

**Cons**:

- It keeps the harder half of the problem. A seat count is mutated from more places than a status ever is, so it is the value more likely to drift, not less.
- It splits the mental model: half the page is trustworthy by construction and half is trustworthy only if every write path was remembered. Nobody reading the code later can tell which half they are looking at without checking.
- It buys performance the project cannot yet measure. The seeded portfolio is eight hotels and the realistic near term is tens, where the difference is unmeasurable.

### Option 3: store access state, derive everything time based and count based (chosen)

Store `pending`, `active`, `paused` and `archived`, the four things a person sets. Compute seat usage from live account counts, and expiring and ended from `contract_ends_on` against today and a configured window, every time the page is read.

**Pros**:

- Nothing can drift, because nothing derived is written down. The dashboard is correct by construction rather than by discipline.
- No scheduled job exists, so there is no job to fail, monitor or restart, and no window where the data is wrong.
- Changing the expiring window is one configuration value, and the stat card, the row pill and the filter all move together because all three read the same source.
- An expired contract is repaired by moving a date. There is no state to correct and no way for the state and the dates to disagree.
- It matches what the scope and `AGENTS.md` already instruct, so the codebase stays internally consistent.

**Cons**:

- It is the most expensive to read. Every row needs a live seat count, so the directory is an N plus 1 query problem unless it is written carefully with constrained counts or subquery selects.
- The status filter is no longer one indexed column. Filtering for expiring means a date range against a configured window, which is more query code and harder to index.
- The cost is invisible during development. Eight seeded hotels will not reveal a badly written count, so the query shape has to be got right deliberately rather than discovered under load.

## Rationale

Context names four forces, and Option 3 is the only one that answers all four.

The decisive one is that these numbers are commercial. A hotel is billed for seats and for a contract period, so a seat count that is quietly wrong is a dispute with a paying customer, and a contract that shows as active three days after it ended is access somebody did not pay for. Options 1 and 2 both make correctness depend on every future write path remembering to update a cached value, and on a scheduled job continuing to run. That is a discipline guarantee. Option 3 makes it a structural guarantee: the number is right because it is recomputed, and there is no path that could make it wrong. For a value the client bills against, structural beats disciplined.

The second force is that the project has already decided this. The scope's own wording for this feature asks that seat usage be counted live instead of stored in a column that drifts, and `AGENTS.md` explicitly refuses the `used_seats` field the entity list proposed, for exactly this reason. Choosing Option 1 or 2 would mean overturning a written project rule to buy performance nobody has measured, on a portfolio of eight hotels. That is the wrong trade at this size, and it would leave the codebase arguing with its own documentation.

The third force is operational reality. A nightly job that moves hotels between statuses is a new thing to deploy, monitor and page someone about, and its failure mode is silent wrongness rather than a loud error. There is no queue worker running in production for this project yet, no scheduler set up, and no alerting. Adding a correctness dependency on infrastructure that does not exist is the kind of elegant looking decision that costs a weekend six months later.

The performance cost is real and it is the honest price of this choice, so it is written into Consequences rather than waved away. The mitigation is specific rather than hopeful: constrained counts or subquery selects on the directory query, fifteen rows per page, and a note to revisit if the portfolio passes a few hundred hotels. Given a realistic ceiling of tens of hotels for a single client running a PhD study, that ceiling is a long way off, and if it is ever reached the fix is a cache added on top of a correct calculation, which is a much safer direction of travel than trying to make a drifted cache correct after the fact.

Two smaller calls were made here rather than asked. The enum column is named `access_state` rather than `status`, because the page displays a `status` that mixes stored state with derived date rules, and giving both the same name would guarantee somebody eventually filters the wrong one. And rejection archives the hotel with a reason rather than adding a fifth state, because a `rejected` state would have to be carried through every query, filter and status pill to express something the audit log already records faithfully; the cost of that is a rejected hotel being indistinguishable from an old archived one without reading the reason, which is named in Consequences as the price.

One point where the design pushes back on the request. Extend Contract, Pause and Resume were chosen as row actions rather than sidebar actions. That is the right call for working across a portfolio quickly, and it is what you asked for. It does mean the Actions column carries view, edit, seats, contract, pause and archive, which is more than a row can hold comfortably, so it needs an overflow menu, and on a phone the row becomes a card and those controls need a second home. That is a real cost of the choice rather than a reason to overturn it, and it is recorded in Consequences and Follow-up so it is decided deliberately at build time.
