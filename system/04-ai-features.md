# 04 — AI Features

The AI is used in four places: **role-play conversations**, **evaluating open answers (writing/speaking)**, **helping the admin create content**, and **generating audio**.

---

## 4.1 AI Role-play — a core feature

> "We do not want a generic chatbot. We want a defined scenario based on the job."

### Employee flow

```
Choose a Scenario → Get Ready → Conversation → Feedback
```

| ID    | Requirement                                                                                                                                                                                                                   |
| ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RP-01 | The employee picks from a list of scenarios available to their department (with difficulty and a short description).                                                                                                          |
| RP-02 | **Get Ready** screen explains the situation, the employee's role, the AI's role and the objective — **in English, with Show Meaning available** if the employee needs the Arabic. It may also list useful expressions to use. |
| RP-03 | **Conversation**: a short, complete, turn-by-turn conversation with the AI, in role. Text input and/or voice input (per scenario setting).                                                                                    |
| RP-04 | The AI stays strictly in the configured role and situation. It must not drift into being a general assistant.                                                                                                                 |
| RP-05 | **Each scenario gives the employee 3 attempts** (the number is configurable per scenario by the admin).                                                                                                                       |
| RP-06 | **One attempt = one complete short conversation**, not one message.                                                                                                                                                           |
| RP-07 | After the conversation, **Feedback** is generated.                                                                                                                                                                            |

### Feedback structure

Shown after each attempt:

- **What you did well**
- **What to improve**
- **Better expression** (a stronger way to say something they said)
- **Key phrase to remember**
- Ratings on the relevant criteria: **Vocabulary, Grammar, Fluency, Pronunciation, Politeness, Task Completion** (and any other criterion the admin defines)

| ID    | Requirement                                                                                                                                                                         |
| ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RP-08 | **The evaluation focuses on communicative success.** It must not punish the employee excessively for small grammar mistakes if they conveyed the meaning and handled the situation. |
| RP-09 | Feedback is encouraging in tone, appropriate for adult learners with a weak English level.                                                                                          |
| RP-10 | Feedback items can be saved to My Phrasebook (key phrase / better expression).                                                                                                      |
| RP-11 | The full conversation transcript, the attempt number, the feedback and the criteria scores are all stored.                                                                          |

### Admin scenario configuration

For each scenario the admin defines:

| Field               | Example                                                     |
| ------------------- | ----------------------------------------------------------- |
| Department          | Reception                                                   |
| Difficulty          | Easy / Medium / Hard                                        |
| Situation           | The room is not ready and the guest is angry                |
| AI role             | An angry guest whose room is not ready                      |
| Employee role       | Receptionist                                                |
| Employee objective  | Apologise, explain, and offer a solution                    |
| Expected language   | Apology phrases, explanation phrases, offering alternatives |
| Feedback criteria   | Politeness, Task Completion, Vocabulary, Fluency...         |
| Number of attempts  | 3 (default)                                                 |
| Input mode          | Text / Voice / Both                                         |
| Conversation length | e.g. 6–10 turns, or until objective met                     |

| ID    | Requirement                                                                                                                 |
| ----- | --------------------------------------------------------------------------------------------------------------------------- |
| RP-12 | The admin can **create, edit, duplicate, publish and unpublish** AI scenarios from the panel — no code.                     |
| RP-13 | The admin can **test a scenario before publishing it** (a preview conversation that is not saved to any employee's record). |
| RP-14 | Scenarios can be attached to a lesson as a step, or be available standalone in an "AI Role-play" section.                   |

---

## 4.2 AI usage limits

| ID     | Requirement                                                                                                                                                         |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| AIL-01 | The admin can set an **AI usage limit per employee** (e.g. number of role-play conversations or messages per day / per week / total), to monitor and control usage. |
| AIL-02 | Limits can also be set per hotel.                                                                                                                                   |
| AIL-03 | When the limit is reached, the employee gets a clear message; the rest of the platform keeps working.                                                               |
| AIL-04 | Usage is visible in the admin panel per employee, per hotel and overall, so the client can track it against their own API billing.                                  |

---

## 4.3 AI evaluation of open answers

| ID     | Requirement                                                                                                                                                                                                       |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| AIE-01 | The AI evaluates **written** answers (email/short reply/free text) against: task completion, factual accuracy vs the information given, politeness, clarity and language quality — and proposes a better version. |
| AIE-02 | The AI evaluates **spoken** answers (recordings) where applicable, with transcription stored alongside the audio file.                                                                                            |
| AIE-03 | Evaluation criteria and weighting are configurable per activity by the admin.                                                                                                                                     |
| AIE-04 | The AI's output is stored as structured data (criterion → score + comment), not only as free text, so it can be exported and analysed.                                                                            |
| AIE-05 | The admin can review and, if needed, override an AI score.                                                                                                                                                        |

---

## 4.4 AI-assisted content creation (for the admin)

The admin content flow:

```
Write English content
   → Generate with AI (optional)
   → Arabic Meaning + Simple Explanation + Hotel Example
   → Review / Edit
   → Generate Audio (Normal + Slow)
   → Save / Publish
```

| ID     | Requirement                                                                                                                                                                                                                         |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GEN-01 | When the admin writes an English word or sentence, there are always **two options: Manual** (write the translation, explanation and example yourself) **or Generate with AI**.                                                      |
| GEN-02 | "Generate with AI" proposes: **Arabic meaning + a simple explanation + a hotel-context example** appropriate to the word/sentence. Example: typing `reservation` returns the translation, a simple explanation and a hotel example. |
| GEN-03 | **AI output is never published automatically.** It appears as a draft suggestion that the admin reviews and can edit before Save/Publish.                                                                                           |
| GEN-04 | Regenerate is available if the first suggestion is not good.                                                                                                                                                                        |
| GEN-05 | The same Generate/Manual choice is available for vocabulary, expressions, examples, dialogue lines and activity instructions.                                                                                                       |
| GEN-06 | _(Suggested)_ Bulk mode: paste a list of words and generate meanings + examples + audio for all of them, then review as a table.                                                                                                    |

---

## 4.5 Audio generation (TTS)

| ID     | Requirement                                                                                                                            |
| ------ | -------------------------------------------------------------------------------------------------------------------------------------- |
| TTS-01 | From the same content editor, the admin can **Generate Audio** for any word or sentence, in **Normal** and **Slow** versions.          |
| TTS-02 | Generated audio is **stored as files**, so the employee plays the saved file rather than regenerating it every time they press Listen. |
| TTS-03 | The admin can preview the audio, regenerate it, or **upload their own recording** to replace it.                                       |
| TTS-04 | Voice selection (accent/gender) is configurable, and ideally consistent across the platform.                                           |
| TTS-05 | Audio files are optimised for mobile bandwidth.                                                                                        |
| TTS-06 | Missing-audio detection: the admin panel flags content items that have no audio yet.                                                   |
