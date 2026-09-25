export const meta = {
    name: 'spec3-foundation',
    description:
        'Build the spec 0003 domain foundation: schema lanes, providers, asset crops, then integrate',
    phases: [
        {
            title: 'Lanes',
            detail: 'content schema, learner schema, messaging schema, providers, asset crops in parallel',
        },
        {
            title: 'Integrate',
            detail: 'migrate:fresh on SQLite, pint, phpstan, existing tests, cross-lane fixes',
        },
    ],
};

const ENV = `
## Environment (read before running anything)
- Windows host; the repo lives in WSL Ubuntu-24.04 at ~/clickDz/ghasido and is reachable from your file tools as \\\\wsl.localhost\\Ubuntu-24.04\\home\\houdaifayahia\\clickDz\\ghasido (your working directory). Read/Write/Edit/Glob/Grep work on that path. For ANY multi-line file content use the Write tool (heredocs through Bash fail on this UNC path).
- PHP 8.3, composer, node 22, npm, python3 with Pillow exist ONLY inside WSL. Run them through the PowerShell tool exactly like this: wsl.exe -d Ubuntu-24.04 -- bash -lc 'cd ~/clickDz/ghasido && <command>'. If a command needs nested quotes, $( ) or pipes that fight PowerShell quoting, write a script to storage/harness/<lane>-<n>.sh with the Write tool and run: wsl.exe -d Ubuntu-24.04 -- bash -lc "cd ~/clickDz/ghasido && sed -i 's/\\r$//' storage/harness/<file>.sh && bash storage/harness/<file>.sh"
- Migrations check: DB_CONNECTION=sqlite DB_DATABASE=/tmp/<lane>.sqlite php artisan migrate:fresh. Tests: DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=<TestClass>. PHP gates: vendor/bin/pint --parallel (auto-fix) and vendor/bin/phpstan analyse --no-progress --memory-limit=1G (level 7, must be clean for your files). JS gates: npm run check:fix; npm run check; npm run types:check. NEVER run npm run build (it empties public/build where the user keeps files).
- Never edit resources/js/routes/**, resources/js/actions/**, resources/js/wayfinder/**, resources/js/components/ui/*. Do not git commit. Do not touch files owned by another lane (spec Part A rule 7 and the ownership list in your brief).
- The contract is docs/specs/0003-employee-journey-and-admin-backend/index.md. Read it in full before writing code. AGENTS.md is in your context and applies (Laravel 13 attribute style, PHPDoc @property blocks, generics on relations, string columns cast to enums, no ->enum(), no ->after(), factories for every model, class-based PHPUnit tests).
- Existing code to copy patterns from: app/Models/Hotel.php, app/Models/AuditLog.php, app/Enums/HotelAccessState.php, database/migrations/2026_09_18_00000*.php, database/factories/HotelFactory.php, tests/Feature/Admin/HotelModelTest.php.
- Your final message is your report. Be specific: files written, decisions taken, anything you could not finish, and what the integrator must check.
`;

const REPORT = {
    type: 'object',
    properties: {
        files: { type: 'array', items: { type: 'string' } },
        decisions: { type: 'array', items: { type: 'string' } },
        open_issues: { type: 'array', items: { type: 'string' } },
        gates: {
            type: 'string',
            description:
                'what you ran (migrate/pint/phpstan/tests) and the result',
        },
    },
    required: ['files', 'decisions', 'open_issues', 'gates'],
};

phase('Lanes');

const lanes = [
    {
        key: 'schema-content',
        prompt: `${ENV}
# Lane: schema-content
Build spec Part B.3, B.4, B.5 and the enums/services they need.
You own: database/migrations/2026_09_18_1000{10,11,20..27}_*.php, app/Models/{MediaAsset,AudioClip,Course,Unit,Lesson,Block,LexiconItem,Activity,ActivityVersion,ActivityPlacement,AiScenario}.php, app/Enums/{ContentStatus,BlockType,ActivityType,LexiconKind,AudioSpeed,GenerationStatus,MediaKind,MediaLibrary,ScenarioDifficulty}.php, database/factories for each model, app/Policies/{LessonPolicy,AiScenarioPolicy,MediaAssetPolicy}.php, app/Services/Audio/AudioLibrary.php, app/Jobs/GenerateAudioClip.php, app/Services/Learning/ActivityScorer.php (+ app/Services/Learning/ScoreResult.php DTO), tests/Unit/ActivityScorerTest.php, tests/Feature/Content/ContentModelTest.php.
Requirements:
- Exact table/column names from spec Part B. BlockType and ActivityType carry the label/heading/description/tone/icon methods from B.8. Add PHPDoc @property blocks and typed relations. Lessons keep course_id/hotel_id in sync with their unit's course via model events (saving).
- Lesson::scopeForLearner(User) = published, course.department_id = user.department_id, course.hotel_id null or = user.hotel_id; Course::scopeForLearner likewise; AiScenario::scopeForLearner. Lesson::visibleBlocks() ordered by position. Lesson::stepNumberIn(course) helpers: position within course and total lessons in course.
- LessonPolicy::view(User, Lesson): lesson passes forLearner AND $user->hasSubmittedPreTest() (call that method; the learner lane defines it on User; if it does not exist yet when you test, stub the expectation with a Mockery partial or skip that assertion and note it). Super Admin override already exists via Gate::before.
- MediaAsset::url() per B.3; MediaAssetPolicy::view (uploader, Super Admin, or a user whose hotel_id matches the asset's hotel_id or the asset is public-disk).
- AudioClip::hashFor(text), AudioLibrary::ensure/urlFor/urlsFor per B.4. GenerateAudioClip job: ShouldQueue + ShouldBeUnique keyed by clip id, resolves App\\Contracts\\TtsProvider (interface written by the providers lane: method synthesise(string $text, string $voice, AudioSpeed $speed): App\\Contracts\\SynthesisedAudio with public readonly string $binary, string $mime, string $extension, ?int $durationMs) and stores the file on the public disk under content/audio/{yyyy}/{mm}/{uuid}.{ext}, creating the media_assets row (kind audio, library generated), marking the clip done; failed() marks failed with the reason. If the contract file is absent while you work, still code against it; the integrator wires it.
- ActivityScorer::score(ActivityVersion $version, array $rawAnswer): ScoreResult for every ActivityType in B.9 (option match, pair map, ordered list; speaking/writing → is_correct null, score null). Unit test every type including partial credit (score = correct items / total * max 100? No: max_score = number of items, score = number of correct items; is_correct = all correct).
- Activity model: creating a version on create and whenever payload/scoring change (observer or saved event), bumping current_version; currentVersion() relation helper.
- Factories with realistic states (Course published(), Lesson published() withBlocks(), Activity ofType(ActivityType), AiScenario published()).
- Run migrate:fresh on SQLite including the EXISTING migrations, pint, phpstan on your files, and your tests. Report.`,
    },
    {
        key: 'schema-learner',
        prompt: `${ENV}
# Lane: schema-learner
Build spec Part B.1 (users) and B.6 (learner data) and their enums, models, factories and policies.
You own: database/migrations/0001_01_01_000000_create_users_table.php (edit in place: email nullable unique), database/migrations/2026_09_18_100001_add_learner_columns_to_users_table.php, database/migrations/2026_09_18_1000{30..39}_*.php, app/Models/User.php (additions only, keep everything that exists), database/factories/UserFactory.php (add states: withUsername(), consented(), firstLoginDone(), forHotel(Hotel, Department)), app/Models/{BlockCompletion,LessonCompletion,Test,TestAttempt,Attempt,RoleplayAttempt,PhrasebookItem,VoiceRecording,Certificate,AiUsage}.php, app/Enums/{TestType,TestAttemptStatus,ResultsVisibility,RoleplayStatus,CertificateType,AiFeature}.php, factories for each, app/Policies/{TestPolicy,TestAttemptPolicy,AttemptPolicy,RoleplayAttemptPolicy,PhrasebookItemPolicy}.php, tests/Feature/Learner/LearnerModelTest.php, tests/Feature/Learner/UserLearnerColumnsTest.php.
Requirements:
- Exact names from spec B.1 and B.6. Attempts unique(test_attempt_id, activity_id). All decimals decimal(5,2). Durations in ms.
- User additions: fillable for the new columns, casts (datetimes), relations listed in B.1, hasCompletedFirstLogin(), consentsToReminders(), hasSubmittedPreTest(): true when a test_attempts row with status submitted exists for a Test of type pre that Test::scopeForLearner($this) matches (define Test::scopeForLearner(User): published, department match, hotel null or match — the Test model is yours). Also User::generateParticipantCode() static (P- + 6 uppercase alnum, unique) and a scopeEmployees() (hasRole employee) and scopeActive().
- Existing HotelPortfolioSeeder inserts users with email; keep that working (email still unique when present).
- Test model: intro/settings json casts, questions() = activity_placements morph (placeable) ordered by position (ActivityPlacement model is written by the content lane: App\\Models\\ActivityPlacement with placeable morph; code against it), timeLimitSeconds(), resultsVisibility(). TestAttempt: isExpired(), remainingSeconds(), scoreSummary(). RoleplayAttempt: appendTurn(role, text, audioMediaId), turnsCount(), isFinished().
- Policies: TestPolicy::start(User, Test) (forLearner + no in-progress attempt or resume it; pre-test only once when a submitted attempt exists), TestAttemptPolicy::view/update owner only (and status in_progress for update), AttemptPolicy owner, RoleplayAttemptPolicy owner, PhrasebookItemPolicy owner.
- Tests: user columns exist and hasSubmittedPreTest flips after a submitted pre attempt; factories create every model; cross-owner 403 shape at the policy level (assert Gate denies).
- Run migrate:fresh on SQLite with all existing migrations (note the content and messaging lanes are writing their migrations at the same time; if a foreign key target table is missing when you run, mention it and make sure YOUR foreign keys point at the spec's table names), pint, phpstan on your files, your tests plus the existing tests/Feature/Auth and tests/Feature/Settings suites (email nullable must not break them). Report.`,
    },
    {
        key: 'schema-messaging',
        prompt: `${ENV}
# Lane: schema-messaging
Build spec Part B.2 (departments focus/status), Part B.7 (reminders), the reminder services and the XLSX writer from Part D.
You own: database/migrations/2026_09_18_100002_add_focus_and_status_to_departments_table.php, database/migrations/2026_09_18_1000{40,41,42}_*.php, app/Models/Department.php (additions only), app/Models/{ReminderTemplate,AutomationRule,Reminder}.php, app/Enums/{DepartmentStatus,ReminderStatus,ReminderChannel,AutomationTrigger}.php, factories for the three models, app/Services/Reminders/{ReminderService,TemplateRenderer,AutomationRunner}.php, app/Mail/ReminderMail.php (+ resources/views/mail/reminder.blade.php, plain and tidy), app/Jobs/{SendReminderEmail,RunAutomationRules}.php, routes/console.php (add the daily schedule for RunAutomationRules), app/Support/SimpleXlsxWriter.php, tests/Unit/SimpleXlsxWriterTest.php, tests/Feature/Reminders/ReminderServiceTest.php, tests/Feature/Reminders/AutomationRunnerTest.php.
Requirements:
- ReminderService::send(Collection<User> $recipients, ReminderTemplate $template, ReminderChannel $channel, ?User $sender, ?AutomationRule $rule = null): Collection<Reminder>. For email: a user without email or without email_consent_at gets a reminder row with status blocked and blocked_reason ('no_consent' | 'no_email'); consenting users get status queued and SendReminderEmail dispatched; the job sends ReminderMail and sets sent_at/status sent, failed() sets failed. In-app: status sent immediately, read_at null. TemplateRenderer replaces {{name}} {{hotel}} {{department}} {{progress}} {{days_remaining}} {{login_url}} (progress: call $user->progressPercent() if the method exists, else 0 — guard with method_exists; days_remaining from $user->hotel?->daysRemaining()). Every send writes one AuditLog row (action reminders.sent, auditable = template) with recipient count.
- AutomationRunner::run(): for each active rule pick recipients by trigger (inactive_days: employees active with last_activity_at older than N days or null with created_at older than N; not_started: no lesson/block completions and no test attempts; consent_missing: email_consent_at null; posttest_available: skip with a note unless a method User::postTestUnlocked() exists), restricted by audience json, skip users already reminded by this rule in the last N days (or 7 when N null), send through ReminderService, update last_run_at.
- SimpleXlsxWriter: fromRows(array $headers, iterable $rows): string path of a temp .xlsx (ZipArchive; [Content_Types].xml, _rels/.rels, xl/workbook.xml, xl/_rels/workbook.xml.rels, xl/worksheets/sheet1.xml, xl/styles.xml minimal; inline strings, numbers as numbers, XML-escape). Unit test: writes a file, unzips it, asserts the sheet XML contains the cell values, and that the file starts with PK.
- Department model gains focus/status fillable + cast, scopeActive(), and counts helpers used later: coursesCount()/lessonsCount()/testsCount()/scenariosCount() implemented as withCount-friendly relations (courses(), tests(), aiScenarios() hasMany — those models are written by the content/learner lanes; reference App\\Models\\Course, App\\Models\\Test, App\\Models\\AiScenario by name).
- Run migrate:fresh on SQLite with all existing migrations, pint, phpstan on your files, your tests. Report.`,
    },
    {
        key: 'providers',
        prompt: `${ENV}
# Lane: providers
Build spec Part C: configuration, contracts, DTOs, fake and real AI/TTS/STT providers, usage metering, limits and the AI jobs.
You own: config/services.php (add ai/tts/stt), config/guesvia.php (add ai.limits.*, reminders.inactive_days, tests.pre_test_time_limit_seconds), .env.example (add the variables, providers default fake), composer.json (add "@php artisan storage:link" to the setup script after migrate; nothing else), app/Contracts/{AiProvider,TtsProvider,SpeechToTextProvider}.php plus DTOs app/Contracts/{AiReply,AiEvaluation,LexiconDraft,WritingEvaluation,SynthesisedAudio,AiUsageInfo}.php (final readonly classes with public readonly promoted properties), app/Services/Ai/{FakeAiProvider,AnthropicAiProvider,UsageMeter,AiLimitReached}.php, app/Services/Tts/{FakeTtsProvider,OpenAiCompatibleTtsProvider}.php, app/Services/Stt/{FakeSpeechToTextProvider,OpenAiCompatibleSpeechToTextProvider}.php, app/Providers/AppServiceProvider.php (register() bindings only — keep boot() as is), app/Jobs/{GenerateLexiconDraft,GenerateRoleplayReply,EvaluateRoleplayAttempt,EvaluateWrittenAnswer}.php, tests/Unit/FakeProvidersTest.php, tests/Feature/Ai/UsageMeterTest.php, tests/Feature/Ai/ProviderBindingTest.php.
Requirements:
- Before writing AnthropicAiProvider, invoke the Skill tool with skill "claude-api" and follow it for the Messages API request shape; the model id must come from config('services.ai.model') (never hardcoded), base_url default https://api.anthropic.com, key from config, JSON-mode by asking for a JSON object and parsing it defensively. Use Laravel's Http client with timeout 60 and retry 2.
- Contract signatures exactly: AiProvider::roleplayReply(AiScenario $scenario, array $transcript): AiReply; evaluateRoleplay(AiScenario $scenario, array $transcript): AiEvaluation; generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft; evaluateWriting(array $item, string $answer): WritingEvaluation. TtsProvider::synthesise(string $text, string $voice, AudioSpeed $speed): SynthesisedAudio (properties: binary, mime, extension, durationMs). SpeechToTextProvider::transcribe(string $absolutePath, string $mime): string. DTO fields per spec Part C (AiEvaluation: criteria array<string, array{score:int, comment:string}>, overall int, didWell string[], improve array<{title,text}>, betterExpression array{yours,better}, keyPhrase string, summaryLabel, summaryText, usage AiUsageInfo{promptTokens, completionTokens, model, provider}).
- FakeAiProvider: roleplayReply walks a script: if the scenario has a fake_script list in a public static array keyed by scenario slug (put the Check-in script from spec G.5 there and a generic 4-line fallback), returns the next guest line based on how many guest turns are already in the transcript; evaluateRoleplay returns exactly the spec G.6 feedback and criteria; generateLexicon returns templated Arabic/explanation/example strings mentioning the word; evaluateWriting returns 4 criteria with scores 70-85 and a better answer. FakeTtsProvider writes a valid 16-bit mono 22050 Hz WAV of 0.6 s (slow: 0.9 s) sine tone whose frequency = 220 + (crc32(text) % 440) Hz, mime audio/wav, extension wav. FakeSpeechToTextProvider returns "[voice message]".
- UsageMeter::record(?User, AiFeature, AiUsageInfo, float $costEstimate = 0) writes ai_usages (model App\\Models\\AiUsage, written by the learner lane; code against spec B.6); assertWithinLimits(User, AiFeature) counts today's roleplay_turn rows per user and per hotel against config guesvia.ai.limits and throws AiLimitReached (an exception with a user-facing message; rendered by the callers as a flash error).
- Jobs: constructor takes the model id; $tries=3; $backoff=[10,30,60]; ShouldBeUnique where the spec says; each sets ai_status pending→running→done/failed on the owning row (LexiconItem.ai_status + ai_draft; RoleplayAttempt.pending_reply + transcript append via appendTurn('guest', text); EvaluateRoleplayAttempt sets criteria_scores, overall_score, feedback, status completed, ai_status done; EvaluateWrittenAnswer sets Attempt.ai_feedback/score/ai_status). Meter every call. Those models are written by other lanes right now; code against the spec names and note anything the integrator must reconcile.
- ProviderBindingTest: with the fake config the container resolves the fake classes; with provider anthropic/openai the real ones. UsageMeterTest: limit reached throws.
- Run pint, phpstan on your files (mock/skip model-dependent jobs' analysis only if the models are absent and say so), your tests. Report.`,
    },
    {
        key: 'asset-crops',
        prompt: `${ENV}
# Lane: asset-crops
Produce every seed image in spec Part F from the mockups in desginphotos/employ/ using python3 + Pillow inside WSL, and write database/seeders/data/seed-media.json.
Method: for each mockup you need, first view it with the Read tool to locate the region, then measure precisely with Pillow (write storage/harness/crops-probe.py that prints pixel colours along a row/column to find the photo frame edges: photos are surrounded by white or very light blue card backgrounds, so scan for the transition to photo pixels), crop 2 px inside the frame so no border/rounded corner shows, and save to public/content/seed/<name>.jpg (quality 88) — or .png for decor-desk-sign-bell. After cropping, view a few of the outputs with the Read tool to confirm no UI chrome leaked in (letter badges, captions, play buttons, radio circles). Where a caption/quote overlay sits on the photo (photo_4, 5, 16, 17, 19) crop the region above/outside the overlay when that still gives a usable photo; otherwise keep the full frame and say so.
The manifest: { "<name>": { "file": "content/seed/<name>.jpg", "width": W, "height": H, "alt": "<short descriptive alt text>", "kind": "image", "category": "reception|guest|objects|decor|scenario|test" } } for every name in Part F. Also record in the manifest the crop rectangle you used ("source": "photo_8", "box": [x1,y1,x2,y2]) so a later pass can re-crop.
Do not touch anything outside public/content/seed/, database/seeders/data/seed-media.json and storage/harness/. Report the list of names produced with dimensions, and any region you could not isolate cleanly.`,
    },
];

const laneResults = await parallel(
    lanes.map(
        (l) => () =>
            agent(l.prompt, {
                label: l.key,
                phase: 'Lanes',
                schema: REPORT,
                effort: 'high',
            }).then((r) => ({ key: l.key, report: r })),
    ),
);

const done = laneResults.filter(Boolean);
log(`lanes finished: ${done.map((d) => d.key).join(', ')}`);

phase('Integrate');

const summary = done
    .map(
        (d) =>
            `### ${d.key}\nfiles: ${d.report.files.join(', ')}\ndecisions: ${d.report.decisions.join(' | ')}\nopen issues: ${d.report.open_issues.join(' | ')}\ngates: ${d.report.gates}`,
    )
    .join('\n\n');

const integration = await agent(
    `${ENV}
# Lane: schema-integrate
Five lanes just wrote the spec 0003 foundation in parallel. Their reports:

${summary}

Your job: make the whole tree consistent and green.
1. DB_CONNECTION=sqlite DB_DATABASE=/tmp/integrate.sqlite php artisan migrate:fresh — fix ordering/foreign-key problems (rename migration timestamps only if a dependency order is wrong; keep the spec prefixes). Then run it again with DB_CONNECTION=sqlite DB_DATABASE=/tmp/integrate2.sqlite php artisan migrate:fresh --seed to prove the existing seeders (roles, portfolio) still run.
2. vendor/bin/pint --parallel, then vendor/bin/phpstan analyse --no-progress --memory-limit=1G — must be clean at level 7 across the whole app. Reconcile cross-lane references (contract names, DTO fields, model method names such as appendTurn, hasSubmittedPreTest, Test::questions, ActivityPlacement morph name 'placeable', AudioLibrary → TtsProvider, UsageMeter → AiUsage). Prefer fixing the caller to match the spec over changing the spec.
3. DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test — everything green, including the pre-existing suites.
4. Write tests/Feature/FoundationSmokeTest.php: creates one row of every new model through its factory inside one test, and a second test that runs the fake TTS through GenerateAudioClip synchronously (QUEUE_CONNECTION is sync in tests; use Storage::fake('public')) and asserts the clip is done with a media asset.
5. Verify database/seeders/data/seed-media.json exists and every 'file' it names exists under public/. List missing ones.
Report: what you changed and why, the final gate results, and open issues for the next phase.`,
    {
        label: 'schema-integrate',
        phase: 'Integrate',
        schema: REPORT,
        effort: 'high',
    },
);

return { lanes: done, integration };
