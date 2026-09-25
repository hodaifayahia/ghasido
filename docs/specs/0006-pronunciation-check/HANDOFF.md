# Spec 0006 — handoff

Written for: the next agent or developer who continues the pronunciation
check. Read `index.md` first (design, probe results, scoring rule).

## Status (2026-09-24)

| Part                                                         | State                    |
| ------------------------------------------------------------ | ------------------------ |
| Probe of Deepgram TTS/STT for mispronunciations              | done (`index.md` §2)     |
| Accent per lesson (`lessons.accent`), voice per accent       | done                     |
| Normal + slow audio in the lesson's accent voice             | done                     |
| Pronunciation guide per text + accent (Qwen draft)           | done (no admin UI yet)   |
| Calibration on our own reference audio                       | done (lazy, first check) |
| Check job: free listen → align → hinted listen → score v1    | done                     |
| Coach job (Qwen fast model), Arabic hint behind Show Meaning | done                     |
| Listen & Repeat: result card + word drill                    | done, screenshots taken  |
| Lesson Settings → Accent; Voice studio → voice per accent    | done                     |
| Vocabulary / Expressions "Say it" mic                        | backend ready, no UI     |
| Read-aloud question in Pre/Post-tests (results hidden)       | not started              |
| Guide review screen, attempts in Reports / exports           | not started              |

## Where the code is

- Scoring (pure, unit tested): `app/Services/Pronunciation/` —
  `SpokenWords`, `ReferenceText`, `WordAligner`, `SoundDiff`,
  `PronunciationKnowledge`, `PronunciationScorer`, `PronunciationOutcome`.
- Orchestration: `PronunciationGuides` (guide rows + calibration),
  `LessonSpeech` (prepare a lesson's audio + guides), `PronunciationTargets`
  (what may be said on a step), `PronunciationPresenter` (JSON for the page).
- Jobs: `CheckPronunciation`, `CoachPronunciation` (interactive queue),
  `GeneratePronunciationGuide` (default queue).
- Providers: `SpeechToTextProvider::transcribeWords()` (Deepgram, OpenAI-
  compatible, fake); `AiProvider::pronunciationGuide()` /
  `coachPronunciation()` with prompts in
  `app/Services/Ai/Concerns/BuildsPronunciationPrompts.php`.
- HTTP: `Learn\PronunciationController` —
  `POST learn/lessons/{lesson}/steps/{block}/pronunciation` (throttle 30/min,
  daily cap), `GET learn/pronunciation/{attempt}` (owner or Super Admin).
- Front end: `composables/usePronunciationCheck.ts`,
  `components/learning/pronunciation/*`, `steps/ListenRepeatStep.vue`,
  `lessons/tabs/LessonsSettingsTab.vue`, `tts/TtsVoiceStudio.vue`,
  `lessons/LessonsAudioChips.vue` (sends `lesson_id`).
- Tables: `pronunciation_guides`, `pronunciation_attempts`; columns
  `lessons.accent`, `tts_settings.british_voice` / `american_voice`.
- Config: `config/guesvia.php` → `pronunciation` (score v1, daily cap);
  `config/services.php` → `stt.fake_words` / `fake_hinted_words` (fake STT).

## Rules that are easy to break

- A lesson with `accent = null` keeps the **platform voice** for its audio
  (no regression for existing lessons); it is judged in
  `speakingAccent()` = the platform voice's accent. Audio calls pass
  `$lesson->accent`; judging uses `$lesson->speakingAccent()`.
- The scorer is pure and versioned. Change a threshold → bump
  `guesvia.pronunciation.version`, so research data scored under two rules
  is never mixed silently.
- The coach never changes a score; learners never see provider errors
  (`PronunciationPresenter` shows a plain retry message).
- A check never charges AI points; it is metered in `ai_usages`
  (`pronunciation_check`, `pronunciation_guide`, `pronunciation_coach`).
- `smart_format` must stay off for the word listen ("three" → "3").

## How to run and verify

- Tests: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="Pronunciation|LessonAccent|DeepgramStt|OpenAiCompatibleAiProvider"`
  and `php vendor/bin/phpunit tests/Unit/Pronunciation`.
- Live Deepgram probe (small cost):
  `php artisan tinker storage/harness/probe-pronunciation.php` and
  `…/probe-pronunciation-words.php`.
- Screens: harness on port 8096 with `STT_FAKE_WORDS="would you like a furry quiet:0.55 room"`
  (see `storage/harness/pron-setup.php` for the sentence), then
  `PW_NO_CORS=1 node storage/harness/shoot.mjs storage/harness/jobs/pronunciation.json`.
  Shots: `storage/harness/shots/pronunciation/`.

## Open items

- Thresholds are tuned on synthetic speech only. Record 10–20 real learners
  (some words wrong on purpose) and adjust `unclear_*` / `close_similarity`
  before trusting the scores for research.
- Errors hidden by sentence context ("I sink we…" heard as "I think…") are
  only caught by the word drill; say so in any research write-up.
- PHPStan still reports 11 errors in subscription/checkout files that this
  work did not touch.
