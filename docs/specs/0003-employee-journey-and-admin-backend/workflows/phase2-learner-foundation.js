export const meta = {
    name: 'spec3-phase2',
    description:
        'Spec 0003 phase 2: learner auth+routes, learner shell, content seeder, hotels admin (spec 0002)',
    phases: [
        {
            title: 'Build',
            detail: 'auth-backend → learn-shell pipeline, content-seeder and admin-hotels in parallel',
        },
        {
            title: 'Integrate',
            detail: 'gates across the tree, seeded harness boot, cross-lane fixes',
        },
    ],
};

const ENV = `
## Environment (read before running anything)
- Windows host; the repo lives in WSL Ubuntu-24.04 at ~/clickDz/ghasido and is reachable from your file tools as \\\\wsl.localhost\\Ubuntu-24.04\\home\\houdaifayahia\\clickDz\\ghasido (your working directory). Read/Write/Edit/Glob/Grep work on that path. For ANY multi-line file content use the Write tool (heredocs through Bash fail on this UNC path).
- PHP 8.3, composer, node 22, npm, python3+Pillow exist ONLY inside WSL. Run them through the PowerShell tool exactly like this: wsl.exe -d Ubuntu-24.04 -- bash -lc 'cd ~/clickDz/ghasido && <command>'. PowerShell expands $HOME and $( ) inside double quotes, so for anything with $, nested quotes or pipes write a script to storage/harness/<lane>-<n>.sh with the Write tool and run: wsl.exe -d Ubuntu-24.04 -- bash -lc "cd ~/clickDz/ghasido && sed -i 's/\\r$//' storage/harness/<file>.sh && bash storage/harness/<file>.sh"
- Tests: DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=<TestClass> (run the FULL suite only once, at the end). PHP gates: vendor/bin/pint --parallel <your paths> then vendor/bin/phpstan analyse --no-progress --memory-limit=1G (level 7, must be clean). JS gates: npm run check:fix (once, before your final gate), npm run check, npm run types:check. NEVER run npm run build.
- Wayfinder: after changing any routes/*.php run php artisan wayfinder:generate --with-form (other lanes regenerate too; never hand-edit resources/js/routes/**, resources/js/actions/**, resources/js/wayfinder/**). Never edit resources/js/components/ui/*. Do not git commit.
- Visual verification harness (see storage/harness/README.md): use YOUR OWN port and database so lanes do not collide: HARNESS_PORT=<your port> HARNESS_DB=$HOME/.guesvia-harness/<lane>.sqlite bash storage/harness/serve.sh reset (runs migrate:fresh --seed then starts php -S). Screenshot with node storage/harness/shoot.mjs <jobs.json> (job file "base" must be http://127.0.0.1:<your port>). Compare with python3 storage/harness/compare.py <mockup> <shot> <out.png> --overlay and LOOK at the output with the Read tool. Assets are served by the Vite already running on :5173 (public/hot); if a page renders unstyled, curl http://localhost:5173/@vite/client and report. Stop your server when done: HARNESS_PORT=<port> bash storage/harness/serve.sh stop.
- Ownership: touch only the files your brief lists as yours; other lanes are editing the rest of the tree right now. If you must change a shared file, make the smallest possible Edit and say so in your report.
- The contract is docs/specs/0003-employee-journey-and-admin-backend/index.md. Read it in full first. AGENTS.md applies (design fidelity §0, tokens only, locked chrome, Laravel 13 model style, class-based PHPUnit, Wayfinder, Inertia <Form>, no literal URLs, no new npm/composer packages).
- Your final message is your report: files, decisions, deviations from the spec/mockup with reasons, gate results, open issues.
`;

const REPORT = {
    type: 'object',
    properties: {
        files: { type: 'array', items: { type: 'string' } },
        decisions: { type: 'array', items: { type: 'string' } },
        deviations: {
            type: 'array',
            items: { type: 'string' },
            description:
                'every remaining difference from the mockup/spec and why',
        },
        open_issues: { type: 'array', items: { type: 'string' } },
        gates: { type: 'string' },
    },
    required: ['files', 'decisions', 'deviations', 'open_issues', 'gates'],
};

phase('Build');

const authBackend = () =>
    agent(
        `${ENV}
# Lane: auth-backend (harness port 8098)
Build spec Part B.1 (auth), Part E (learner routes, middleware, shared props) and the learner controllers/services that every employee screen shares.
You own: config/fortify.php, app/Providers/FortifyServiceProvider.php, app/Actions/Fortify/CreateNewUser.php (delete), resources/js/pages/auth/Register.vue (delete), resources/js/pages/auth/Login.vue (copy only: label "Username or email", placeholder "username", remove the sign-up paragraph and the register import), app/Http/Middleware/{EnsureHotelAccess,EnsureFirstLoginCompleted}.php, bootstrap/app.php (aliases hotel.access + first-login only), app/Http/Middleware/HandleInertiaRequests.php, app/Http/Controllers/DashboardController.php (add: employees redirect to route learn.home; leave the admin body), routes/web.php (require learn.php + the media.show route), routes/learn.php, app/Http/Controllers/MediaController.php, app/Http/Controllers/Learn/{HomeController,FirstLoginController,LessonsController,LessonStepController,ActivityController,RoleplayController,TestController,PhrasebookController,ProgressController,MessagesController,RecordingController,CertificateController}.php, app/Http/Requests/Learn/*, app/Services/Learning/{JourneyService,ProgressService,LessonNavigator,BlockPresenter,ActivityPresenter,AttemptRecorder}.php, app/Policies/LessonPolicy.php (only if the content lane's version lacks the pre-test gate; otherwise leave), tests/Feature/Learn/{LoginByUsernameTest,HotelAccessGateTest,FirstLoginGateTest,PreTestGateTest,LessonStepFlowTest,PhrasebookTest,RecordingUploadTest,MediaAccessTest}.php.
Requirements:
- Login: Fortify::authenticateUsing resolves the posted 'email' field against users.username (lowercased) OR users.email, checks Hash::check, refuses status inactive and hotels that do not allowsAccess() by throwing ValidationException::withMessages(['email' => <message>]) using the hotel's access_state->blockedMessage() / the ended message. Existing tests in tests/Feature/Auth post email + password: keep them green. Remove Features::registration() and Features::emailVerification() from config/fortify.php; keep resetPasswords, twoFactor, passkeys. Update tests that self-skip accordingly (RegistrationTest, EmailVerificationTest and VerificationNotificationTest already skip when the feature is off; verify).
- Middleware: EnsureHotelAccess (for authenticated employees/managers with a hotel: if !hotel->allowsAccess() or user status inactive → logout, invalidate session, redirect to login with the message as an 'email' error). EnsureFirstLoginCompleted: employees without first_login_completed_at are redirected to learn.first-login.edit (skip when already there).
- routes/learn.php exactly per spec Part E (names, verbs, paths). Group middleware: auth, role:employee, hotel.access, first-login (the first-login pair outside the first-login middleware). media.show lives in web.php under auth only (any role) and is authorized by MediaAssetPolicy (Storage::disk('local')->response / public redirect).
- HandleInertiaRequests: add auth.user.department_name, auth.user.hotel_name (null for admins), notifications.unread = Reminder in_app unread count for the user (0 when none), and journey (spec Part E shape) for users with the employee role, computed by JourneyService (preTestSubmitted, lessonsCompleted/lessonsTotal from Lesson::forLearner + lesson_completions, postTestUnlocked = all lessons completed (config-driven later), certificateAvailable = post-test submitted, continueUrl = LessonNavigator::continueUrl(user) — the first uncompleted visible block of the first uncompleted lesson, or null). Update resources/js/types/auth.ts and global.d.ts (add journey type as LearnerJourney in types/auth.ts) — tiny edits.
- Controllers: HomeController@index renders 'employee/Home' with {test: pre-test intro payload from Test::forLearner type pre (intro json + id + time limit + question count), attemptInProgress: {id, url} | null, journey (also shared)}. LessonsController@index renders 'employee/Lessons' with courses → units → lessons (title, position, stepCount, completed bool, locked bool, url). LessonStepController@show(Lesson, Block): authorize LessonPolicy view (403 when pre-test not submitted); renders 'employee/lesson/Step' with {lesson: {id, title, introduction, objectives, cover, positionInCourse, courseLessonCount, department: {name}}, steps: [{number, label, type, url, done, current}], block: BlockPresenter::present($block, $user) (generic: type, title, heading (from BlockType), settings with media ids resolved to urls under an 'media' map and audio urls via AudioLibrary::urlsFor for every string field named text/audio_text/example.text etc. — implement a recursive walker: keys named image|video|poster|cover|thumbnail → {id,url,alt}; keys ending in _text or named text (when inside items/lines) → also emit '<key>_audio': {normal, slow}), prevUrl, nextUrl, completeUrl}. LessonStepController@complete: AttemptRecorder-free path: creates block_completions (firstOrCreate), lesson_completions when every visible block is done, updates last_activity_at/training_started_at/training_completed_at, redirects to the next step (or the complete step) — respond to Inertia with a redirect. ActivityController@show(Lesson, Block, ActivityPlacement) renders 'employee/lesson/Activity' with {activity: ActivityPresenter (type, title, prompt, skill_label, items resolved, attemptsLeft), lesson, steps, block, backUrl, answerUrl, result: null}; @answer validates {answers: array} (see spec B.9 answer shapes), scores through ActivityScorer, writes an attempts row (activity_version_id = current version, lesson_id, block_id, placement_id, attempt_no incremented, raw_answer, is_correct, score, max_score, started_at from the request's started_at hidden field or now, time_taken_ms) and re-renders the page with result {perItem, score, maxScore, isCorrect, correct answers revealed}. Speaking/writing answers accept recording_media_id/text and set is_correct null (writing dispatches EvaluateWrittenAnswer). ActivityPresenter strips correct/order/pairs when mode = test. RoleplayController and TestController: create the classes with every method the routes need, typed signatures, each body abort(501, 'Built by the roleplay/tests lane') — those lanes replace them. PhrasebookController@store/destroy per spec (lexicon_item_id OR text+arabic; returns back with flash or JSON when request wantsJson). RecordingController@store: validates audio (webm|wav|mp3|m4a|ogg max 20 MB), stores on local disk under recordings/{user}/{uuid}.{ext}, creates media_assets (kind audio, library recordings, uploaded_by) and voice_recordings, returns JSON {id, url, duration_ms}. ProgressController/MessagesController/CertificateController: render 'employee/Progress' {courses with percent}, 'employee/Messages' {reminders in_app for the user}, 'employee/Certificate' {certificate or eligibility}; MessagesController@read marks read_at. FirstLoginController@edit/update per spec (require email when hotel settings.require_email, consent checkbox → email_consent_at, notice acknowledgement → research_notice_acknowledged_at, first_login_completed_at).
- Also add on User: progressPercent(): int (completed lessons over the total published lessons of the learner's department courses, ×100 rounded, 0 when none) and postTestUnlocked(): bool (delegating to JourneyService). TemplateRenderer and AutomationRunner already call them through method_exists; once they exist, turn the self-skipping posttest_available test in tests/Feature/Reminders/AutomationRunnerTest.php into a real assertion (small Edit, say so). MediaAsset::url() calls route('media.show') for private assets, so that route must exist before any page shaper runs.
- Wayfinder regen after routes. Write the tests listed (feature level: username login works, blocked hotel login refused with message and rows preserved, first-login redirect, lesson step 403 before pre-test and 200 after, completing steps writes completions in order and lesson completion at the end, phrasebook toggle, recording upload stores on the private disk and media.show refuses another user with 403).
- Vue pages are NOT yours except the Login copy; the learn-shell lane builds the pages right after you. Keep prop names exactly as described so it can type them.
- Gates on your files. Report precisely the prop shapes you return (JSON examples) so the learn-shell lane can type them.`,
        {
            label: 'auth-backend',
            phase: 'Build',
            schema: REPORT,
            effort: 'high',
        },
    );

const learnShell = (auth) =>
    agent(
        `${ENV}
# Lane: learn-shell (harness port 8098)
The auth-backend lane just finished. Its report:
${JSON.stringify(auth, null, 2)}

Build spec Part H.1 (layouts), H.2 (shared learning components + composables), the page dispatchers, and two reference screens pixel-matched to their mockups: the Situation step (desginphotos/employ/photo_1_2026-09-18_11-20-54.jpg) and Home (photo_20_2026-09-18_11-20-55.jpg).
You own: resources/js/layouts/{LessonLayout,EmployeeLayout}.vue, resources/js/app.ts (layout switch cases per H.1), resources/js/components/shell/{LanguageSelect,BottomNav,LessonTopNav,LessonFooter}.vue, resources/js/components/shell/AppTopbar.vue (extract the language box into LanguageSelect and use it; role line = auth.user.department_name for employees, 'Hotel Manager' for managers, 'System Administrator' otherwise — no other change to the locked geometry), resources/js/components/AppSidebar.vue (add an optional nav prop: {main, journey?, account} of SidebarNavItem[] with the admin lists as the default so the admin chrome is byte-identical; EmployeeLayout passes the employee lists; journey group rendered with the same divider treatment), resources/js/components/learning/** (every component in H.2), resources/js/composables/{useAudio,useShowMeaning,useRecorder,useTimer}.ts, resources/js/types/{learning,assessment,roleplay}.ts + the three export lines in types/index.ts, resources/js/pages/employee/Home.vue, resources/js/pages/employee/lesson/Step.vue, resources/js/pages/employee/lesson/Activity.vue, resources/js/components/learning/steps/{SituationStep,GenericStep}.vue, resources/js/components/learning/activities/GenericActivity.vue, resources/js/pages/employee/{Lessons,Phrasebook,Progress,Messages,Certificate,FirstLogin}.vue as MINIMAL but complete pages (title via a page header, cards from the design system, real props from the controllers; the learn-support lane will refine them), resources/css/app.css (append new tokens only).
Requirements:
- Types: type every prop shape the auth-backend report describes (LessonSummary, LessonStepNav, StepBlock<TSettings> with per-type settings from spec B.10, ActivityPayload per B.9, JourneyState, PreTestIntro…). Prefer discriminated unions on block.type / activity.type.
- LessonLayout per H.1, measured from photo_1: top bar 72 px white; logo lockup at x 32; nav on the right: Home (solid house), My Lessons (book), My Phrasebook (gold solid star), user menu 'Welcome!' with chevron, then the language box; tracker band ≈ 96 px tall on a very light blue tint (sample it; add a token if none matches), 9 circles 28 px with numbers, connector lines, labels 14 px under; active circle brand-600 filled white number + label brand-600 semibold; other labels ink-slate; done circles brand-600 outline with check. Footer: 'Guesvia' brand-600 semibold + '|' + 'Real English for a Warmer Welcome' ink-slate 12 px on the left; 'Hotel People. Brighter Futures.' ink-slate 12 px on the right (photo_7/12/14/15/16/18/19 also draw the palm sketch left of it: use /decor/palm-island-tagline.png scaled, only when the page has room — make it a prop). Content width: the cards span x 32 → 1248 at 1280. StepTracker takes steps[] from props (every visible block: label + url + state) and navigates with Inertia Link only to done or current steps (LESSON-04).
- EmployeeLayout per H.1 (AppSidebarLayout with employee nav; journey group Pre-test/Training/Post-test/Certificate shown on every employee page except Home; Help + Log out). Topbar shows 'Welcome, Samira!' / 'Reception Department' from the shared props.
- Shared components per H.2 with the exact visual recipes from the mockups: AudioButton normal = brand-50 bg, brand-600 speaker icon + 'Normal' label 16 px semibold brand-700; slow = success-tint bg, success turtle glyph + 'Slow' success-text (Lucide has Turtle; the mockup's is solid — fill it); ShowMeaningButton = tint-grid/line bg with eye icon, Arabic 'إظهار المعنى' 15 px Cairo over 'Show Meaning' 13 px ink-slate; PhrasebookButton = gold-tint bg, solid gold star, 'Save to Phrasebook' 15 px semibold ink; TipCard = brand-50 bg, bulb icon brand-600, title 'Tip' brand-700 semibold, body ink-slate 13 px; StepFooterNav: Previous = tint-grid bg 56 px tall rounded-lg with arrow-left + 'Previous' 18 px semibold ink-indigo, x 32 width ≈ 218; Next/Start Lesson = brand-600 filled white 20 px semibold with arrow-right, right-aligned width ≈ 280–336. Measure every value from the mockup pixels; write the measured numbers as comments.
- Situation step (photo_1): LessonStepHeader '1. Situation (Intro)' 28 px Poppins bold ink-royal at x 32 y 205; chip 'Reception' with solid bell in a tint-grid pill (x 938, 164×44), 'Lesson 1 / 8' 18 px; left photo card 716×440 rounded-xl at x 32 y 257; right card x 766 → 1248 with title 'Handling Guest Complaints' 30 px bold ink-royal, intro 16 px ink-slate, three objective rows with 60 px tinted round icon chips (brand-50/chat, success-tint/people, gold-tint/check), quote box brand-50 italic 18 px centered; footer nav 'Back to Home' / 'Start Lesson'.
- Home (photo_20): EmployeeLayout; journey stepper (Pre-test active brand-600, Training, Post-test, Certificate) at y 110; left white card (x 218 → 700) with eyebrow 'WELCOME TO GUESVIA' 12 px tracking-wide ink-slate, heading 'Let's start with a short Pre-test' 34 px bold ink-royal, two paragraphs 18 px ink-slate (bold 'not a pass or fail test'), five fact rows with 22 px brand icons, 'Start Pre-test →' full-width brand-600 button 56 px, "I'll do it later" link underlined brand-600; right column: photo card home-receptionist (from the seed manifest via props) 560×352 with the handwritten quote overlay (Caveat, script token) 'A small test today, a brighter you tomorrow!' rotated -6°, 'Good to know' card with bulb + five success check rows, 'Remember!' danger-tint card with heart icon; bottom-right 'Better Communication Brighter Careers' decoration (/decor/better-communication.png) rotated. Start Pre-test posts to learn.tests.start (the tests lane makes it work; the form can exist now). After pre-test submission the same card shows 'Continue where you left off' with the next lesson (JOURNEY-02) — build that variant from journey.continueUrl.
- Verification: HARNESS_PORT=8098 HARNESS_DB=$HOME/.guesvia-harness/shell.sqlite bash storage/harness/serve.sh reset (the content seeder may still be running in another lane; if /learn shows no test or lesson, create the rows with a tinker script using the factories: one published Reception course/unit/lesson with a situation block and one published pre test, and give user samira the department). Screenshot /learn and /learn/lessons/{id}/steps/{blockId} as samira at 1280×853, 1240×698, 390×844; compare with the two mockups using compare.py --overlay; iterate until the region diffs are small and you see no difference by eye; also screenshot /dashboard as harness@guesvia.test at 1280×853 and confirm the admin chrome is unchanged versus storage/harness/before/dashboard-1280x853.png (compare.py should show near-zero diff on the sidebar/topbar regions).
- Gates: npm run check, npm run types:check, phpstan, your tests if any. Report the measured values and remaining differences.`,
        {
            label: 'learn-shell',
            phase: 'Build',
            schema: REPORT,
            effort: 'high',
        },
    );

const contentSeeder = () =>
    agent(
        `${ENV}
# Lane: content-seeder (harness port 8099)
Build spec Part G in full (database/seeders/LearningContentSeeder.php plus data files under database/seeders/data/*.php returning arrays, one per concern: course.php, lesson-handling-complaints.php, practice-activities.php, tests.php, scenarios.php, reminders.php), and extend the existing seeders.
You own: database/seeders/LearningContentSeeder.php, database/seeders/data/**, database/seeders/DatabaseSeeder.php (call LearningContentSeeder after HotelPortfolioSeeder in local/testing; create the harness Super Admin harness@guesvia.test / password named 'Harness Owner' in local/testing when absent; keep the rest), database/seeders/HotelPortfolioSeeder.php (give every seeded employee a username first.last[-n], a participant_code, email consent for ~70 %, first_login_completed_at for ~85 %, last_login_at/last_activity_at spread over the last 14 days with ~15 % null; keep the seat numbers identical), tests/Feature/Seeders/LearningContentSeederTest.php.
Requirements:
- View every mockup in desginphotos/employ/ with the Read tool and transcribe copy EXACTLY (titles, subtitles, IPA, tips, options, dialogue lines, scenario texts, goals, useful phrases, feedback texts, test questions 8/12/15/18/21/23/24/25 with their options, instruction strips and side notes). Media: read database/seeders/data/seed-media.json (written by the asset lane; if a name is missing, create the media_assets row anyway pointing at the expected path and list it) and create media_assets rows (disk public, path content/seed/…, library seed, alt from the manifest, width/height) once, keyed by path.
- Audio: for every playable text (lexicon english_text and hotel_example, listen items, dialogue lines, video example, activity audio_text/guest_audio_text/option audio_text, scenario useful_phrases, the roleplay script lines) call AudioLibrary::ensure() for normal and slow so clips exist; with QUEUE_CONNECTION=sync and TTS_PROVIDER=fake the GenerateAudioClip job writes the WAV files synchronously (the seeder must work when the queue is a database too: it may leave clips pending — say which mode you tested).
- Activities: create through the Activity model so activity_versions row 1 exists; placements on the practice block in the spec order and on the tests as questions 1–25 in order. Tests: intro/settings json per G.4; post-test paired via paired_test_id both ways.
- Accounts and progress per G.7: samira (pre-test not submitted) and amine (pre-test submitted with 25 answered attempts + a score, lesson 1 completed with block completions, one roleplay attempt on Check-in with the G.6 feedback and the photo_18 transcript), plus progress spread over the portfolio employees (~55 % with a submitted pre-test and 1–5 lesson completions, ~20 % with all 8 lessons done and a post-test attempt, a few roleplay attempts). Reminder templates, automation rules and ~10 reminder rows from the Messages sample data (statuses sent/blocked/scheduled).
- Hard requirements surfaced by the foundation lanes: the Check-in scenario slug must be exactly 'check-in' (App\\Services\\Ai\\FakeAiProvider::$scripts is keyed by slug; add the other five slugs there too with short 4-line scripts so every scenario converses); every employee gets a participant_code via User::generateParticipantCode(); departments.focus is set on the catalogue rows (G.1); ai_scenarios.description is NOT NULL; Activity rows must be created through the model so activity_versions v1 is written; MediaAsset rows for seed images use disk 'public' and path 'content/seed/<file>' exactly as seed-media.json names them (58 entries; some are PNG).
- Idempotent (keyed on slugs/usernames): running the seeder twice leaves one of everything. Test: run on sqlite :memory: with Storage::fake('public'), assert counts (8 lessons in Guest Service Basics, 9 visible blocks on the first lesson, 7 practice activities, 25+25 questions, 6 scenarios, users samira/amine exist with the right state), and idempotency.
- Boot the harness (HARNESS_PORT=8099 HARNESS_DB=$HOME/.guesvia-harness/seed.sqlite bash storage/harness/serve.sh reset) to prove migrate:fresh --seed runs clean; stop it after. Report counts and anything you could not transcribe or resolve.`,
        {
            label: 'content-seeder',
            phase: 'Build',
            schema: REPORT,
            effort: 'high',
        },
    );

const adminHotels = () =>
    agent(
        `${ENV}
# Lane: admin-hotels (harness port 8091)
Take the Hotels screen to real data and full function, exactly as docs/specs/0002-hotels-portfolio-management/ (index.md + the four child specs) prescribes. Read them all. The page and its three components (resources/js/pages/admin/Hotels.vue, resources/js/components/hotels/*) are pixel-matched already; storage/harness/before/hotels-1280x853.png is the approved 'before' look.
You own: routes/admin/hotels.php, app/Http/Controllers/Admin/HotelsController.php (may become app/Http/Controllers/Admin/Hotels/*), app/Http/Requests/Admin/Hotels/*, app/Services/Hotels/*, app/Http/Resources/Hotels/* (or a shaper class), app/Models/Hotel.php and app/Policies/HotelPolicy.php (additions), config/guesvia.php hotels section (edit only that section), resources/js/components/hotels/**, resources/js/pages/admin/Hotels.vue, resources/js/types/hotels.ts, tests/Feature/Admin/Hotels/**, existing tests/Feature/Admin/Hotel*Test.php (keep green; move if you like).
Requirements: spec 0002 AC-1 … AC-20 in full: five stat cards, rows, sidebar overview + alerts + quota list from the database (withCount/withSum, no N+1); server-side search/status/capacity filters + pager in the query string on partial reloads (router.get with preserveState, only: [...]); Add Hotel button at the top (Quick Actions panel removed, AC-17) opening a Dialog (Sheet side=bottom below md) with the create form (Inertia <Form> bound to the Wayfinder form variant, errors beside fields); row View swaps the sidebar (hotel query param); row actions overflow menu with Edit (same dialog), Approve/Reject (pending), Extend Contract, Pause/Resume, Archive, Manage seats (dialog with per-department allowed seats + live used counts + capacity states); all writes through form requests + HotelService + AuditLog; approval duration-preserving; pause/resume invariant; login block via Hotel::allowsAccess (the auth lane wires the login check — you only need the model methods, which exist). New status values pending/archived added to the frontend types and pills (sample the tints from existing pills: pending = warning-tint, archived = tint-grid/ink-muted).
Tests: every critical scenario listed in spec 0002 (derived status, pause invariant with Carbon::setTestNow, over quota refusal at service level, cross-tenant 403 by GET and POST for a manager of hotel A, approval timing, blocked login message via the auth lane's mechanism if present else via Hotel::allowsAccess, one audit row per action, migrations on sqlite).
Verification: HARNESS_PORT=8091 HARNESS_DB=$HOME/.guesvia-harness/hotels.sqlite bash storage/harness/serve.sh reset; screenshot /hotels as harness@guesvia.test at 1280×853, 1240×698, 390×844 and compare against storage/harness/before/hotels-1280x853.png: everything except the spec-mandated changes (Add Hotel button, actions menu, real numbers) must be identical. Open the create dialog and the seats dialog and screenshot them too. Fix, re-shoot, stop the server. Gates on your files + the whole tests/Feature/Admin folder.`,
        {
            label: 'admin-hotels',
            phase: 'Build',
            schema: REPORT,
            effort: 'high',
        },
    );

const results = await parallel([
    () =>
        authBackend().then((a) =>
            learnShell(a).then((s) => ({ auth: a, shell: s })),
        ),
    () => contentSeeder().then((r) => ({ seeder: r })),
    () => adminHotels().then((r) => ({ hotels: r })),
]);

const done = results.filter(Boolean);
log('phase 2 lanes done');

phase('Integrate');

const integrate = await agent(
    `${ENV}
# Lane: phase2-integrate
Four lanes just finished. Reports:
${JSON.stringify(done, null, 2)}

Make the tree green and prove the seeded harness boots:
1. vendor/bin/pint --parallel; vendor/bin/phpstan analyse --no-progress --memory-limit=1G — clean.
2. php artisan wayfinder:generate --with-form; npm run check:fix; npm run check; npm run types:check — clean (fix real problems; if a lane left a half-typed prop, type it from the controller).
3. DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test — all green.
4. Confirm config/guesvia.php carries reminders.inactive_days, tests.pre_test_time_limit_seconds and ai.limits.per_employee_daily_turns / per_hotel_daily_turns (add any that is missing with the spec defaults) and that .env.example lists AI_/TTS_/STT_ variables.
5. HARNESS_PORT=8090 bash storage/harness/serve.sh reset; as samira screenshot /learn, /learn/lessons and the first lesson's first step; as harness@guesvia.test screenshot /dashboard and /hotels (1280×853). Confirm status 200, no console errors, no horizontal overflow. Leave the server running on 8090 for the next phase.
Report what you fixed and any open issue the next phase must know (broken prop shapes, missing seed rows, failing screens).`,
    {
        label: 'phase2-integrate',
        phase: 'Integrate',
        schema: REPORT,
        effort: 'high',
    },
);

return { lanes: done, integrate };
