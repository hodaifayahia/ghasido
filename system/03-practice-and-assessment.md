# 03 — Practice, Assessment, Timers & Certificates

## 3.1 Practice activity types

The admin picks which activities a lesson uses. Activities are short, simple, and lean on images and audio. Simple feedback is shown after the answer.

| Type                            | Description                                                          |
| ------------------------------- | -------------------------------------------------------------------- |
| **Listen & Choose**             | Hear a word/sentence, choose the correct option (text or picture).   |
| **Look & Listen**               | Image + audio matching / recognition.                                |
| **Best Response**               | A guest says something — choose the most appropriate reply.          |
| **Listen & Match**              | Match audio items to images or text items.                           |
| **Watch & Respond**             | Watch a short video, then answer or choose a response.               |
| **Words & Sentences**           | Build a sentence from words, fill a gap, complete a sentence.        |
| **Put the Dialogue in Order**   | Drag conversation lines into the correct sequence.                   |
| **Email Activity (Writing)**    | Write a reply to a realistic request — AI-evaluated (see 3.2).       |
| **Phone Activity (Speaking)**   | Respond by voice to a phone-call situation — recorded and evaluated. |
| **Speaking / Record & Compare** | Repeat a sentence, record, compare with the model audio.             |

| ID      | Requirement                                                                                                                                                                                     |
| ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PRAC-01 | The admin selects the activity type per lesson according to what the department needs: listening for listening-heavy roles, writing for writing-heavy roles, speaking for speaking-heavy roles. |
| PRAC-02 | Activities must be short and simple, with images and audio wherever possible.                                                                                                                   |
| PRAC-03 | After answering, the employee gets simple feedback (correct/incorrect + a short explanation the admin can write, or AI feedback for open answers).                                              |
| PRAC-04 | Answers and results are saved to the employee's account (see 06).                                                                                                                               |
| PRAC-05 | Activity types are reusable: **any activity type usable in a lesson must also be usable in the Pre-test and Post-test.**                                                                        |
| PRAC-06 | Each activity supports optional images, optional audio, and optional Show Meaning on its instructions.                                                                                          |
| PRAC-07 | Multiple attempts within a practice activity are allowed (configurable per activity), while tests are single-attempt by default.                                                                |

---

## 3.2 Writing / Email Activity

A key request — the platform must not be audio-only. Some departments (especially **Marketing / Commercial**) mainly write.

**Employee flow:**

1. A realistic hotel request is shown — e.g. a guest asking about a booking, prices, taxes, or room availability.
2. Below it, the **information the employee needs to answer** (rates, availability, tax rate, policies) is provided.
3. A text box where the employee writes the reply in English.
4. Submit.
5. The AI corrects and evaluates the answer:
    - Did it answer what was asked?
    - Is the information correct (against the data given)?
    - Is the language polite and clear?
6. A **suggested better reply** is shown at the end.

| ID       | Requirement                                                                                                                             |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| WRITE-01 | The writing task presents a scenario + supporting information + a free-text answer box.                                                 |
| WRITE-02 | The AI evaluates content accuracy, task completion, politeness and clarity, then shows an improved model answer.                        |
| WRITE-03 | Difficulty is graded and configurable: **(a)** complete or reorder an email → **(b)** write a Short Reply → **(c)** write a full Email. |
| WRITE-04 | The employee's exact written text, the AI evaluation and the score are stored.                                                          |
| WRITE-05 | Writing tasks with AI checking can be inserted **anywhere**: in lessons, in practice, in the Pre-test and in the Post-test.             |
| WRITE-06 | Writing tasks are available to all departments, not only Marketing/Commercial — the admin decides.                                      |

---

## 3.3 Pre-test and Post-test

| ID      | Requirement                                                                                                                                                                    |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| TEST-01 | The employee takes a **Pre-test before any lesson is unlocked**, and a **Post-test after completing the programme**.                                                           |
| TEST-02 | Pre-test and Post-test use roughly the **same template, the same skill types and the same difficulty level**, but **the questions and situations are not literally the same**. |
| TEST-03 | During a test: **Show Meaning is disabled**, and no correct answers or helpful feedback are shown while answering.                                                             |
| TEST-04 | Results are shown after submission according to the admin's setting (score only, or score + breakdown). The admin can also choose to hide results from the employee entirely.  |
| TEST-05 | The test builder supports the same range of question types as lessons.                                                                                                         |

### Question types required

- Listening + Choose Picture
- Listen & Best Response
- Situation + Choose
- Short Reading
- Complete Sentence
- **What Would You Say?** — the employee gives a short response, by **voice recording** or by typing, depending on the question type
- **Writing** — short reply / short email with a given situation and given information (especially for the commercial department). Same task type and difficulty and same evaluation criteria in Pre and Post, but a different situation and content.

### Data capture — critical for the research

| ID      | Requirement                                                                                                                                                                    |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| TEST-06 | **The system must not store only the score.** The employee's **actual answer to every single question** must be stored, question by question, for both Pre-test and Post-test. |
| TEST-07 | **Audio recordings** from speaking questions must be stored and be playable/downloadable from the admin panel.                                                                 |
| TEST-08 | Written answers are stored verbatim, together with the AI evaluation.                                                                                                          |
| TEST-09 | Stored per attempt: answer, correctness, time taken (if timed), timestamp, question version.                                                                                   |
| TEST-10 | Data must be exportable per employee / department / hotel for statistical analysis (see 06).                                                                                   |

---

## 3.4 Timers

| ID      | Requirement                                                                                                                                        |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| TIME-01 | The admin can optionally attach a **time limit** to an exercise, to a test question, or to a whole test.                                           |
| TIME-02 | The timer is off by default and only appears when the admin enables it for that item.                                                              |
| TIME-03 | The employee sees a clear countdown; when time runs out the answer is auto-submitted (or the question is marked as unanswered, per admin setting). |
| TIME-04 | Time taken is recorded even when no limit is set, because response time is useful research data.                                                   |
| TIME-05 | Timer behaviour must survive a page refresh (server-side start timestamp, not client-only).                                                        |

---

## 3.5 Certificates

| ID      | Requirement                                                                                                                                         |
| ------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| CERT-01 | When an employee completes the programme, a **certificate** becomes available to view and download (PDF).                                           |
| CERT-02 | Two types: **Certificate of Participation** and **Certificate of Completion / Achievement**. The admin chooses which applies and on what condition. |
| CERT-03 | The certificate shows at least: employee name, hotel, department, course/programme name, completion date, and the platform logo/signature.          |
| CERT-04 | The admin defines the completion condition (e.g. all lessons completed + Post-test taken, or a minimum Post-test score).                            |
| CERT-05 | The certificate template (background, logo, text) is editable by the admin without code changes.                                                    |
| CERT-06 | Issued certificates are listed in the admin panel and can be revoked or re-issued.                                                                  |
| CERT-07 | _(Suggested)_ A unique certificate ID for verification.                                                                                             |
