# Guesvia — Project Documentation

**Project:** Guesvia (working name — client is open to alternative name suggestions)
**Type:** Responsive web application (mobile-first, must feel app-like on phones)
**Purpose:** English training platform for hotel staff, organised by department and built around real on-the-job situations.
**Context:** The platform also serves as the data-collection instrument for the client's PhD research, so answer-level data capture and export are hard requirements, not nice-to-haves.

**Source:** These documents were written from the client's requirements conversation dated 9–11 September 2026. The client also sent UI mockup images; those images illustrate structure, navigation and user experience only and are **not** to be copied pixel-for-pixel. (The images were not part of this write-up — see `08-open-questions-and-suggestions.md`.)

---

## Document index

| File                                                                         | Contents                                                                                                 |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| [01-overview-and-roles.md](01-overview-and-roles.md)                         | Product summary, user roles, org hierarchy, accounts, authentication, subscriptions and contract periods |
| [02-employee-experience.md](02-employee-experience.md)                       | Employee journey, lesson structure, Show Meaning, audio, My Phrasebook, progress saving                  |
| [03-practice-and-assessment.md](03-practice-and-assessment.md)               | Practice activity types, writing/email activities, Pre-test & Post-test, timers, certificates            |
| [04-ai-features.md](04-ai-features.md)                                       | AI Role-play, AI writing evaluation, AI content generation, text-to-speech, usage limits                 |
| [05-admin-cms.md](05-admin-cms.md)                                           | Admin dashboard, CMS, drag & drop lesson builder, media management, reminders                            |
| [06-data-model-and-reporting.md](06-data-model-and-reporting.md)             | Entities, what must be stored, reporting, filtering and Excel/CSV export                                 |
| [07-design-and-technical.md](07-design-and-technical.md)                     | Visual direction, responsiveness, performance, security, accessibility, i18n                             |
| [08-open-questions-and-suggestions.md](08-open-questions-and-suggestions.md) | Decisions still needed from the client + proposed improvements                                           |
| [09-requirements-checklist.md](09-requirements-checklist.md)                 | Flat traceability checklist of every requirement with IDs                                                |

---

## The platform in one paragraph

A hotel buys a subscription for a set number of employee seats per department. The hotel's HR/manager creates employee accounts within that quota. Each employee logs in with a username and password, takes a Pre-test, then works through lessons built specifically for their department — vocabulary, expressions, listening, dialogues, video, short interactive practice, AI role-play conversations and writing tasks. English stays on screen as the primary language; Arabic meaning is revealed only on demand. Everything the employee does is saved so they can resume from the exact point they stopped, on any device. After finishing, they take a Post-test and download a certificate. The Super Admin builds all content through a drag & drop CMS (with optional AI assistance for translations, explanations, examples and audio), monitors every hotel, and exports the full answer-level dataset for research.

## Core principles to keep in mind while building

1. **Mobile first.** Most employees will use a phone. If a layout only works on desktop, it is wrong.
2. **Low English level.** Lean on images, audio and interaction. Short, simple text.
3. **English is primary, Arabic is on demand.** Never show the Arabic translation by default.
4. **Nothing is ever lost.** Every answer, attempt, recording and position is persisted.
5. **The admin should never need a developer** to add or change lessons, tests or scenarios.
6. **Adult, professional tone.** Lively, animated, colourful — but not childish.
