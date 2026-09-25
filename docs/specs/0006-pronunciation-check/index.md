# Spec 0006 — Pronunciation check with the tools we already have

Status: **slice 1 built** (2026-09-24): accents, voices per accent, guides,
calibration, the check and coach, and Listen & Repeat with the word drill.
Slices in §8 are next. Build notes and how to run: `HANDOFF.md`. Owner
decision: no new paid engine (Azure Pronunciation Assessment was rejected on
cost); use Deepgram (TTS + STT) and Qwen, which the platform already pays for.

Requirement IDs: LESSON-06, CTRL-05, TTS-01, TTS-02, RESP-05, TEST-07,
DATA-02, DATA-08, AIE-01, AIE-04, RP-08, RP-09, PERF-04, ACC-02, ACC-03,
AIL-04, API-03, API-04, ROLE-02, SEC-01, SEC-04, PRIV-04, GEN-03.

## 1. The question we answer

Not "is this native-perfect?" but **"would a guest understand you?"**
(intelligibility). That is what hotel staff need and what the spec rewards
(RP-08: communicative success over perfection). An STT engine is a model of
a listener: if it hears the word we expected, a guest most likely would too.

## 2. Probe results (2026-09-24, `storage/harness/probe-pronunciation*.php`)

Deepgram TTS rendered each sentence correctly and with a typical
Arabic-speaker error spelt in; Deepgram nova-3 transcribed both.

| Case                               | Heard (US voice)                 | Heard (GB voice)      |
| ---------------------------------- | -------------------------------- | --------------------- |
| "a **fery** quiet room" (sentence) | "furry"                          | very (0.92)           |
| "I **sink** we have…" (sentence)   | think (1.00) — hidden by context | think (1.00) — hidden |
| "Do you need **barking**?"         | barking                          | barkin                |
| "for **tree** nights"              | three (0.82)                     | tree (0.77)           |
| "sink" (word alone)                | sync                             | sync                  |
| "fery" (word alone)                | free                             | ferry                 |
| "pill" for bill (word alone)       | pill                             | (nothing)             |
| "fegetarian" (word alone)          | sagittarian                      | vegetarian (0.87)     |

Findings that shape the design:

1. **Single words are judged well.** Out of context the recogniser cannot
   "fix" the error, so almost every wrong sound shows up as another word.
2. **Sentences are judged partly.** An error that makes another plausible
   word is caught; an error the language model can guess from context
   ("I sink we…") is hidden. Hence the **drill loop**: a word flagged in the
   sentence (or any word the learner taps) is re-checked on its own.
3. **Confidence dips are a real signal** (very 0.92, three 0.82 vs 1.00 for
   the reference), but only relative to what "perfect" scores for that
   sentence. Hence **calibration** against our own reference audio.
4. Reference audio is not always recognised perfectly (a hallucinated
   "show" after "parking"; the first word of a clip often scores 0.3–0.6).
   Words the recogniser misses even on the reference are **not judged**.
5. `keyterm` hints on pre-recorded audio work (tree → three 0.50 when
   hinted): a word the learner only gets with hints is "almost".
6. `language=en-GB` / `en-US` is accepted but changed nothing measurable
   and lowered one GB confidence; we send `language=en`.
7. `smart_format` must be **off** for this: it turns "three" into "3".
8. Latency 0.4–1.1 s per STT call.

Honest limits: vowel length, word stress and intonation are not judged
when the word is still understood. Stated in the UI copy nowhere as a
"native score"; the score is labelled clarity for listeners.

## 3. Decisions (defaults chosen by the owner's "follow best practice")

| Decision       | Choice                                                                                                                                                                                                |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Accent         | Per lesson: `lessons.accent` = `en-GB` \| `en-US`, null = platform default (the accent of the platform voice)                                                                                         |
| Voices         | One voice per accent in Settings (British voice, American voice); the existing platform voice stays for role-play and tests                                                                           |
| First surfaces | Listen & Repeat (the mockup already has the Repeat mic). Vocabulary / Expressions "Say it" and a Read-aloud test question are later slices (§8)                                                       |
| Tests          | Pronunciation results are stored but never shown during a Pre/Post-test (TEST-03) — applies when the test slice lands                                                                                 |
| Points         | A check never charges AI points; it is metered for cost (`ai_usages`) and capped per employee per day (`guesvia.pronunciation.daily_checks_per_employee`)                                             |
| AI output      | The guide (IPA, traps) is an internal scoring aid, reviewable later; learners only see the coach tip, which is runtime feedback like a speaking evaluation (AIE-01), never published content (GEN-03) |

## 4. Authoring: prepare "what we know" (once per text + accent)

For every **speakable text** of a lesson — Listen & Repeat sentences and the
English text of Vocabulary / Expressions items — in the lesson's accent:

1. **Audio**: normal + slow clips in the accent's voice (`GenerateAudioClip`;
   clips are keyed by text + voice + speed, so a sentence used by a British
   and an American lesson has two sets).
2. **Pronunciation guide** (`pronunciation_guides`, unique per text hash +
   accent), drafted by Qwen (`AiProvider::pronunciationGuide`): per word the
   IPA, syllables with stress, a "sounds like" respelling, one tip for Arabic
   speakers, **trap words** (`heard_as` → the sound that went wrong, e.g.
   `ferry` → v→f) and homophones that must count as correct (`suite` /
   `sweet`). A fixed list of sounds Arabic speakers find hard steers it.
3. **Calibration**: the first check of a text runs STT once on the stored
   normal clip and keeps each word's confidence and timing on the guide row
   (with the voice it measured). Reference words the recogniser misses are
   marked "not judged".

Triggers: changing a lesson's accent, "Generate audio" in the lesson
builder, the all-lessons generation, AI lesson generation, and — as a safety
net — the first learner check of a text with no guide.

## 5. Learner check (Listen & Repeat)

```
record (existing recorder: webm / mp4 / ogg are fine for Deepgram)
  → POST learn/pronunciation  {block_id, item, word?, audio}
      server resolves the reference text from the block (never the client),
      stores the recording privately (voice_recordings), creates a
      pronunciation_attempts row (pending), queues CheckPronunciation
  → CheckPronunciation (interactive queue)
      1. STT free listen (no hints)          → words + confidence + timing
      2. align heard words to the reference  (Needleman–Wunsch, PHP only)
      3. only if some word failed: STT again with the reference words as
         keyterms → a word it now hears counts as "almost"
      4. classify + score (fixed formula, versioned)  → status done
      5. queue CoachPronunciation unless every word is correct
  → CoachPronunciation (interactive queue)
      Qwen fast model gets the structured result + the guide entries of the
      weak words + the accent + the learner level; returns one line of
      praise, up to two {word, tip} and an optional Arabic hint (shown only
      behind Show Meaning, CTRL-01/02). It never changes a score.
  → the page polls GET learn/pronunciation/{attempt}: words first, tip next
```

Word statuses: `correct`, `unclear` (right word, low confidence vs the
reference), `almost` (only heard with hints), `mispronounced` (heard as a
similar word; a trap hit names the sound), `different` (heard as an
unrelated word), `missed`, `skipped` (numbers, words the reference itself
fails). Extras and fillers ("um") are recorded for the flow score.

Score v1 (config `guesvia.pronunciation`):

- **Words** 60 % — credit per judged word: correct 1, unclear 0.7,
  almost 0.5, mispronounced 0.25, different / missed 0.
- **Clarity** 25 % — mean of min(1, confidence ÷ reference confidence).
  Dropped (weights renormalised) when the STT gives no confidence.
- **Flow** 15 % — 100 minus penalties: speaking much slower than the
  reference, pauses > 0.7 s, fillers, extra words.
- Level: ≥ 90 Excellent, ≥ 75 Good, ≥ 50 Keep practising, else Try again.
  Excellent and Good need every judged word understood: a sentence with a
  mispronounced, different or missed word is at best Keep practising,
  whatever its score ("a furry quiet room" scored 89 in the harness).

Every attempt stores the raw STT words of both listens, the keyterms used,
the scoring version and the thresholds, so the research data can be
re-scored later without re-recording.

## 6. Data (never deleted, DATA-10)

`pronunciation_attempts`: user, hotel, department, lesson, block,
lexicon item, `item_index`, `word_index`, voice recording, reference text,
accent, attempt number, status + failed reason, STT provider/model, raw STT
(json), per-word results (json), extras, fillers, recording duration,
scores (overall, words, clarity, flow), level, scoring version, coach
feedback (json) + its status, started / checked / coached timestamps.

`pronunciation_guides`: text, text hash, accent, status, source, full-text
IPA, per-word entries (json), tips (json), calibration (json), provider,
model, generated / reviewed timestamps.

## 7. Security

- The reference text is resolved server-side from a block the learner may
  open (`LessonPolicy::view`, which includes the Pre-test gate, JOURNEY-01);
  a block of another hotel or department is a 403 (ROLE-02, SEC-01).
- Recordings stay on the private disk (PRIV-04, SEC-04); only the owner
  (and the Super Admin) can read an attempt.
- Upload type and size checked by detected MIME (same list as recordings).
- Throttled per minute and capped per day.

## 8. Later slices (not in this build)

1. "Say it" mic on Vocabulary / Expressions cards (backend already accepts
   lexicon targets).
2. Read-aloud question type for Pre/Post-tests, results hidden (TEST-03),
   exported in the long-format research export (REP-06).
3. Admin review screen for guides (edit IPA / traps, regenerate — GEN-04),
   and pronunciation attempts in Reports.
4. Optional: an audio LLM second opinion if the Qwen token plan ever
   includes one (not available on the current plan's text endpoint).
