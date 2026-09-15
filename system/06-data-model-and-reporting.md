# 06 — Data Model, Storage & Reporting

The platform doubles as a research instrument. **Answer-level data capture is a hard requirement.**

---

## 6.1 Core entities (proposed)

| Entity                        | Key fields                                                                                                                                            |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Hotel**                     | name, contact, status, contract start/end, seat quotas per department, settings (email mandatory yes/no)                                              |
| **Department**                | name, hotel (or global), description                                                                                                                  |
| **User**                      | username, password hash, role (super_admin / manager / employee), hotel, department, email, email_consent, status, created_by, created_at, last_login |
| **Subscription / Seat quota** | hotel, department, allowed_seats, used_seats                                                                                                          |
| **Course / Unit**             | title, department, hotel scope, order, status                                                                                                         |
| **Lesson**                    | course, title, cover image, order, status, completion condition                                                                                       |
| **Block**                     | lesson, type, order, layout, settings, visibility, content payload                                                                                    |
| **VocabItem / Expression**    | english_text, arabic_meaning, simple_explanation, hotel_example, image, audio_normal, audio_slow, source (manual/AI)                                  |
| **Activity**                  | lesson/test, type, prompt, media, options, correct answer(s), scoring rules, time limit                                                               |
| **Test**                      | type (pre/post), department, paired_test_id, settings (shuffle, time limit, results visibility)                                                       |
| **AIScenario**                | department, difficulty, situation, ai_role, employee_role, objective, expected_language, feedback_criteria, attempts_allowed, input_mode, status      |
| **Enrollment / Progress**     | user, course, status, progress %, current lesson, current step, started_at, completed_at, last_activity_at                                            |
| **ActivityAttempt**           | user, activity, attempt_no, raw_answer (text/choice/audio ref), is_correct, score, ai_feedback (structured), time_taken, submitted_at                 |
| **TestAttempt / TestAnswer**  | user, test, per-question answer, recording ref, score, time taken, timestamps                                                                         |
| **RoleplayAttempt**           | user, scenario, attempt_no, full transcript, criteria scores, feedback blocks, duration, timestamps                                                   |
| **PhrasebookItem**            | user, item ref, saved_at                                                                                                                              |
| **Certificate**               | user, course, type, issued_at, certificate_id, file ref                                                                                               |
| **Reminder log**              | user, type, sent_at, sent_by, template, status                                                                                                        |
| **MediaAsset**                | type, file ref, alt text, uploaded_by, usage references                                                                                               |
| **AIUsage**                   | user, feature, tokens/calls, cost estimate, timestamp                                                                                                 |
| **AuditLog**                  | actor, action, target, timestamp                                                                                                                      |

---

## 6.2 What must be stored (explicit client requirement)

| ID      | Requirement                                                                                                                  |
| ------- | ---------------------------------------------------------------------------------------------------------------------------- |
| DATA-01 | **Not just scores.** The employee's **actual answers**, question by question, for Pre-test and Post-test.                    |
| DATA-02 | **Audio recordings** of spoken answers, stored and playable/downloadable from the admin panel.                               |
| DATA-03 | Written answers stored verbatim with the AI evaluation attached.                                                             |
| DATA-04 | Exercise results and answers throughout the training.                                                                        |
| DATA-05 | AI Role-play: **full conversations and responses**, all attempts, and the feedback given.                                    |
| DATA-06 | Lessons completed, progress %, last activity.                                                                                |
| DATA-07 | Training start date and completion date.                                                                                     |
| DATA-08 | Time taken per question/activity (whether or not a timer was set).                                                           |
| DATA-09 | Phrasebook contents per employee.                                                                                            |
| DATA-10 | Data is retained when an account is deactivated or a contract ends — never auto-deleted.                                     |
| DATA-11 | Content versioning: if a question is edited after answers exist, the stored answer still shows which version it referred to. |

---

## 6.3 Reports & Export

| ID     | Requirement                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| REP-01 | Filter by **Hotel / Department / Employee / Date range** (and by status: started, completed, inactive).                                                                                                                                                                                                                                                                                                                                                                                                                    |
| REP-02 | View the data in detail in the panel — not only aggregates. Drill from hotel → department → employee → individual answer.                                                                                                                                                                                                                                                                                                                                                                                                  |
| REP-03 | **Export to Excel / CSV** — required for the research analysis.                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| REP-04 | Exportable datasets should include at least: <ul><li>Employee list with hotel, department, status, progress, start/completion dates</li><li>Pre-test answers per question per employee</li><li>Post-test answers per question per employee</li><li>Pre vs Post comparison (score per skill/criterion)</li><li>Exercise answers and results</li><li>AI role-play transcripts, attempts, criteria scores</li><li>Writing tasks: prompt, employee answer, AI evaluation, score</li><li>Activity log / last activity</li></ul> |
| REP-05 | Audio recordings exportable as a downloadable bundle, with filenames traceable to employee + question.                                                                                                                                                                                                                                                                                                                                                                                                                     |
| REP-06 | Exports use one row per answer (long format) where possible — easiest for statistical analysis.                                                                                                                                                                                                                                                                                                                                                                                                                            |
| REP-07 | An anonymised export option (participant code instead of name) for publishing research data.                                                                                                                                                                                                                                                                                                                                                                                                                               |
| REP-08 | The Hotel Manager can export only their own hotel's data, and without full AI transcripts.                                                                                                                                                                                                                                                                                                                                                                                                                                 |

---

## 6.4 Privacy and research ethics

| ID      | Requirement                                                                                                                          |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| PRIV-01 | Employees are informed that their activity is recorded and used for training follow-up and research (a short notice at first login). |
| PRIV-02 | Consent for reminder emails is recorded separately and is revocable.                                                                 |
| PRIV-03 | Personal data is limited to what is needed: name, username, email, hotel, department.                                                |
| PRIV-04 | Recordings and transcripts are accessible only to the Super Admin.                                                                   |
| PRIV-05 | Data is transmitted over HTTPS and backed up regularly.                                                                              |
