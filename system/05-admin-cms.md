# 05 — Admin Panel, CMS & Reminders

## 5.1 Admin dashboard (Super Admin)

Full control over: **Hotels, Departments, Employees, Lessons & Content, Tests, AI Scenarios, Reminders, Reports & Export.**

At-a-glance widgets:

- Number of employees (total / per hotel / per department)
- Who started, who completed
- Average progress
- Pre-test / Post-test results
- Latest activity
- Employees who stopped and need a reminder
- AI usage
- Hotels approaching the end of their contract period
- Seat usage vs quota per hotel

| ID     | Requirement                                                                                   |
| ------ | --------------------------------------------------------------------------------------------- |
| ADM-01 | The dashboard gives a quick overview, with drill-down into any hotel, department or employee. |
| ADM-02 | Every management action is available without developer involvement.                           |
| ADM-03 | Admin actions on accounts and content are logged (who did what, when).                        |

---

## 5.2 Hotel Manager / HR dashboard

Scoped to their own hotel only:

- Number of employees; who started; who completed
- Progress %, lessons completed
- Pre/Post results
- Last activity; who needs a reminder
- Send reminders to their own employees
- Create/edit/deactivate employees within the approved per-department quota

**Not available:** editing lessons, tests or AI scenarios; other hotels' data; full AI transcripts.

---

## 5.3 Content management (CMS)

The client will add and edit all lessons and content personally. The CMS must be simple and require **no coding**.

### Content hierarchy

```
Hotel → Department → Course / Unit → Lesson → Blocks
```

| ID     | Requirement                                                                                                                                                             |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CMS-01 | Create, edit, reorder, duplicate, hide/unhide and delete Courses/Units and Lessons.                                                                                     |
| CMS-02 | Inside a lesson, add and arrange: Situation, Vocabulary, Expressions, Dialogue, Video, Audio, Practice, AI Scenario, Text, Note, Image, Email Activity, Phone Activity. |
| CMS-03 | Draft vs Published states, and a **Preview as employee** mode (phone + desktop preview).                                                                                |
| CMS-04 | Content can be assigned to a department globally (shared across hotels) or scoped to a specific hotel.                                                                  |
| CMS-05 | Lesson-level settings: title, cover image, estimated duration, prerequisites, completion condition, visibility.                                                         |
| CMS-06 | Reusable content library — a vocabulary item or expression created once can be reused in several lessons without retyping.                                              |

---

## 5.4 Drag & drop Page / Lesson Builder

| ID     | Requirement                                                                                                                                                              |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| BLD-01 | A simple **Drag & Drop** builder for lessons and pages.                                                                                                                  |
| BLD-02 | Available blocks: **Text, Image, Vocabulary, Expressions, Audio, Video, Dialogue, Practice, Email Activity, Phone Activity, AI Role-play, Note** (and others as needed). |
| BLD-03 | Blocks can be **dragged to reorder, edited, deleted, duplicated and hidden**.                                                                                            |
| BLD-04 | Section layout options per section: **Full width**, **Image + Text**, **Text + Image**, **Cards**.                                                                       |
| BLD-05 | The admin can add images and decorative elements and move them inside the available layouts.                                                                             |
| BLD-06 | Whatever the admin arranges must stay **responsive on both phone and desktop** — the builder should not allow layouts that break on mobile.                              |
| BLD-07 | The builder maps blocks to the employee's step-by-step view: each major block becomes its own step/page for the employee (per LESSON-01).                                |
| BLD-08 | Undo / autosave while building; no accidental loss of a half-built lesson.                                                                                               |
| BLD-09 | _(Suggested)_ Save a lesson as a **template** to reuse its structure for the next lesson.                                                                                |

---

## 5.5 Media & visual element management

| ID     | Requirement                                                                                                                                       |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| MED-01 | The admin controls all imagery: lesson image, cover/banner, vocabulary images, situation images, graphics, icons, badges and decorative elements. |
| MED-02 | Each media slot supports **Upload / Choose / Replace / Remove**.                                                                                  |
| MED-03 | A central media library with search, so images can be reused.                                                                                     |
| MED-04 | Uploaded images are automatically resized/compressed for mobile.                                                                                  |
| MED-05 | Supported: images, audio files, video (upload and/or embed link).                                                                                 |
| MED-06 | The **logo and the main Guesvia brand identity stay separate** from lesson media and are managed in their own settings area.                      |
| MED-07 | Alt text field per image (accessibility + clarity).                                                                                               |

---

## 5.6 Test & scenario management

| ID      | Requirement                                                                                       |
| ------- | ------------------------------------------------------------------------------------------------- |
| TSTM-01 | Build Pre-tests and Post-tests from the same question-type library used in lessons.               |
| TSTM-02 | Set per test: question order (fixed or shuffled), time limits, pass condition, result visibility. |
| TSTM-03 | Pair a Pre-test with its Post-test so they mirror each other in skills and difficulty.            |
| TSTM-04 | Create/edit/test/publish AI scenarios (see 04).                                                   |
| TSTM-05 | Assign tests and scenarios to a department, course or hotel.                                      |

---

## 5.7 Reminders

| ID     | Requirement                                                                                                         |
| ------ | ------------------------------------------------------------------------------------------------------------------- |
| REM-01 | The admin can see each employee's **email address and reminder-consent status**.                                    |
| REM-02 | Send a reminder to **one employee or a group** (by hotel, department, or "inactive" filter).                        |
| REM-03 | Configure **automatic reminders** for employees who have not logged in for a defined period (e.g. 5 / 7 / 14 days). |
| REM-04 | Editable email templates, with variables (name, hotel, progress %, days remaining in the contract period).          |
| REM-05 | Reminders are only sent to employees who gave consent (and a record of consent is kept).                            |
| REM-06 | A reminder log: who was sent what and when; delivery status if the provider supports it.                            |
| REM-07 | The Hotel Manager can send reminders to their own employees only.                                                   |
| REM-08 | _(Suggested)_ In-app notification banner as well as email, since some employees may not check email.                |
