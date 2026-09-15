# 08 — Open Questions & Suggested Additions

The client explicitly invited suggestions: _"if you have ideas that improve the site, don't hold back."_

---

## 8.1 Questions that need a decision before/while building

**Naming & branding**

1. Confirm the name **Guesvia**, or choose an alternative. Is the domain available? Is there a logo already, or does one need to be designed?

**Structure** 2. What is the canonical list of departments for launch (Reception, F&B, Housekeeping, Marketing/Commercial, others)? 3. Is content **shared across all hotels** by department, or can a hotel have its own custom lessons? (The spec assumes shared by default with optional hotel-specific overrides.) 4. Is there a level/CEFR notion (A1/A2/B1), or only difficulty tags on scenarios?

**Tests** 5. Is the Pre-test one test per department, or one general test for everyone? 6. Are test results shown to the employee, or hidden (research consideration — showing Pre-test results could influence behaviour)? 7. Is the Post-test a single attempt only? 8. What exactly unlocks the Post-test — all lessons completed, or a percentage?

**Certificates** 9. What is the completion condition for a Certificate of Completion vs Participation? Is a minimum Post-test score required? 10. Whose name/signature/logo appears on the certificate?

**AI** 11. Which AI provider and which model? Which TTS provider and which voice/accent (British / American)? Is speech-to-text needed for spoken answers, or will the client listen to the recordings manually? 12. Default AI usage limit per employee? 13. Should the AI role-play accept **voice** input from day one, or start with text and add voice later?

**Accounts & contracts** 14. Default contract length (the 60-day example) — is it the same for every hotel or set individually? 15. When a contract ends: hard block, or read-only access to certificate and phrasebook? 16. Is the email mandatory for the PhD cohort only, or for all hotels?

**Scope & phasing** 17. Which features are needed for the **first usable version for the PhD study**, and which can come later? (Suggested split in 8.3.) 18. Are the mockup images available to share? They were referenced but not included with this requirements text — they should be reviewed before UI work starts.

---

## 8.2 Suggested additions

**Learning experience**

- **Spaced review**: a short daily review built from the employee's Phrasebook and past mistakes. Very effective for weak learners and increases return visits — which directly helps the research (participants must keep coming back).
- **Streaks and badges (adult-appropriate)**: "3 days in a row", "20 phrases saved". Motivation without becoming childish.
- **Estimated time per lesson** ("5 min") shown on the card — employees on shift are more likely to start a lesson they know is short.
- **Offline-tolerant audio caching** so a lesson keeps working when the hotel Wi-Fi drops.
- **"Mistakes" list**: automatically collect the items the employee got wrong and offer a quick re-practice.
- **Pronunciation self-check**: record, play back next to the model audio, optional AI pronunciation score.

**Admin**

- **Bulk import of employees** via CSV (name, username, department) — much faster than creating 9 accounts by hand for each hotel.
- **Bulk generation** of meaning + example + audio for a pasted word list, reviewed in a table.
- **Lesson templates** and **duplicate lesson** to speed up building the curriculum.
- **Content health check**: flag lessons missing audio, images, Arabic meaning or a practice activity.
- **Preview as employee** on a simulated phone screen.
- **Scheduled publishing** — release lessons week by week, which keeps a study cohort on the same pace.

**Research-specific**

- **Participant codes** and an anonymised export, so data can go straight into SPSS/R without exposing names.
- **Cohort / group tagging** (e.g. "Study Group A", "Control") for filtering and comparison.
- **Automatic Pre/Post comparison report** per employee and per department: score difference per skill.
- **Activity timeline export** (login times, session durations) — often requested by supervisors as engagement evidence.
- **Consent record** stored with a timestamp.

**Communication**

- In-app announcement banner from Admin/Manager (some employees will never open email).
- WhatsApp or SMS reminder option later, given the audience — email reach may be low in practice.

---

## 8.3 Suggested phasing

**Phase 1 — core platform (needed for the study)**
Accounts & roles, hotel/department structure, seat quotas and contract period, login + first-login email, lessons with all step types, Show Meaning, audio playback, My Phrasebook, progress saving, practice activities, Pre/Post tests with full answer capture, CMS + drag & drop builder, AI content generation + TTS, reports & CSV export.

**Phase 2**
AI Role-play with full feedback criteria and attempts, writing/email activities with AI evaluation, voice answers and recording storage, reminders (manual + automatic), certificates, manager dashboard.

**Phase 3**
Spaced review, badges/streaks, bulk import, content health check, PWA install, anonymised research exports, advanced analytics.

_(If AI Role-play and writing evaluation are considered essential to the study design, they move into Phase 1 — this is a decision for the client.)_
