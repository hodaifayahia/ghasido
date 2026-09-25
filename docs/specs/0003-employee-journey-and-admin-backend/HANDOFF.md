# Spec 0003 — build log and handoff

**Purpose:** any agent (Claude Code, Codex, Copilot, a human) can pick this work up from here. Updated after every build phase. Read `index.md` (the contract) first, then this file, then `storage/harness/README.md`.

**Last update:** 2026-09-20, Lessons & Content repair, manager dashboard, and learner navigation checkpoint.

## 0. How the work is organised

The build runs as five workflow phases. Each phase is a script under `workflows/` that fans out parallel agents ("lanes"), each lane owning a fixed set of files (spec Part A rule 7), followed by an integration lane that makes the whole tree green. The scripts are plain JavaScript for the Claude Code `Workflow` tool; another agent can read them as a precise task list even without that tool: every lane brief is a self-contained specification of files, behaviour, tests and visual verification.

| Phase | Script                                                                  | Lanes                                                                                                                 | Status                                                                                                                                 |
| ----- | ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | `workflows/phase1-foundation.js`                                        | schema-content, schema-learner, schema-messaging, providers, asset-crops → schema-integrate                           | **Done, green** (37 migrations, 300 tests, PHPStan 7 clean)                                                                            |
| 2     | `workflows/phase2-learner-foundation.js`                                | auth-backend → learn-shell, content-seeder, admin-hotels → phase2-integrate                                           | **Done, green** (383 tests, Situation + Home pixel-matched)                                                                            |
| 3a    | `workflows/phase3a-admin-tests-roleplay.js` (`args.only` selects lanes) | admin-departments, admin-employees, admin-lessons-content, admin-messages, admin-reports, admin-dashboard → integrate | **Done, green** (staged files recovered after the 2026-09-18 WSL crash + lessons lane finished; `composer ci:check` clean, 516 tests)  |
| 3a′   | same script, `args.only: ["learn-tests","learn-roleplay"]`              | learn-tests, learn-roleplay → integrate                                                                               | Done, green — inline recovery (see §5)                                                                                                 |
| 3b    | `workflows/phase3b-lesson-steps-practice-support.js`                    | learn-steps-a, learn-steps-b, learn-practice-a, learn-practice-b, learn-support → integrate                           | **Partial** — steps A/B done; Practice-A complete; Practice-B functional via `GenericActivity`; support polish and visual sweep remain |
| 4     | (to write) visual-verify loop per screen + `composer ci:check`          |                                                                                                                       | Not started                                                                                                                            |

Rule of thumb for cost: one phase ≈ 1.5–3 M subagent tokens. Phase 1 used 1.64 M.

**Usage checkpoints (user's Claude plan meter, target: weekly < 60 %):**

| When                                             | Session (5h) | Weekly | Fable limit |
| ------------------------------------------------ | ------------ | ------ | ----------- |
| 2026-09-18 ~12:40, phase 1 done, phase 2 running | 36 %         | 32 %   | 15 %        |

## 1. What exists after phase 1 (verified)

- **Migrations** `database/migrations/2026_09_18_1000*.php`: users learner columns (username, consent, first login, activity, participant code…), departments focus/status, media_assets, audio_clips, courses, units, lessons, blocks, lexicon_items (+ block_lexicon_item), activities (+ activity_versions), activity_placements, ai_scenarios, block_completions, lesson_completions, tests, test_attempts, attempts, roleplay_attempts, phrasebook_items, voice_recordings, certificates, ai_usages, reminder_templates, automation_rules, reminders. All run on SQLite and MySQL.
- **Models/enums/factories/policies** for all of the above (`app/Models`, `app/Enums`, `database/factories`, `app/Policies`), Laravel 13 attribute style, PHPDoc typed.
- **Services:** `App\Services\Audio\AudioLibrary` (hash-keyed pre-generated audio, CTRL-05), `App\Services\Learning\ActivityScorer` (every activity type in spec B.9), `App\Services\Reminders\{ReminderService,TemplateRenderer,AutomationRunner}` (consent gate, REM-05), `App\Support\SimpleXlsxWriter` (no package), `App\Services\Ai\{FakeAiProvider,AnthropicAiProvider,UsageMeter}`, `App\Services\Tts\*`, `App\Services\Stt\*` behind `app/Contracts/*` interfaces, bound from `config/services.php` (`AI_PROVIDER` / `TTS_PROVIDER` / `STT_PROVIDER`, default `fake`).
- **Jobs** (`app/Jobs`): GenerateAudioClip, GenerateLexiconDraft, GenerateRoleplayReply, EvaluateRoleplayAttempt, EvaluateWrittenAnswer, SendReminderEmail, RunAutomationRules (scheduled daily in `routes/console.php`).
- **Seed images:** 58 crops of the client's employee mockups in `public/content/seed/` with `database/seeders/data/seed-media.json` (name → file, size, alt, source photo, crop box). They are stand-ins until the client uploads real photos through the CMS (MED-02).
- **Routes:** `routes/admin.php` now requires one file per screen under `routes/admin/`.
- **Harness:** `storage/harness/` (serve.sh, shoot.mjs, compare.py, README.md); approved before-shots of the sample-data admin pages in `storage/harness/before/`.
- **Tests:** 300 passing (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`), including `tests/Feature/FoundationSmokeTest.php` (one row of every model + the fake TTS pipeline end to end).

## 2. Decisions taken against open questions (flag to the client)

| Topic                           | Decision coded                                                                                                                                      | Where to change                                                   |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Login identifier (AUTH-01)      | The login form's `email` field accepts **username or email**; registration and email verification removed                                           | `FortifyServiceProvider::authenticateUsing`, `config/fortify.php` |
| Content hierarchy               | Course → Unit → Lesson → Block (the admin mockup shows units)                                                                                       | spec B.5                                                          |
| Lesson photos                   | Crops of the mockups as seed media, replaceable in the CMS                                                                                          | `public/content/seed`, `seed-media.json`                          |
| Audio without a TTS key         | `FakeTtsProvider` writes short WAV tones so every play button works locally; real provider = any OpenAI-compatible `/audio/speech` endpoint via env | `config/services.php`                                             |
| AI without a key                | `FakeAiProvider` scripts keyed by scenario slug (`check-in` reproduces the mockup conversation)                                                     | `app/Services/Ai/FakeAiProvider.php`                              |
| Speech input in role-play       | Text always; voice recorded and stored; transcription through an STT provider (fake returns "[voice message]")                                      | `config/services.php`                                             |
| Excel export                    | Hand-rolled XLSX (ZipArchive), no package                                                                                                           | `app/Support/SimpleXlsxWriter.php`                                |
| PDF report / certificate        | Print-styled HTML page + browser print; no PDF package (needs a client decision)                                                                    | spec Part D                                                       |
| Employee sidebar journey group  | Stable complete learner navigation is shown on every employee page so route changes do not add/remove a group unexpectedly                          | `EmployeeLayout`                                                  |
| Video example Arabic (photo_6)  | Hidden until Show Meaning (CTRL-02) although the mockup draws it revealed                                                                           | `VideoStep`                                                       |
| Ended contract                  | Hard block at login (spec 0002)                                                                                                                     | `Hotel::allowsAccess`                                             |
| Post-test attempts / pass score | Multi-attempt, percent                                                                                                                              | `TestPolicy::start`, `TestAttempt::scoreSummary`                  |
| Data deletion                   | Answer-holding tables restrict deletes (DATA-10) instead of cascading                                                                               | learner migrations                                                |

## 3. Open issues carried forward (from lane reports)

- `activities.scoring` json is stored/versioned but not interpreted (max_score = item count, no weights).
- `UsageMeter::assertWithinLimits` counts role-play turns only; call it before start/message, never before evaluation (phase 3a brief says so).
- `AutomationRule` has no channel column; channel derives from the trigger.
- Failure reasons of reminder mails land in `reminders.blocked_reason`.
- Thumbnails cropped from the mockups are at 1× size (soft on HiDPI); `video-poster-reception` still shows the mockup's play button; `home-receptionist` carries the handwritten quote inside the photo (do not render the script text again on top).
- AI Scenarios admin screen stays on sample data (not in the user's list).
- Admin Pre-test & Post-test builder screen not built (tests are seeded and readable only).

## 4. How to run and verify

```bash
# inside WSL, repo root
bash storage/harness/serve.sh reset            # SQLite + seed + php -S 127.0.0.1:8090 (Vite from the Sail container on :5173)
node storage/harness/shoot.mjs storage/harness/jobs/<job>.json
python3 storage/harness/compare.py <mockup.jpg> <shot.png> <out.png> --overlay
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
vendor/bin/pint --parallel --test && vendor/bin/phpstan analyse --memory-limit=1G
npm run check && npm run types:check           # never npm run build (public/build holds user files)
```

Logins (password `password`): `harness@guesvia.test` (Super Admin), `samira` (employee, pre-test not done), `amine` (employee, lesson 1 done). Employees land on `/learn`.

## 5. Phase log

### Phase 1 — foundation (done)

Reports: `phase1-foundation.js` run `wf_6b8fd21d-67c`. Integrator result: migrate:fresh (plain and --seed) clean on SQLite; pint 251 files pass; PHPStan 7 no errors; 300 tests / 1357 assertions green; seed-media.json 58/58 files present.

### Phase 2 — learner foundation + Hotels (running)

Lanes: auth-backend (username login, hotel-access + first-login middleware, shared props incl. `journey`, `routes/learn.php`, Learn controllers/services, media.show), learn-shell (LessonLayout, EmployeeLayout, shared learning components, Situation step + Home pixel-matched), content-seeder (spec Part G), admin-hotels (spec 0002 in full). Result to be appended here.

Lane results so far (13:45):

- **auth-backend — done, green.** 383 tests / 10 skipped, PHPStan 0, npm check + types clean. Username-or-email login (`FortifyServiceProvider::authenticateUsing`), registration + email verification removed (`Register.vue`, `VerifyEmail.vue`, `CreateNewUser.php` deleted; `Welcome.vue` / `settings/Profile.vue` lost their register / re-send links), `EnsureHotelAccess` + `EnsureFirstLoginCompleted` middleware (aliases `hotel.access`, `first-login`), `routes/learn.php` per spec Part E, twelve `app/Http/Controllers/Learn/*` controllers (Roleplay + Test are typed `abort(501)` stubs for phase 3a), `MediaController@show`, `app/Services/Learning/{JourneyService,ProgressService,LessonNavigator,BlockPresenter,ActivityPresenter,AttemptRecorder,PayloadResolver}`, shared props `auth.user.department_name/hotel_name`, `notifications.unread`, `journey`. Employees are redirected from `/dashboard` to `/learn`. Assumptions: post-test unlocks when every published lesson is complete (JourneyService), certificate type = Completion when the post-test pass mark is met (CertificateController) — both pending admin configuration (JOURNEY-04, CERT-04).
- **content-seeder — done, green.** `LearningContentSeeder` + `database/seeders/data/*.php`; 8 lessons, 9 blocks on lesson 1, 7 practice activities, 25 + 25 paired test questions, 6 scenarios (slug `check-in` drives the fake AI script), 58 seed media rows, 424 audio clips generated by the fake TTS, users `samira` / `amine` / `harness` (username `harness`), portfolio employees with usernames, participant codes, consent and activity spread, reminder rows. Mockup conflicts needing a ruling: photo_7 draws six practice cards and "0 / 6" while the mockup set contains seven activities (seeded seven); photo_19 says "8 words" while photo_2 shows six vocabulary items (seeded six); lesson counter "1 / 8" (photo_1) vs "1 / 6" (photos 2–14) — seeded eight lessons. No video file exists in the seed (poster only).
- **admin-hotels — done, green.** Spec 0002 AC-1…AC-20: `app/Http/Controllers/Admin/{HotelsController,Hotels/*}`, `app/Services/Hotels/*`, form requests, `routes/admin/hotels.php`, dialogs (create/edit, seats), row action menu, server-side filters/pager, audit rows; 113 admin tests. Visual diff vs the before-shot limited to the Add Hotel button, real numbers and the extra seeded pending row. Table Status/Actions columns still clip inside the panel's horizontal scroll at 1280/1240 exactly as the approved before-shot did (locked widths, not changed). `SeatQuotaService::assertSeatAvailable()` exists for the Employees lane to call.
- **learn-shell — done, green.** `layouts/{LessonLayout,EmployeeLayout}.vue`, `components/shell/{LanguageSelect,BottomNav,LessonTopNav,LessonFooter}.vue`, `AppSidebar` nav prop (admin default unchanged: dashboard diff 0.0), `components/learning/**` (StepTracker, LessonStepHeader, StepFooterNav, AudioButton, SpeedButtons, ShowMeaning*, PhrasebookButton, TipCard, TaskCard, SidePhotoCard, RecorderButton, RecordingPlayer, ProgressRing, ActivityDots, CheckButton, OptionRow, WaveformGlyph, steps/{SituationStep,GenericStep}, activities/GenericActivity), composables `useAudio/useShowMeaning/useRecorder/useTimer`, types `learning.ts/assessment.ts/roleplay.ts`, pages `employee/Home`, `employee/lesson/{Step,Activity}`, minimal `employee/{Lessons,Phrasebook,Progress,Messages,Certificate,FirstLogin}`. Situation step and Home pixel-matched (per-region diffs ≤ 40/255 only in photo areas; positions within 0–3 px). New tokens added from mockup samples: `ink-night`, `ink-graphite`, `ink-steel`, `ink-cobalt`, `ink-dusk`, `crimson`/`crimson-text`, `step-idle`, `font-quote` (serif italic for the quote box). Known: the mockup's display face is not Poppins/Inter (widths differ up to 15 %), and the seven middle tracker circles are hand-placed in the mockup (ours are evenly spaced, ≤ 26 px off).
- **phase2-integrate — green.** Pint 315 files, PHPStan 0, Wayfinder regenerated, npm check + vue-tsc clean, full suite 383 passed / 10 skipped / 0 failed (2451 assertions). Seeder now publishes the seed crops onto the `public` disk (`storage/app/public/content/seed`) so `MediaAsset::url()` resolves without a symlink. Harness on :8090 left running with the seeded DB.
- Phase 2 cost: 2.04 M subagent tokens, 5 agents, 107 min wall clock.

Screenshots: `storage/harness/shots/shell-*.png`, `hotels-*.png`, `int-*.png`; composites `cmp-shell-step.png`, `cmp-shell-home.png`, `cmp-hotels-1280.png`.

- Infrastructure note: the Sail container's Vite (`:5173`) died twice during this phase; restart with `docker exec -d -u sail ghasido-laravel.test-1 bash -c 'cd /var/www/html && npm run dev > storage/logs/vite-dev.log 2>&1'`. When `public/hot` is missing, the stale `public/build` manifest breaks full-page renders in feature tests; tests should use `withoutVite()`.

### Phase 3a — admin half (done, green)

The run `wf_972737ae-e87` crashed WSL mid-integration (see `HANDOFF-phase3a-addendum.md`). Recovered and finished inline on 2026-09-18:

- **Staged files landed.** All scratchpad files copied to their destinations (§C of the addendum): the Messages page + `MessagesLogDialog`, the Reports page + six report components, the whole Lessons CMS frontend (`types/lessons.ts`, `pages/admin/LessonsContent.vue`, `components/lessons/**` incl. `tabs/` and `blocks/`), and three lessons tests.
- **Type collisions fixed.** `types/lessons.ts` redefined `AudioPair` and `ActivityItem`, colliding with `types/learning.ts` / `types/assessment.ts` through the `@/types` barrel. Renamed the CMS versions to `LessonAudioPair` / `LessonActivityItem` (only `LessonsAudioChips.vue` imported them directly). `BlockEditorDialog` now casts the settings payload to `FormDataConvertible`.
- **Six missing block editors created.** `BlockEditorDialog` mapped `vocabulary/expressions→LexiconEditor`, `listen_repeat→ListenRepeatEditor`, `dialogue→DialogueEditor`, `video→VideoEditor`, `practice/email_activity/phone_activity→PracticeEditor`, `ai_roleplay→RoleplayEditor`, none of which were staged (the `*.vue` shim hid the missing modules from vue-tsc; they would have crashed the CMS in a browser). Built to the B.10 settings contract, following the `SituationEditor`/`GenericEditor`/`CompleteEditor` pattern. Known limitation: LexiconEditor and PracticeEditor edit the block settings and display their child rows (lexicon items / activities) with audio + Show Meaning state, but the deep per-item CRUD (AI draft generation per word, the activity builder) is not yet wired — the backend endpoints exist.
- **Block audio wired.** `LessonsAudioChips` imports `@/routes/audio`, which did not exist. Added `AudioController@generate` (queues `ensureBoth` per text) + `@replace` (an uploaded file replaces a clip, TTS-03) + `AudioLibrary::attachUpload()` + the `audio.generate` / `audio.replace` routes.
- **Activity payload bug fixed.** `StoreActivityRequest`/`UpdateActivityRequest::activityData()` used `validated()`, which — because of the `payload.items.*.id` wildcard rule — stripped every item key except `id`, losing the authored content (the `correct` answer, options…). Now the full submitted payload is stored verbatim (DATA-11, TEST-09); `ActivityVersionTest` proves versioning preserves item edits.
- **`withoutVite()`** added to `RoutePermissionTest` and `SharedAuthPropsTest` (§E.2); §E.1 method clashes were already fixed pre-crash.
- **Gates green:** `composer ci:check` clean — oxlint/format, vue-tsc, Pint (436 files), PHPStan level 7 (0 errors), 516 tests / 4752 assertions passed, 10 skipped, on SQLite in-memory.
- **Browser visual verification — done (follow-up pass).** Served natively (`npm run build` + `storage/harness/serve.sh reset` on :8090, no Sail) and drove a real browser as Super Admin. All eight admin screens render with real seeded data and **no horizontal overflow at 1280×853 or 390×844**; Lessons & Content and Reports & Export match their mockups region-by-region (differences are seeded data only); Messages & Reminders (broken before recovery) renders; the Dialogue and Vocabulary block editors open with seeded settings.
- **Build caught a real bug:** `MessagesTemplateDialog.vue` had `{{ \`{{${variable}}}\` }}`— nested mustaches crash the Vue SFC compiler (build **and** dev), invisible to vue-tsc/oxlint. Fixed by moving the brace-building into a`placeholderToken()` script helper.
- **Deep CMS item CRUD — lexicon half added + browser-verified.** New `blocks/LexiconItemDialog.vue` (add/edit every field, image slot, **Generate with AI → draft → Apply draft**, Generate audio), wired into `LexiconEditor` (Add word/expression + per-item Edit). The AI-draft flow works end to end against the fake provider. **Remaining:** the PracticeEditor activity builder (the 7 activity-type payload editors) is a larger separate build.
- **Env note (WSL 9p):** `\\wsl.localhost` in-place edits don't always flush to the native ext4 the build reads (same chunk hash, new code absent). `create_file` syncs; to modify, write `X.new.vue` then native `mv -f` over the target and verify with `grep` before building.

### Phase 3a′ — learner Tests + AI Role-play (done, green)

Built inline on 2026-09-18. `Learn/Test*` and `Learn/Roleplay*` were `abort(501)` stubs.

- **Pre-test / Post-test runner.** `TestController` (start/question/answer/finish/result) + `TestRunner` (server-timed `startOrResume`, `saveAnswer` — one `attempts` row per question with `answer_history`, `isExpired`) + `TestScorer` (scores via `ActivityScorer`, breakdown by skill_label, sets `training_started_at`). Pages `employee/test/{Question,Result}.vue`: EmployeeLayout, per-question progress, `GenericActivity` keyed `q-${number}` (fixes an Inertia answer-leak — every seeded test item shares id `i1`), Prev/Next/Finish saving via `router.put`/`router.post`, `TimeRemaining` (useTimer off the server deadline) + 5×5 navigator. Result is visibility-gated (hidden → "answers saved" / score → % / score_breakdown → per-skill bars). Arabic + `correct` are stripped from the payload by `ActivityPresenter::present(_, _, 'test')` — never shipped (CTRL-04, TEST-03). Satisfies TEST-04/05/06, TIME-04/05, DATA-01/08/11, JOURNEY-01 (finishing the pre-test opens the lessons gate).
- **AI Role-play.** `RoleplayController` (ready/start/show/message/end/feedback) + `RoleplayService` (attempt cap from `scenario.attempts_allowed`; `UsageMeter::assertWithinLimits` before start/message → `AiLimitReached` caught → flash error; `appendTurn`; dispatches `GenerateRoleplayReply` / `EvaluateRoleplayAttempt`). Pages `employee/roleplay/{Ready,Chat,Feedback}.vue`: brief + attempt dots, guest/employee bubbles with a pending "…" bubble, textarea Send / End, poll via `router.reload({ only: ['attempt'] })` while `pending_reply`; Feedback polls while `evaluating`, then overall score + 5 criteria bars + did-well / improve / better-expression. Full transcript captured every attempt, previews excluded (RP-05/06/11/13, AIE-04, AIL-03, PERF-04, DATA-05). Entry point is the existing `GenericStep.vue` scenario cards (no picker page needed).
- **Reused infra:** `ActivityPresenter`, `ActivityScorer`, `JourneyService`, `UsageMeter`, the reply/eval jobs + `FakeAiProvider` (scripted by scenario slug `check-in`), `RoleplayAttempt::appendTurn`, `useTimer` / `useRecorder` / `useAudio`. Routes already existed in `routes/learn.php`.
- **13 new feature tests** (`Learn/Tests/TestRunnerTest` ×6, `Learn/Roleplay/RoleplayFlowTest` ×7): server deadline, no arabic/correct in payload, one answer row per question + history, finish scores + opens the gate, cross-user 403, hidden visibility; roleplay start/queue, turn append, evaluation stores scores with the transcript intact, preview never counts, attempt cap, cross-user 403, AI daily-limit flash. The limit test drives the real `UsageMeter` via `config()->set('guesvia.ai.limits.per_employee_daily_turns', 0)` — the class is `final`, so it cannot be `$this->mock()`ed.
- **Browser-verified** (native serve on :8090, no Sail): samira takes the 25-question pre-test (timer, navigator, save-on-nav, finish → Result 1/23 = 4%, lessons unlock); samira runs the Check-in role-play (Ready → fake-AI guest opening "Hello! I have a reservation for tonight." → 7-turn chat → Feedback 70/100 with 5 criteria bars) — Ready/Feedback match photos 16/18.
- **Env note (WSL 9p):** the same in-place-edit flush bug bit again — the FULL `phpstan analyse` (not per-file) caught two errors that per-file runs cached over (`RoleplayService` Carbon `!== null`, `TestRunner` nullsafe-`??`), and a stale draft of `RoleplayFlowTest` (broken `+3-3` assertion, illegal `mock(final UsageMeter)`) had survived a create_file write. Fixed by python scripts that read the exact native content, `assert old in text`, replace, then verify with `sed`/`grep` before trusting.
- **Gates green:** `composer ci:check` clean — oxlint/format (299 files), vue-tsc, Pint (444 files), PHPStan level 7 (0 errors), **529 tests / 4821 assertions passed**, 10 skipped, SQLite in-memory.

### Phase 3b — lesson steps (lanes A + B done, green)

Built inline 2026-09-19, browser-verified on the :8090 harness at 1280×853 and 390×844.

- **Lane A — Vocabulary, Useful Expressions, Listen & Repeat** (photo_2/3/4): `components/learning/steps/{VocabularyStep,ExpressionsStep,ListenRepeatStep}.vue` + `components/learning/vocab/{VocabFeaturedCard,VocabExampleCard,RelatedWordsList,ExpressionFeaturedCard,ExpressionsList}.vue`. The featured item swaps client-side from the side list; Show Meaning reveals Arabic inline (CTRL-02); the Listen & Repeat recorder uploads to the block (`learn.recordings.store`, DATA-02). No presenter change — `BlockPresenter::lexicon()` already shipped every field.
- **Lane B — Dialogue, Video, Lesson Completed** (photo_5/6/19) + `video/VideoPlayer.vue` (custom control bar; Normal/Slower drive playbackRate). The dispatcher `pages/employee/lesson/Step.vue` gained `v-else-if` branches for vocabulary/expressions/listen_repeat/dialogue/video/complete; the complete step suppresses the shared `LessonStepHeader`/`StepFooterNav`, renders its own trophy header and marks the lesson done on mount (LESSON-05). `BlockPresenter::summary()` extended with per-block `rows` (Key Vocabulary "n words", …, AI Role-play "u of a attempts"); new TS `CompleteRow`. My Lessons was already built (`Lessons.vue` → `CourseOutlineCard`).
- **Tests:** `tests/Feature/Learn/Steps/{Vocabulary,ListenRepeat,Dialogue,Complete}StepTest.php` (7 methods). `npm run check`/`types:check` clean (257 files), Pint + PHPStan L7 clean, `tests/Feature/Learn` 70 passed. Not committed.
- **Deviations (§0.2 rule 8):** vocab kept as single-file steps (not the ListenPanel/RepeatPanel split); Listen & Repeat mic 96px (mockup 112); Complete "Scenario" row uses the lesson title (mockup "Check-in (Guest arrival and registration)" has no data source); Video shows 0:00/0:00 (seed ships posters, no video files); Video example Arabic ships hidden behind Show Meaning (mockup draws it revealed) per CTRL-02.
- **Remaining phase 3b:** support pages (Phrasebook/Progress/Messages/first-login polish, bottom nav). Practice-A and the functional Practice-B runner were completed in the current checkpoint below; the practice-hub mini previews still need the final visual sweep.

### Phase 3b (practice + support) / 4

Practice-A is complete and Practice-B is functional through `GenericActivity`.
Support-page polish and phase 4 visual verification remain. Run the scripts in
order; each integrator leaves the harness on :8090.

### Current checkpoint — 2026-09-20

The previous interrupted-session summary is the current source for the practice
lane: Practice-A is complete (Practice hub, Listen & Choose, Look & Listen,
Best Response, and Listen & Match); Practice-B is functional through
`GenericActivity`. The next project-level step remains the phase-4 visual
sweep and final gate. Do not restart the completed activity work.

This turn repaired Lessons & Content (CMS-01, CMS-02, CMS-04, MED-01/02):

- `LessonsDirectoryTable.vue` now appears first and lists every reachable
  lesson with department, hotel scope (`All hotels` for shared content), and
  draft/published status; each row opens the correct editor selection.
- The directory data is built tenant-safely in `ContentTree`; the new
  `ContentTreeTest` assertions cover the lesson/department/hotel mapping.
- Uploads now switch the image library to `My Images` after success, so a new
  photo is immediately visible and pickable. Public media URLs also fall back
  to the seed asset or authorized media response when the storage symlink is
  missing, fixing broken cover/library images.
- Browser verification passed at 1280×853, 1240×698, and 390×844 with no
  horizontal overflow or console errors. The harness remains on :8090.
- This repair's focused gates: 62 SQLite feature tests / 557 assertions,
  `npm run check`, `npm run types:check`, Pint, PHPStan (target files), and
  the production build all pass.

This turn also repaired the learner navigation and enabled the hotel manager lane
(ROLE-01/02, SUB-02, PROG-02, REM-07, REP-01):

- `EmployeeLayout.vue` now keeps the full learner sidebar stable on My Progress
  and every other learner page; it no longer conditionally adds the journey
  group only after leaving Home.
- `DashboardController` now renders the real `Dashboard` for a manager whose
  account is attached to a hotel, passing `DashboardStats::build($hotel)` so
  employee counts, progress, needs-attention rows, and recent activity cannot
  cross the hotel boundary. A manager without a hotel remains on a safe
  placeholder. The dashboard hides the platform-only TTS editor from managers.
- Added local/testing-only `HotelManagerSeeder`: `manager@guesvia.test` (or
  username `manager`), password `password`, attached to La Gazelle d'Or. It is
  called after the local hotel portfolio and never runs in production.
- The existing manager employee directory and Messages & Reminders screens are
  now reachable from a working manager dashboard: managers can add/update/
  activate/deactivate employees within department seat quotas, inspect their
  progress/activity, and send consent-aware reminders/messages. Server-side
  policies still deny other hotels, templates/rules, transcripts, and progress
  resets.
- Verification: 80 focused SQLite tests / 1,060 assertions passed, `npm run
check`, `npm run types:check`, and `git diff --check` pass. Browser visual
  verification of the manager surface remains part of the phase-4 sweep.

## 6. Orchestrator checklist (resume here after a context compaction)

1. Phase 3a admin half is **done and green** (recovered from the WSL crash, see §5). Still owed there: the browser visual pass for the admin screens (screenshots at the three sizes vs the mockups, per spec Part I / AGENTS.md §0.3) — the harness needs the Sail Vite (:5173) up.
2. Ask the user for the usage meter; record it in §0; project the remaining cost (each run so far ≈ 1.6–2.1 M agent tokens).
3. Launch phase 3a′: the same script `workflows/phase3a-admin-tests-roleplay.js` with `args: {"only": ["learn-tests","learn-roleplay"]}` (pass the script inline; the tool refuses scratchpad paths). Fold any new open issues from 3a into the briefs first.
4. Launch phase 3b: `workflows/phase3b-lesson-steps-practice-support.js` (five lanes). Fold the practice-hub ruling (seven activities kept) and the `blossom` token note into the briefs if not already there.
5. Write and run phase 4: one lane per screen group that shoots every employee screen (28) and admin page (7) at 1280×853 / 1240×698 / 390×844, compares with `compare.py`, fixes, re-shoots; then one lane running `composer ci:check` end to end. Then final §5 entry + update `docs/scope/scope.md` pointers.
6. Stop at any phase boundary if the user says the meter nears 60 %: leave the tree green, update this file, do not commit unless asked.

## 7. If you are continuing this work without the Workflow tool

Take the lane briefs from the phase script (each `agent(\`…\`)` block is one task) and execute them one at a time in the order: 3a admin lanes, 3a learn-tests, 3a learn-roleplay, then 3b lanes, then the visual verification per screen (spec Part I). Every brief ends with the gate commands and the mockup to compare against.
