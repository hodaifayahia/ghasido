# Spec 0005 — build log and handoff

Read [index.md](index.md) first. Any agent can continue from the status table below.

**Last update:** 2026-09-24, post-audit fixes (index.md §5) — all four phases and the fixes green.

## Status

| Phase | Scope                      | Status                                                                                                                                                         |
| ----- | -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | Access hardening           | **Done, green** — 707 tests pass, PHPStan no new errors, `npm run check` + `types:check` pass                                                                  |
| 2     | Smarter role-play AI       | **Done, green** — 727 tests pass, PHPStan no new errors, frontend gates pass, screenshots checked (see phase log)                                              |
| 3     | Learner engagement         | **Done, green** — 749 tests pass, PHPStan no new errors, frontend gates pass, screenshots checked                                                              |
| 4     | Manager and admin insights | **Done, green** — 766 tests pass, PHPStan 11 errors (all pre-existing; one old error fixed), frontend gates pass, screenshots checked                          |
| §5    | Post-audit fixes           | **Done, green** — five review findings fixed with tests (see the phase log below and index.md §5); full suite, PHPStan (no new errors) and frontend gates pass |

## Before using the dev app (Sail) — run once

Spec 0005 added seven migrations (`2026_09_24_100001` … `100007`) and one permission. Sail was down when they were written, so the developer database has not been migrated:

```bash
./vendor/bin/sail artisan migrate    # includes 2026_09_24_100006 (grants scores.override to the Super Admin) and 100007 (graded_level columns)
```

Do **not** run `RolesAndPermissionsSeeder` on an existing database for this: it re-syncs every built-in role and would wipe permission edits made on the Roles & Permissions screen.

Without the migration, finishing a test fails (no `users.english_level` column).

## How to run the gates (Windows host, repo in WSL)

```bash
# inside WSL, repo root
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
vendor/bin/pint --parallel <changed paths>     # never the whole tree: other work is uncommitted
vendor/bin/phpstan analyse --memory-limit=1G
npm run check && npm run types:check
```

## Pre-existing gate failures (not from this spec)

PHPStan level 7 reports 12 errors that predate spec 0005, all in the subscriptions/checkout work: `Admin/SubscriptionsController.php` (58, 104×2, 108×2), `CheckoutController.php:66`, `Requests/Admin/Subscriptions/UpdateSubscriptionPlanRequest.php` (23, 39), `Services/Ai/UsageMeter.php:183`, `Services/Hotels/SeatQuotaService.php` (98, 100), `Services/Subscriptions/EmployeeAiPointsService.php:58`. Left alone per AGENTS.md §8 ("say so rather than fixing unrelated code"); `UsageMeter.php:183` will be fixed in phase 4 when that file is touched.

## Phase log

### Phase 1 — access hardening (2026-09-24)

See index.md §1 for every finding, fix and test. Side effects worth knowing:

- Pint (run on `app/Http`, `app/Policies`, `app/Services/Reports`, `tests/Feature`) also normalised line endings / import order in a few untouched files (`RoleController.php`, `UpdateSubscriptionPlanRequest.php`, `RoleManagementTest.php`, `ManagerTrainingTest.php`, `routes/settings.php`). Style only.
- Role-play and voice-call starts now 404 when the block does not offer the scenario. The learner UI only ever lists linked scenarios (`BlockPresenter::scenarios`), so nothing visible changes; seeded or imported blocks must carry their `block_ai_scenario` rows.
- Spoken answers in the Pre/Post-test now upload against the open sitting (`recordable_type=test_attempt`). This was broken before (the runner sent id 0 and got a 422).

### Phase 2 — smarter role-play AI (2026-09-24)

See index.md §2. Notes for the next agent:

- The `AiProvider` interface gained an optional `?EnglishLevel $level` on `roleplayReply`, `evaluateRoleplay`, `evaluateWriting`, `evaluateSpeaking`. Any test double implementing it must match (see `tests/Feature/Ai/AiJobsTest.php`).
- Role-play prompts live only in `App\Services\Ai\RoleplayPrompt`; never rebuild one inside a provider.
- The Anthropic provider sends no `temperature`: current Claude models reject sampling parameters with a 400 and the model id is configuration.
- **Visual check without Sail** (Docker was down): `~/.guesvia-vite-up.sh` style — `VITE_PORT=5174 npx vp dev` in WSL (the laravel plugin did not write `public/hot` under vp, so write `http://127.0.0.1:5174` into it by hand), harness on `HARNESS_PORT=8097`, and `PW_NO_CORS=1 node storage/harness/shoot.mjs …` (new opt-in flag: that Vite only allows its own origin). Delete `public/hot` and stop Vite afterwards. Job: `storage/harness/jobs/spec5-phase2.json`; data probe: `storage/harness/probe-0005-roleplay-limit.php`.
- Screens checked at 1280×853, 1240×698, 390×844: the chat's turn-limit panel, the Detailed Answers "Adjust" action and dialog, the role-play dialog, the phone bottom sheet. They reuse the Reports modal and card recipe; no new tokens. The chat page logs "Hydration completed but contains mismatches" in the harness; not investigated (the harness runs Inertia SSR through the dev Vite).

### Phase 3 — learner engagement (2026-09-24)

See index.md §3. Notes for the next agent:

- Two more migrations (`2026_09_24_100003` phrasebook review columns, `2026_09_24_100004` `ai_insights`); run `sail artisan migrate` on the dev database as noted at the top.
- `AiProvider` gained `coachLearner(array $context, ?EnglishLevel $level)`. `FakeAiProvider` is `final`; tests that need a failing provider use `$this->mock(AiProvider::class, …)`.
- The coach and the streak are page props of Home and My Progress, not shared props, so they never ride on every response.
- Visual check: `storage/harness/jobs/spec5-phase3.json` (+ `-recheck`), data probe `storage/harness/probe-0005-engagement.php` (streak, saved phrases, coaching). The probe needs `QUEUE_CONNECTION=sync` so the coaching job runs inline. Screens checked at 1280×853, 1240×698 and 390×844: Home (continue state), My Progress, My Phrasebook, review (card, meaning shown, finished), Post-test entry.
- Home's continue state is ~919px tall at 1280 wide (the content column scrolls, the sidebar stays put); the approved Pre-test state still fits 853.

### Phase 4 — manager and admin insights (2026-09-24)

See index.md §4. Notes for the next agent:

- The Dashboard gained one row under the approved mockup rows (at-risk learners + AI briefing); nothing above it changed. The at-risk rules live in `DashboardStats::atRisk`; the briefing in `App\Services\Dashboard\DashboardBriefing` (portfolio briefing: `ai_insights.subject_type = 'portfolio'`, id 0).
- `AiInsight::needsRefresh()` / `stateOf()` are the one refresh rule for every AI insight (coach, briefing); reminder drafts reuse the table with kind `reminder_draft`.
- `AiProvider` gained `briefDashboard()` and `draftReminder()`; any test double must implement them.
- `UsageMeter::record()` now prices rows from `ai_model_prices` and takes an optional `hotelId`; `GenerateAudioClip::handle()` takes a `UsageMeter` (tests calling `handle()` directly pass `app(UsageMeter::class)`).
- Visual check: `storage/harness/jobs/spec5-phase4.json`, probe `storage/harness/probe-0005-usage.php` (a month of usage rows and two prices). Checked: Super Admin and manager dashboards (1280, 1240, 390), AI usage (1280, 390), template dialog with a draft filled in.
- Open for the client: whether managers should draft free-text reminders too (today only template authors — the Super Admin — can use Draft with AI; the send dialog has no free subject/body).

### Post-audit fixes (2026-09-24)

See index.md §5. Notes for the next agent:

- One more migration (`2026_09_24_100007`: `attempts.graded_level`, `roleplay_attempts.graded_level`, both cast to `EnglishLevel`, nullable = the fixed scale). The `AiProvider` interface did not change.
- `Attempt::gradingLevel()` is the one place that decides which bar an AI evaluator uses; the three evaluation jobs read it and store it. Do not pass `$attempt->user->english_level` to an evaluator directly.
- The reply prompt is now `RoleplayPrompt::replySystemParts()` (`stable` + `turn`); `replySystem()` joins them for providers without prompt caching. Anything that must change per turn goes in `turn`, never in `stable`, or the Anthropic cache never hits. `AnthropicAiProvider::complete()` takes an optional fourth argument for such an uncached trailing system block.
- `RoleplayService` and `VoiceCallService` now take `ProgressService` in their constructors (container-resolved everywhere; nothing constructs them by hand).
- `LearnerCoach::context()` keys changed (`pre_test` / `post_test` objects with `taken` + `percent`, in place of `pre_test_percent` / `post_test_percent`), so every stored fingerprint differs once: each active learner gets one summary refresh after deploy, then the normal cooldown applies.
- The phrasebook review POST (`learn.phrasebook.review.store`) now requires `version`; the cards carry it as `reviewCount`. A client that omits it gets a 422.
