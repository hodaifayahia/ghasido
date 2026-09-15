# 02 — Employee Experience

## 2.1 Overall journey

```
Login (username + password)
   ↓
First login only: add Email + reminder consent
   ↓
PRE-TEST  ← mandatory gate: lessons stay locked until it is completed
   ↓
My Training (course / unit list for my department)
   ↓
Lesson → Lesson → Lesson ...   (progress saved continuously)
   ↓
POST-TEST
   ↓
CERTIFICATE (download)
```

| ID         | Requirement                                                                                                                                                          |
| ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| JOURNEY-01 | The employee **cannot start any lesson until the Pre-test is completed.** Lessons appear locked until then.                                                          |
| JOURNEY-02 | The home screen shows: overall progress %, "Continue where you left off" as the primary action, the next lesson, and quick access to My Phrasebook and AI Role-play. |
| JOURNEY-03 | The employee sees only their own department's courses. No other department is listed or reachable.                                                                   |
| JOURNEY-04 | Post-test unlocks after the required lessons are completed (the Super Admin defines the completion condition).                                                       |
| JOURNEY-05 | The certificate becomes available after the defined completion condition is met.                                                                                     |

---

## 2.2 Lesson structure

The default learning path inside a lesson:

```
Situation → Vocabulary → Useful Expressions → Listen & Repeat → Dialogue
→ Video → Practice → AI Role-play → Lesson Complete
```

| ID        | Requirement                                                                                                                                                                                                                                                                        |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| LESSON-01 | **Each part is its own step / page.** The employee moves forward one step at a time. Never put everything on one long crowded page.                                                                                                                                                |
| LESSON-02 | Not every lesson contains every part. The Super Admin chooses which parts a given lesson has, and in what order.                                                                                                                                                                   |
| LESSON-03 | A step indicator / progress bar shows where the employee is in the lesson (e.g. "Step 3 of 7").                                                                                                                                                                                    |
| LESSON-04 | Navigation: Next / Back, plus the ability to jump back to an already-completed step of the same lesson.                                                                                                                                                                            |
| LESSON-05 | A "Lesson Complete" screen at the end with a short summary, what was learned, and the next action.                                                                                                                                                                                 |
| LESSON-06 | The design relies heavily on **images, audio and interaction with short simple sentences**, because many employees have a weak English level. The employee sees the picture, hears the word or sentence, and only then moves gradually toward sentences, dialogue and application. |

### Step types (content blocks)

| Step                                | What it contains                                                                                   |
| ----------------------------------- | -------------------------------------------------------------------------------------------------- |
| **Situation**                       | An image + a short description of the real work situation the lesson is about.                     |
| **Vocabulary**                      | Word cards: image, English word, audio (normal/slow), Save, Show Meaning.                          |
| **Useful Expressions**              | Ready-to-use sentences with the same controls, plus optional hotel example.                        |
| **Listen & Repeat**                 | Audio playback; the employee repeats (optionally records themselves for comparison).               |
| **Dialogue**                        | A two-party conversation (guest ↔ employee), line by line, each line playable, optional role-swap. |
| **Video**                           | Embedded or uploaded video, optional captions/subtitles.                                           |
| **Practice**                        | One or more interactive activities (see `03-practice-and-assessment.md`).                          |
| **AI Role-play**                    | Scenario-based conversation with the AI (see `04-ai-features.md`).                                 |
| **Text / Note / Image**             | Free content blocks used by the admin for explanations or tips.                                    |
| **Email Activity / Phone Activity** | Writing and speaking tasks in a realistic work context.                                            |

---

## 2.3 Word and sentence controls

Every important word or sentence carries the same four controls:

| Control             | Behaviour                                                                        |
| ------------------- | -------------------------------------------------------------------------------- |
| 🔊 **Normal**       | Play the audio at normal speed.                                                  |
| 🐢 **Slow**         | Play the same audio slowed down for weak listeners.                              |
| ⭐ **Save**         | Add the item to My Phrasebook.                                                   |
| 🌐 **Show Meaning** | Reveal the Arabic meaning (+ simple explanation + hotel example when available). |

| ID      | Requirement                                                                                                                                                                                                                                                                                                               |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CTRL-01 | **English stays visible as the base.** The Arabic translation is never shown on the page by default.                                                                                                                                                                                                                      |
| CTRL-02 | Arabic appears only after the employee taps **Show Meaning**, and can be hidden again.                                                                                                                                                                                                                                    |
| CTRL-03 | Show Meaning applies to Vocabulary, Useful Expressions, examples, dialogue lines, activity instructions and AI Role-play instructions — and, in principle, **anywhere the admin decides to enable it**. It should be a reusable capability available on essentially every text element, switchable per item from the CMS. |
| CTRL-04 | Show Meaning is **disabled during Pre-test and Post-test** (see 03).                                                                                                                                                                                                                                                      |
| CTRL-05 | Audio is pre-generated and stored, so tapping Listen plays a saved file instead of regenerating it each time (fast on mobile, cheaper, works on weak connections).                                                                                                                                                        |
| CTRL-06 | Audio controls must have large, thumb-friendly tap targets on mobile.                                                                                                                                                                                                                                                     |

---

## 2.4 My Phrasebook

| ID        | Requirement                                                                                                                         |
| --------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| PHRASE-01 | The employee has a personal **My Phrasebook** where they save words and expressions they need.                                      |
| PHRASE-02 | Items can be saved from anywhere in the app via the ⭐ control.                                                                     |
| PHRASE-03 | In the Phrasebook the employee can: play the pronunciation (normal/slow), show the Arabic meaning, see the example, remove an item. |
| PHRASE-04 | Items are grouped/filterable (by lesson, by type: word / expression, or searchable).                                                |
| PHRASE-05 | The Phrasebook is personal to each employee and persists across devices and sessions.                                               |
| PHRASE-06 | _(Suggested)_ A short "review my saved items" practice mode built from the Phrasebook.                                              |

---

## 2.5 Progress saving — "Continue where you left off"

This is called out by the client as **very important**.

| ID      | Requirement                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PROG-01 | Everything the employee does is saved to their account, server-side. If they leave and come back another day, or log in from another device, they resume **exactly** where they stopped.                                                                                                                                                                                                                                                                                        |
| PROG-02 | Saved state includes, at minimum: <ul><li>current course, lesson and step/page</li><li>completed activities</li><li>exercise answers and results</li><li>Pre-test and Post-test answers, scores and recordings</li><li>AI Role-play attempts, transcripts and feedback</li><li>saved words and expressions in My Phrasebook</li><li>progress percentage per lesson / course / overall</li><li>last activity timestamp</li><li>training start date and completion date</li></ul> |
| PROG-03 | Saving happens automatically and continuously (on each step completion / answer submission), not only at the end of a lesson.                                                                                                                                                                                                                                                                                                                                                   |
| PROG-04 | If the connection drops mid-activity, the app should recover gracefully and not lose the answer already submitted.                                                                                                                                                                                                                                                                                                                                                              |
| PROG-05 | A visible "Continue where you left off" entry point on the home screen.                                                                                                                                                                                                                                                                                                                                                                                                         |
| PROG-06 | Progress is never silently reset. Only the Super Admin can reset a specific employee's progress, and that action is logged.                                                                                                                                                                                                                                                                                                                                                     |
