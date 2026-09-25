export const meta = {
    name: 'spec3-phase3b',
    description:
        'Spec 0003 phase 3b: lesson steps, practice activities and learner support pages, pixel-matched to the employee mockups',
    phases: [
        {
            title: 'Build',
            detail: 'five learner lanes in parallel, each with its own harness port',
        },
        {
            title: 'Integrate',
            detail: 'gates across the tree, seeded harness smoke of every employee screen',
        },
    ],
};

const ENV = `
## Environment (read before running anything)
- Windows host; the repo lives in WSL Ubuntu-24.04 at ~/clickDz/ghasido and is reachable from your file tools as \\\\wsl.localhost\\Ubuntu-24.04\\home\\houdaifayahia\\clickDz\\ghasido (your working directory). Read/Write/Edit/Glob/Grep work on that path. For ANY multi-line file content use the Write tool (heredocs through Bash fail on this UNC path).
- PHP 8.3, composer, node 22, npm, python3+Pillow exist ONLY inside WSL. Run them through the PowerShell tool exactly like this: wsl.exe -d Ubuntu-24.04 -- bash -lc 'cd ~/clickDz/ghasido && <command>'. PowerShell expands $HOME and $( ) inside double quotes, so for anything with $, nested quotes or pipes write a script to storage/harness/<lane>-<n>.sh with the Write tool and run: wsl.exe -d Ubuntu-24.04 -- bash -lc "cd ~/clickDz/ghasido && sed -i 's/\\r$//' storage/harness/<file>.sh && bash storage/harness/<file>.sh"
- Tests: DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=<TestClass> (the FULL suite only once, at the end). PHP gates: vendor/bin/pint --parallel <your paths>; vendor/bin/phpstan analyse --no-progress --memory-limit=1G (level 7, clean). JS gates: npm run check:fix (once, before your final gate), npm run check, npm run types:check. NEVER run npm run build.
- Wayfinder: after changing any routes/*.php run php artisan wayfinder:generate --with-form (never hand-edit resources/js/routes/**, resources/js/actions/**, resources/js/wayfinder/**). Never edit resources/js/components/ui/*. Do not git commit.
- Visual verification harness (storage/harness/README.md): use YOUR OWN port and database: HARNESS_PORT=<port> HARNESS_DB=$HOME/.guesvia-harness/<lane>.sqlite bash storage/harness/serve.sh reset (migrate:fresh --seed, then php -S). Screenshot with node storage/harness/shoot.mjs <jobs.json> ("base": "http://127.0.0.1:<port>"; job files under storage/harness/jobs/<lane>-*.json; screenshots to storage/harness/shots/<lane>/). Compare with python3 storage/harness/compare.py <mockup> <shot> <out.png> --overlay and LOOK at the result with the Read tool. Seeded logins (password 'password'): samira (employee, pre-test not done), amine (employee, pre-test submitted, lesson 1 done). To reach lesson steps as samira, first submit her pre-test (through the UI, or with a tinker script that creates a submitted TestAttempt). Assets come from the Vite on :5173. Stop your server when done. Close Playwright between runs; the machine has 7 GB RAM shared by five lanes.
- Ownership: touch only the files your brief lists; other lanes are editing the rest of the tree right now. A shared file you must change: smallest possible Edit, and say so in your report.
- The contract is docs/specs/0003-employee-journey-and-admin-backend/index.md (read it fully, especially B.9, B.10, H). AGENTS.md applies (design fidelity §0 — measured with Pillow, not eyeballed; tokens only; locked chrome; Wayfinder; no literal URLs; no new packages; mobile first at 390 px; ≥ 44 px targets; Arabic only behind Show Meaning; audio only from stored clips).
- Your final message is your report: files, decisions, deviations from mockup/spec with reasons, gate results, open issues.
`;

const REPORT = {
    type: 'object',
    properties: {
        files: { type: 'array', items: { type: 'string' } },
        decisions: { type: 'array', items: { type: 'string' } },
        deviations: { type: 'array', items: { type: 'string' } },
        open_issues: { type: 'array', items: { type: 'string' } },
        gates: { type: 'string' },
    },
    required: ['files', 'decisions', 'deviations', 'open_issues', 'gates'],
};

const LEARN_COMMON = `
Learner lane rules: the LessonLayout/EmployeeLayout, StepTracker, LessonStepHeader, StepFooterNav, AudioButton, SpeedButtons, ShowMeaningButton/Panel, PhrasebookButton, TipCard, TaskCard, SidePhotoCard, RecorderButton, RecordingPlayer, ProgressRing, ActivityDots, CheckButton, OptionRow, WaveformGlyph components and the useAudio/useShowMeaning/useRecorder/useTimer composables exist under resources/js/components/learning and resources/js/composables (built and pixel-verified by the learn-shell lane; read them and REUSE them — extend through props, do not fork). Page dispatchers resources/js/pages/employee/lesson/Step.vue and Activity.vue switch on block.type / activity.type: add your components to the map (a two-line Edit in each dispatcher is allowed). Props come from the Learn controllers (app/Http/Controllers/Learn/*, BlockPresenter/ActivityPresenter in app/Services/Learning): extend the presenter for your type with a small Edit if a field is missing, and type it in resources/js/types/learning.ts (append). Every screen: measure the mockup (positions, sizes, colours via Pillow sampling), build, screenshot as the right user at 1280×853, 1240×698 and 390×844, compare.py --overlay against the mockup, iterate until you see no difference; list what remains. Arabic never renders until Show Meaning is tapped (CTRL-02). Progress and answers persist server-side on every action (PROG-03). Audio plays stored clip URLs only (the seed created clips through the fake TTS: short tones). Tap targets ≥ 44 px, no hover-only affordances, focus rings visible. Completing a step = StepFooterNav Next posting learn.lessons.step.complete (already wired by the shell for the situation step — reuse).`;

phase('Build');

const lanes = [
    {
        key: 'learn-steps-a',
        port: 8101,
        prompt: `${ENV}${LEARN_COMMON}
# Lane: learn-steps-a (port 8101) — Vocabulary, Useful Expressions, Listen & Repeat
Mockups: desginphotos/employ/photo_2_2026-09-18_11-20-54.jpg (Vocabulary), photo_3 (Useful Expressions), photo_4 (Listen & Repeat).
You own: resources/js/components/learning/steps/{VocabularyStep,ExpressionsStep,ListenRepeatStep}.vue, resources/js/components/learning/vocab/** (VocabFeaturedCard, VocabExampleCard, RelatedWordsList, ExpressionFeaturedCard, ExpressionsList, ListenPanel, RepeatPanel), app/Presenters or the BlockPresenter branches for vocabulary/expressions/listen_repeat (items from block_lexicon_item with image url, ipa, part_of_speech, arabic, explanation, example + audio urls normal/slow for the text and the example; featured id; side title; tip), tests/Feature/Learn/Steps/{VocabularyStepTest,ListenRepeatStepTest}.php.
Vocabulary (photo_2): heading '2. Vocabulary'; left card (x 32 → 848): image 412×292 rounded-xl, word 'Air conditioning' 34 px bold ink-royal + speaker button, IPA 18 px ink-slate, Normal | Slow buttons (each 172×52), 'Save to Phrasebook' full width gold-tint, Show Meaning full width; Example card below (brand-50 header with chat icon 'Example', white body: speaker + sentence with the word in bold, Show Meaning small button centred); right card 'Related Words' (book icon) with five rows (thumb 60×60 rounded, word 16 px + POS 13 px ink-slate, speaker 44 px brand-50 button, eye 44 px tint-grid button) and the tip strip. Clicking a related word swaps the featured item (client state; keep the URL). Show Meaning reveals Arabic (Cairo, rtl) + explanation + hotel example in a panel under the buttons. Star toggles the phrasebook (lexicon_item_id).
Useful Expressions (photo_3): heading '3. Useful Expressions' + subtitle; left card: photo 680×260 rounded-xl, speaker chip + sentence 30 px bold ink, IPA 16 px ink-slate, Normal | Slow | Save to Phrasebook row (154 / 160 / 212 wide), Show Meaning centred; right card 'More Useful Expressions' (chat icon) with five rows (thumb 64×54, text 15 px semibold + IPA 12 px, Normal 44 px, Slow 44 px success-tint turtle, eye) and the tip strip 'Click the speaker to hear the pronunciation. Click the eye icon to see the meaning in Arabic.'
Listen & Repeat (photo_4): heading '4. Listen & Repeat' + subtitle; left: photo 562×326 with the caption pill 'How can I help you?' (dark translucent, white 26 px) overlaid bottom-centre, Tip card under it; right card: 'Step 1: Listen' (speaker icon) + 'Click the button to hear the sentence.' + Normal | Slow (258×64 each); divider; 'Step 2: Repeat' (mic icon) + 'Click the microphone and repeat the sentence.' + RecorderButton 112 px with 'Tap to record' + 'Your recording' player card (play 40 px, waveform, 0:00 / 0:05) + 'Great! Keep practicing!' success strip (appears after a recording exists); bottom row: Show Meaning + Save to Phrasebook (text mode: custom_text + custom_arabic) then Previous / Next. Recording: useRecorder → learn.recordings.store (recordable block) → player uses the returned URL; a denied mic shows the non-blocking fallback text. Multiple items: dots pager under the card (mockup shows one).
Tests: presenters ship items with audio urls and no Arabic until requested? (Arabic MAY ship for lesson steps — it is hidden by the UI; do not ship it for tests, which is the tests lane's concern); phrasebook toggles; recording attaches to the block.`,
    },
    {
        key: 'learn-steps-b',
        port: 8102,
        prompt: `${ENV}${LEARN_COMMON}
# Lane: learn-steps-b (port 8102) — Dialogue, Video, Lesson Completed, My Lessons
Mockups: desginphotos/employ/photo_5 (Dialogue), photo_6 (Video), photo_19 (Lesson Completed). My Lessons has no mockup: build it from the design system (cards) and say so.
You own: resources/js/components/learning/steps/{DialogueStep,VideoStep,CompleteStep}.vue, resources/js/components/learning/dialogue/** (DialogueStepper, DialogueLine, DialogueControls), resources/js/components/learning/video/** (VideoPlayer with custom control bar: play, time, seek, volume, fullscreen; speed buttons drive playbackRate 1.0 / 0.75; ExampleFromVideo card), resources/js/components/learning/complete/** (LessonSummaryCard, ProgressCard, EncouragementCard, WhatsNextCard, ClosingQuote), resources/js/pages/employee/Lessons.vue (replace the minimal page), BlockPresenter branches for dialogue/video/complete (complete needs the per-block summary rows: icon per type, title, subtitle such as '8 words', '6 expressions', 'Completed', '1 of 3 attempts completed'; course progress: completed lessons / total; next lesson url), tests/Feature/Learn/Steps/{DialogueStepTest,CompleteStepTest,LessonsIndexTest}.php.
Dialogue (photo_5): heading '5. Dialogue' + subtitle; left photo 620×420 with the 'Situation: Check-in at the hotel' pill (building icon) bottom-left; right card: 'Step 1 / 6' + six dots (brand-600 active, brand-100 others) + a round arrow-right button; the current line as a bubble (brand-50, avatar 84 px round photo, text 20 px, speaker button 44 px in the bubble); Normal | Slow (256×52); Show Meaning centred (310×60); Tip card under the card spanning the right column. Next line = the arrow or Next dot; the block completes when the last line has been reached (client) and Next posts complete.
Video (photo_6): heading '6. Video – Watch the Situation' + subtitle; left: player 684×352 with the poster and a 72 px translucent play button, custom control bar (play, 0:00 / 0:34, seek, volume, fullscreen) — if the block has no video file (seed), the poster shows and the controls render disabled with the seeded duration label; Tip card under it; right: 'Video Controls' card (play icon title, 'Watch as many times as you need.', 'Normal Speed' gauge / 'Slower Speed' turtle buttons 224×62), 'Example from the Video' card (chat icon, note, sentence card 'How can I help you?' with speaker; Arabic line hidden until Show Meaning per CTRL-02 — the mockup shows it revealed: build the revealed state to match and default to hidden; note this deviation).
Lesson Completed (photo_19): heading '9. Lesson Completed!' with the trophy chip (76 px brand-800) + subtitle; left photo 372×450 with the italic quote overlay + gold underline; middle 'Lesson Summary' card (clipboard-check icon) with seven rows (icon chip, title, subtitle, green check 24 px); right column: 'Your Progress' card (bars icon + info icon; ProgressRing 25 % + '2 of 8 lessons completed' + 'Keep going! You're on the right track.'), success-tint encouragement card with bulb, 'What's Next?' card (arrow icon, 'Continue to Next Lesson →' brand button, 'Back to My Lessons' outline with house icon, the two small Arabic tooltips drawn in the mockup are hover tooltips (ui/tooltip) with the Arabic text — implement as tooltips, hidden by default); bottom closing quote strip ('“Better communication creates happier guests.” — Guesvia'); palm decoration bottom right. Reaching the complete step marks it completed automatically (POST complete on mount if not done) so the lesson completes (LESSON-05).
My Lessons page: EmployeeLayout; page header 'My Lessons' + subtitle; course card(s) with a progress bar; units as sections; lesson rows (number, title, steps count, status pill Completed / In progress / Not started / Locked with a lock icon before the pre-test) linking to learn.lessons.show; locked rows are not links (JOURNEY-01) and the controller still refuses direct URLs with 403.
Tests: dialogue presenter ships lines with audio urls; complete step marks the lesson completed and the next lesson url; lessons index locked until the pre-test; a completed lesson shows Completed.`,
    },
    {
        key: 'learn-practice-a',
        port: 8103,
        prompt: `${ENV}${LEARN_COMMON}
# Lane: learn-practice-a (port 8103) — Practice hub + Listen & Choose, Look & Listen, Best Response, Listen & Match
Mockups: desginphotos/employ/photo_7 (Practice hub), photo_8 (Listen & Choose), photo_9 (Look & Listen), photo_10 (Best Response), photo_11 (Listen & Match).
You own: resources/js/components/learning/steps/PracticeHubStep.vue, resources/js/components/learning/practice/** (PracticeProgressPill, PracticeActivityCard with the per-type mini previews drawn in the mockup, ActivityFrame (the shared activity page chrome: 'Back to Practice' button at x 40, the big tinted icon chip + numbered title + subtitle, the department chip and Lesson n / N on the right, left column SidePhotoCard + Task/Tip cards, dots pager bottom-centre, Previous bottom-left, Check bottom-right), PromptAudioBar (speaker 72 px + waveform + '1 / 5'), SpeedRow (Normal Speed / Slower Speed / Show Meaning trio)), resources/js/components/learning/activities/{ListenChooseActivity,LookListenActivity,BestResponseActivity,ListenMatchActivity}.vue, resources/js/pages/employee/lesson/Activity.vue (the frame wiring: the Activity page uses ActivityFrame; keep the dispatcher map), BlockPresenter branch for practice (activities list with type label/description/tone/icon, completed count from attempts, each activity's url) and ActivityPresenter additions, tests/Feature/Learn/Practice/{PracticeHubTest,ListenChooseTest,ListenMatchTest}.php.
Hub (photo_7): heading '7. Practice' + subtitle; right of the header the progress pill card (ProgressRing 0/6 'activities completed' + motto + trophy chip gold); 3×2 grid of activity cards (x 32 → 1248, gap 20, card 396×226, tinted bg per tone with a 52 px round icon chip, numbered title 18 px bold in the tone's text colour, description 14 px ink, a mini preview area drawn from the activity's first item (photos / radio+speaker+waveform rows / word chips / a sentence with a blank) and a 'Start →' button filled in the tone colour (this is the ONE place a non-brand fill is allowed because the mockup draws it; use the tone tokens: brand, success, sunset, ai, blossom, gold — add blossom if missing by sampling photo_7's card 5 (≈ #e5397a) and note it). Completed activities show a check on the card. The seed has 7 activities: the mockup shows 6; render every placement and let the grid wrap (say so).
Listen & Choose (photo_8): PromptAudioBar (speaker plays audio_text) + SpeedRow; three option cards 262×240 (letter badge A/B/C 32 px top-left, photo 236×160 rounded, label 18 px semibold centred), selected = brand-600 border + brand-50; Check (disabled until a choice) posts the answer for the current item → result state: correct = success border + check icon + 'Correct!' text, wrong = danger border + X + 'Not quite' + the correct one highlighted (ACC-02 icon + text); then Next item via the dots/Next. Answers for all items are posted item by item (one attempts row per activity attempt with raw_answer accumulating — or one per item; keep one attempts row per activity per try, updated as items are answered, with attempt_no per try).
Look & Listen (photo_9): question strip 'Which audio matches the picture?' with a 60 px speaker chip; three audio option cards (radio 34 px, speaker+waveform pill, letter); SpeedRow under; the left photo is the item image.
Best Response (photo_10): top strip: speaker chip + 'Listen to the guest. What is the best response?' + Normal / Slower / Show Meaning small buttons; three response rows 94 px tall (radio, letter badge, receptionist thumb 116×74, speaker+waveform pill, text 16 px); Situation card + Tip card on the left.
Listen & Match (photo_11): left column 'Listen to the words:' five numbered rows (number chip 44 px, speaker 44 px, waveform, radio 30 px); right column: speed trio, six picture targets 160×130 with letter badges (A–F) + labels in a 3×2 grid; interaction: tap a number row (selected state) then tap a picture to pair (shows the number badge on the picture), pairs can be cleared by tapping again; 'Check Answers' posts {"1":"a",…}; result marks each pair correct/incorrect with icon + text.
Tests: hub lists placements with completed counts; answering writes attempts with raw_answer/score/max/is_correct/time_taken_ms and reveals the correct option only after posting; a second try increments attempt_no; attempts_allowed enforced when > 0.`,
    },
    {
        key: 'learn-practice-b',
        port: 8104,
        prompt: `${ENV}${LEARN_COMMON}
# Lane: learn-practice-b (port 8104) — Watch & Respond, Words & Sentences, Put the Dialogue in Order, plus the generic multiple_choice / speaking / writing / picture_order activity components in lesson mode
Mockups: desginphotos/employ/photo_12 (Watch & Respond), photo_13 (Words & Sentences), photo_14 (Put the Dialogue in Order). The practice-a lane is building ActivityFrame/PromptAudioBar/SpeedRow at the same time under resources/js/components/learning/practice/**: read them when they appear (poll the folder; if after your first 20 minutes they do not exist, build minimal local versions under resources/js/components/learning/activities/shared/ and note it for the integrator).
You own: resources/js/components/learning/activities/{WatchRespondActivity,WordsSentencesActivity,DialogueOrderActivity,PictureOrderActivity,MultipleChoiceActivity,SpeakingActivity,WritingActivity}.vue, resources/js/components/learning/activities/shared/** (SortableList with pointer-event drag + move-up/move-down buttons ≥ 44 px, ACC-03; DropSlots), tests/Feature/Learn/Practice/{WatchRespondTest,WordsSentencesTest,DialogueOrderTest,WritingActivityTest}.php.
Watch & Respond (photo_12): left: video card 542×320 with the subtitle bar ('Guest: I'm sorry, but my room isn't clean yet.') and a control bar (play, seek, 0:04 / 0:08, CC, fullscreen) — poster from the item when no video file; Tip card; right: blossom-tint card with a 60 px blossom question-chip, 'What should you say now?' 20 px bold + 'Choose the best response.'; three response rows (radio 30 px, letter badge, speaker+waveform pill, text 15 px); Normal Speed / Slower Speed / Show Text (eye, 'إظهار النص' over 'Show Text' → reveals the subtitle transcript) trio; 'Check Answer' bottom right.
Words & Sentences (photo_13): top strip 'Listen to the sentence.' with speaker chip + Normal / Slower / Show Meaning; white card: the sentence 26 px with the blank as a 150 px underline ('May I see your ______, please?'), three option cards 240×176 (radio + letter, photo, label 18 px semibold), 'Check' as a full-width success-tint button inside the card (disabled state: success-tint with white text as drawn); after Check the blank fills with the chosen word; Next bottom right.
Put the Dialogue in Order (photo_14): top strip 'Listen to the dialogue.' + speed trio with 'Show Text'; two columns: left 'Drag the sentences to the correct order:' list of five sentence cards (grip icon, 16 px text, 52 px tall) and right 'Your order:' five numbered slots ('Drag a sentence here' placeholder, dashed) + 'Check Answer' button; drag with pointer events AND tap-to-select then tap-a-slot (mobile), plus move-up/down buttons for keyboard; Check posts the ordered ids; result marks each slot correct/incorrect (icon + text) and reveals the correct order.
Generic components: MultipleChoiceActivity (side layout: image left 340×300 + option rows right; grid layout: passage/image on top + 2×2 option cards; passages kind email (From/To/Subject header + body card with the envelope icon) and notice (photo + question below)), SpeakingActivity (question + situation + instruction, image 354×256 left, right card with RecorderButton 96 px, '0:00 / 0:20', 'Click the microphone to start recording.', 'Play My Recording' button), WritingActivity (scenario, request card, information list, textarea ≥ 160 px, word count, Submit → 'Evaluating your answer…' state polling until ai_feedback arrives → criteria list + better answer), PictureOrderActivity (four picture cards with letter badges and captions + four dashed numbered drop slots + drag/tap-to-place). These render in lesson mode here; the tests lane reuses them in test mode (prop mode: 'test' hides Show Meaning/Check and reveals nothing).
Tests: each answer type is scored (dialogue order exact sequence, words_sentences option), writing dispatches EvaluateWrittenAnswer (sync locally) and stores ai_feedback with four criteria, speaking stores response_media_id, attempts rows carry time_taken_ms.`,
    },
    {
        key: 'learn-support',
        port: 8107,
        prompt: `${ENV}${LEARN_COMMON}
# Lane: learn-support (port 8107) — My Phrasebook, My Progress, Messages, First login, bottom nav, Home 'continue' variant polish
No client mockups exist for these pages: build them from the design system (AGENTS.md §3, §7; PanelCard, StatCard, StatusPill patterns; EmployeeLayout) and state so in your report; do not invent decorative art.
You own: resources/js/pages/employee/{Phrasebook,Progress,Messages,FirstLogin}.vue (replace the minimal versions), resources/js/components/phrasebook/** (PhrasebookItemCard with Normal/Slow, Show Meaning, example, Remove; filters by type word/expression and search; grouped by lesson), resources/js/components/progress/** (overall ring, per-course progress bars, per-lesson rows, pre/post scores when visible, role-play attempts count, last activity, training start/completion dates), resources/js/components/messages/employee/** (in-app reminder list with unread state and mark-as-read), resources/js/components/shell/BottomNav.vue (finish: 64 px bar below md with Home / Lessons / Phrasebook / Progress / Profile, active brand-600 + 3 px top indicator, ≥ 44 px targets; wire it into EmployeeLayout and LessonLayout below md if the shell left it unwired — small Edits), app/Http/Controllers/Learn/{PhrasebookController,ProgressController,MessagesController,FirstLoginController}.php (complete what the auth lane stubbed; keep signatures), app/Services/Learning/ProgressService.php (extend), tests/Feature/Learn/Support/{PhrasebookPageTest,ProgressPageTest,MessagesPageTest,FirstLoginPageTest}.php.
First login (AUTH-04/05/06, PRIV-01/02): EmployeeLayout without the sidebar journey group; one centred card: welcome, email field (required when the hotel's settings.require_email), 'Send me training reminder emails' checkbox, the research notice paragraph with an 'I understand' checkbox (required), Continue button; on success redirect to learn.home. Also add the email + consent editing to the existing settings/Profile page? No — out of scope; note it.
Phrasebook (PHRASE-01..05): header 'My Phrasebook' + subtitle; filter bar (search, type select, lesson select); cards in a responsive grid; each card: English text, IPA/POS when present, Normal / Slow AudioButtons, Show Meaning panel (Arabic + explanation + example), lesson name, Remove (outline danger, confirm dialog). Empty state with a sentence and a link to My Lessons.
Progress (JOURNEY-02, DATA-06/07): header 'My Progress'; stat cards (overall progress %, lessons completed, pre-test score or 'Not shown' per visibility, role-play attempts); course cards with progress bars and lesson rows with status pills; 'Continue where you left off' button.
Messages: header 'Messages'; list of in-app reminders (subject, body, sent date, unread dot) with mark-as-read on open; empty state.
Verify each page at 1280×853, 1240×698 and 390×844 as samira and amine: no overflow, sidebar/topbar chrome identical to the approved admin chrome, bottom nav present below md on every employee page including lesson steps.`,
    },
];

const results = await parallel(
    lanes.map(
        (l) => () =>
            agent(l.prompt, {
                label: l.key,
                phase: 'Build',
                schema: REPORT,
                effort: 'high',
            }).then((r) => ({ key: l.key, report: r })),
    ),
);

const done = results.filter(Boolean);
log(`phase 3b done: ${done.map((d) => d.key).join(', ')}`);

phase('Integrate');

const integrate = await agent(
    `${ENV}
# Lane: phase3b-integrate
Five lanes just finished. Reports:
${JSON.stringify(done, null, 2)}

Make the tree green: pint, phpstan (level 7, clean), wayfinder:generate --with-form, npm run check:fix / check / types:check, DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test (all green; fix real problems, never weaken assertions). Then HARNESS_PORT=8090 bash storage/harness/serve.sh reset; as amine walk the seeded first lesson through every one of its nine steps and the seven practice activities (screenshots at 1280×853 into storage/harness/shots/integrate3b/), and as samira /learn, /learn/phrasebook, /learn/progress, /learn/messages: status 200, no console errors, no horizontal overflow, every Next/Check/Start control works. Leave 8090 running. Report fixes and open issues, and a list of screens that still visibly differ from their mockup.`,
    {
        label: 'phase3b-integrate',
        phase: 'Integrate',
        schema: REPORT,
        effort: 'high',
    },
);

return { lanes: done, integrate };
