# 0008 — Role-play picker, test questions, hotel departments, individuals, PWA, Show Meaning everywhere

_Requested by the platform owner on 2026-09-25. Requirement IDs: RP-01, RP-05, RP-13, TEST-05, SUB-01, SUB-05, ORG-02, ORG-03, AIL-01, AIL-03, CTRL-01..04, I18N-01, I18N-03, RESP-06, DATA-10, SEC-06._

Six requests in one batch. Each section says what was wrong or missing, what changed, and where.

## 1. Assigning an existing AI role-play to a lesson

**Why it failed.** The lesson's **AI Role-play** tab only listed scenarios; picking one meant finding the AI Role-play _block_ in Lesson Content. AI-generated lessons have no such block at all. On top of that:

- the dedicated builder URL (`/lessons/{id}/edit`) has no query string, so the first tab click sent the next save back to `/lessons-content?tab=…`, which closed the builder;
- the admin preview filtered scenarios with the learner scope (the admin has no learner department), so assigned scenarios never showed;
- a rejected save (`scenario_ids`) showed no message.

**What changed.**

- The AI Role-play tab is a picker: every scenario of the lesson's department, tap to add or remove. It saves at once through `PUT lessons/{lesson}/scenarios` (`BlockController::scenarios`, `AssignLessonScenariosRequest`, `BlockService::assignScenarios`). A lesson without a role-play step gets one, placed before the closing step. The department and hotel scope check stays in `BlockService::syncScenarios`.
- The scenario list follows the open lesson's department, not the URL (`ContentTree::scenarios`).
- `lessonsQuery.ts` seeds the query from the page's filters on `/lessons/{id}/edit`, so tabs and saves keep the lesson open.
- The admin preview shows every linked scenario, drafts included, linking to the scenario's own page for testing (`BlockPresenter::present(..., preview: true)`). Learners still see published ones only.
- The block editor shows any rejected field, not only the title.

Tests: `tests/Feature/Admin/Lessons/LessonScenariosTest.php`.

## 2. "Add Question" in the Pre-test / Post-test builder

**Why it looked broken.** The add _worked_: seven questions were created on the live Reception tests between 13:53 and 13:56 on 2026-09-25. Each landed at the end of a long list, off-screen, with placeholder text, and:

- True / False was stored and shown back as Multiple Choice, with "Option A / Option B";
- the chosen question type snapped back to Multiple Choice after every add and every poll;
- validation errors (e.g. an empty "Option C") were never shown.

**What changed.**

- After Add or Duplicate, the new card scrolls into view, is outlined for two seconds, and its text is selected for typing (`TestsEditorPanel.vue`).
- True / False sends True / False answers. It is stored as a two-option multiple choice labelled `True / False`, and read back as `true_false` (`TestService::kindFor(type, skillLabel)`). Changing a card's kind to True / False replaces its answers.
- The chosen kind is no longer reset by a reload.
- Failed writes raise an error toast naming the field in plain words ("The answer text field is required…").

**Clean-up on live.** The seven placeholder questions ("Write the question prompt here.") are still in the live Reception tests. Delete them in the builder, or edit them into real questions.

Tests: `tests/Feature/Admin/Tests/TestQuestionKindsTest.php`.

## 3. Individual subscribers (no hotel)

A learner who uses the app, and its AI, on their own.

| Decision                                                                                                                                                                                                                                                                                                     | Why                                                                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| The learner is an ordinary `employee` user with `hotel_id = NULL` and a **shared-catalogue** department.                                                                                                                                                                                                     | Lessons, tests, phrasebook, certificates and AI practice all work unchanged. Content is the department's shared catalogue (`hotel_id IS NULL`). |
| Their configuration lives in `individual_subscriptions` (one row per user): access window, AI on/off, voice on/off, daily AI turn cap, points per AI action, points per 10 min of voice, price paid, payment reference, notes. The monthly AI points are `users.ai_points_allocated`, as for every employee. | It replaces what a hotel's contract and plan decide for its employees.                                                                          |
| Enforcement: `EnsureHotelAccess::blockedMessageFor` blocks sign-in outside the window; `UsageMeter` uses the individual's costs and cap, enforces their points, and refuses AI / voice when switched off (`AiLimitReached::forIndividualPlan`). Out-of-points messages say "Contact GHASIDO support".        | The same two places that enforce hotels. Data is kept when access ends (DATA-10).                                                               |
| Managed on **Individuals** (`/individuals`, sidebar item under Subscriptions, and an "Individuals" link on the Subscriptions tab bar), behind `subscriptions.manage`.                                                                                                                                        | Same capability as Subscriptions; no permission migration needed.                                                                               |

**One department or several** (follow-up, same day).

- The dialog lists the shared-catalogue departments as a checklist. The first one ticked is the learner's **main** department (`users.department_id`, what reports show), and the full list is in `individual_subscription_department` (migration `100008`, which also back-fills earlier subscribers).
- With more than one, the learner gets the same department switcher a manager has, above every learner page. They move between exactly their subscription's departments (`TrainingDepartments::availableFor/switches`, `ResolveTrainingDepartment`, `TrainingDepartmentController`). With one, there is no switcher.
- `User::learningDepartmentId()` now prefers the per-request choice over `department_id`. Only the middleware sets it, for a manager or a switching individual.
- The shared `journey` and `trainingContext` props are closures, resolved after the route middleware. Before, they were computed too early, so a manager's switcher never showed their current department.

Files: migration `2026_09_25_100006_create_individual_subscriptions_table`, `IndividualSubscription` model and factory, `IndividualSubscriptionService`, `SaveIndividualRequest`, `IndividualsController`, `routes/admin/subscriptions.php`, `pages/admin/Individuals.vue`, `components/individuals/*`, `types/individuals.ts`.

Tests: `tests/Feature/Admin/IndividualsTest.php`.

Not included (ask if wanted): a self-service sign-up and payment page for individuals, and AI point top-ups for individuals. Raising `ai_points_allocated` in the dialog does that job for now.

## 4. Departments of a hotel

A hotel "has" a department when it holds a `seat_quotas` row for it. Before this, Manage seats could only change numbers, never remove a department, and could not add one with 0 seats.

- New hotel row action **Departments** (`HotelDepartmentsDialog.vue`). It lists the hotel's departments with seats used, adds one from the catalogue (shared or the hotel's own) or creates a new one for the hotel (needs `departments.manage`), and removes one.
- `POST hotels/{hotel}/departments`, `DELETE hotels/{hotel}/departments/{department}` (`HotelDepartmentsController`, `AddHotelDepartmentRequest`, `HotelDepartmentService`). Authorized by `manageSeats` on the hotel; one audit row each (`hotel.department_added` / `hotel.department_removed`).
- Removing is refused while the department still has **active** employees at the hotel. Inactive employees keep their department (DATA-10).

Tests: `tests/Feature/Admin/Hotels/HotelDepartmentsTest.php`.

## 5. Installable app (PWA) and "Download the app"

- `public/manifest.webmanifest`: name GHASIDO, `start_url` `/login` (the installed app opens on sign-in; a signed-in user is sent on to the dashboard), standalone, white theme.
- Icons: `public/pwa/icon-{192,512}.png` and `maskable-{192,512}.png`, cropped from the client's palm mark in `desgin/assets/guesvia-logo-original.png` (the favicon's artwork).
- `public/sw.js`: pages and API responses are never cached (private, always current); only hashed `/build/assets/*` and the brand images are. With no connection, `public/offline.html` is shown.
- `public/.htaccess`: manifest MIME type, `no-cache` for `sw.js`.
- `resources/js/composables/usePwaInstall.ts`: registers the service worker and keeps the browser's install prompt. `components/landing/InstallAppButton.vue`: **Download the app** in the landing hero, the phone menu and the closing call-to-action. It uses the browser's install dialog where there is one (Chrome, Edge, Samsung Internet), then opens sign-in. Otherwise (Safari, Firefox, already installed) it shows the steps (iPhone: Share → Add to Home Screen) and a "Continue to sign in" button.

RESP-06 was marked _(Suggested)_; the owner asked for it on 2026-09-25.

**Also in the landing navbar** (follow-up request, same day): at every width.

- Below 1280px it is a 44px brand-tinted download icon next to "Get started". The logo shrinks to 36–40px on phones, and the "Get started" arrow hides below 640px, so the row fits at 360px.
- From 1280px it shows "Download app" with its label.

## 7. Closing the sidebar (follow-up, same day)

- On a desktop ≥1280px, the topbar's sidebar toggle was hidden while the sidebar was open, so it could not be closed. The toggle now shows at every width, with the approved panel-close / panel-open icons and a "Close sidebar" / "Open sidebar" tooltip (`shell/AppTopbar.vue`).
- On a phone, the sidebar sheet now has a visible **×** close button in its header (`AppSidebar.vue`). Tapping outside still closes it too.
- Both are changes to the locked chrome, made at the owner's explicit request.

## 6. Show Meaning on all lesson text

> **Superseded 2026-09-26.** When this work was merged with PR #1, the owner kept the PR's Show Meaning instead: every text's Arabic is stored in `text_translations`, drafted by AI when the content is saved and editable on the **Translations** page (`/translations`). The per-field editors, `lessons.meanings`, block `settings.meanings` and `LessonMeaningsTest` below were removed. The migration `100007` and the components `LessonsMeaningEditor.vue` / `learning/MeaningText.vue` stay but are unused. Meanings already typed into the old fields are not shown until copied into `text_translations`.

Before: only vocabulary and expression items had the full Show Meaning (Arabic, explanation, example). A few blocks had an Arabic line, and the lesson's own text had nothing.

- **Where it is authored.** Every text field of the builder has an **Add Show Meaning** control (Arabic + optional simple explanation) under it. That covers:
    - the lesson title, introduction and each objective;
    - the step title;
    - the subtitle and tip of every block;
    - the Situation quote and objective rows;
    - the Complete subtitle, quote, closing quote and encouragement;
    - the video controls note and example note.
- **Components.** `LessonsMeaningEditor.vue`, used by `LessonsField` through `v-model:meaning` and directly in the lesson editor and Situation editor.
- **Storage.** Lesson: new `lessons.meanings` json (migration `2026_09_25_100007`). Blocks: `settings.meanings`. Both are keyed by field, with `:` for nesting (`objectives:0`, `example:note`). An emptied meaning is dropped (`UpdateLessonRequest::cleanMeanings`).
- **Learner side.** `components/learning/MeaningText.vue` wraps a piece of text. It adds the approved Show Meaning button (or a small eye chip in tight rows) only when a meaning exists, so the layout is unchanged otherwise. The panel shows the Arabic in Cairo, RTL, then the explanation. `BlockPresenter` sends `meanings` on the lesson and on each block.
- **Not covered.** Fields the learner page never shows (listen/repeat labels, attempts note, photo caption, side titles, motto) have no meaning editor. Tests never carry lesson text, so CTRL-04 holds.

Tests: `tests/Feature/Admin/Lessons/LessonMeaningsTest.php`.

## Deploy notes

Two migrations (`100006`, `100007`). No new packages, no config changes. After deploying, the service worker is picked up on the next visit.
