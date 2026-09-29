# 0010 — Safe delete on every admin table

_Requested by the platform owner on 2026-09-27 ("add the delete button on all of the table"). The owner chose **safe delete**: a row is deleted for good only when nothing that matters depends on it. Requirement IDs: DATA-10, SEC-06, ROLE-01, ROLE-02, REM-06._

## Rule

- Every admin table has a **Delete** action. It opens one shared confirmation, `components/common/DeleteRowDialog.vue`.
- If learner or research data, or anything else that must survive, still depends on the row, the server **refuses** and the dialog says why, in one sentence, and what to do instead. For example: "Azure Resort & Spa cannot be deleted because it still has 18 accounts. Archive it instead: access stops and everything is kept."
- Otherwise the row and its own configuration (pivots, quotas, previews, cached AI notes) are deleted, and **one audit row** keeps a snapshot of what was deleted. Passwords and 2FA secrets are left out of the snapshot.
- These are **never deletable**: sent reminders (REM-06 evidence), report and export rows (views of research data), and the translation cache (a deleted row would just come back).

## What blocks each delete

| Table                         | Refused while it still has…                                                                                                                                                                                                                                                                                                     | Instead                                     |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| Hotels                        | accounts, courses, lessons, tests, AI scenarios, practice activities, vocabulary items, media files, AI usage records, pronunciation checks, AI point payments                                                                                                                                                                  | Archive                                     |
| Departments                   | accounts, individual subscribers, hotels (seat quotas), courses, tests, AI scenarios, vocabulary items, pronunciation checks, reminder rules that target it                                                                                                                                                                     | Archive                                     |
| Employees, Individuals, Users | training records: completed steps and lessons, test attempts, answers, role-play attempts, voice recordings, pronunciation checks, certificates, phrasebook items, sent reminders, and AI usage for learners. Users also: never your own account, a Super Admin only by a Super Admin, and at least one active Super Admin kept | Deactivate                                  |
| Lessons                       | any learner with progress: completions, answers, role-plays, pronunciation, recordings                                                                                                                                                                                                                                          | Back to draft                               |
| AI scenarios                  | learners who practised it, published lessons that offer it. Links from draft lessons are removed with it.                                                                                                                                                                                                                       | Remove from the lessons, or keep as a draft |
| Pre/Post-tests                | learners who took it. Unanswered questions used nowhere else go with it.                                                                                                                                                                                                                                                        | Keep it                                     |
| Subscription plans            | hotels on it; being the last active plan                                                                                                                                                                                                                                                                                        | Move the hotels, or mark it Inactive        |
| Payment methods               | recorded payments                                                                                                                                                                                                                                                                                                               | Turn off Visible                            |
| Reminder templates            | automation rules, sent reminders                                                                                                                                                                                                                                                                                                | Mark it Inactive                            |
| Automation rules              | sent reminders                                                                                                                                                                                                                                                                                                                  | Pause it                                    |
| Contact messages              | nothing                                                                                                                                                                                                                                                                                                                         | —                                           |

Counts use the query builder, never Eloquent scopes, so a hotel scope can never hide a dependant (`app/Services/Deletion/DeletionGuards.php`).

## Code

- `app/Services/Deletion/SafeDelete.php`: refuse (a `delete` validation error) or audit, clean up and delete, in one transaction.
- `app/Services/Deletion/DeletionGuards.php`: the blockers per kind of row.
- `app/Http/Controllers/Admin/DeleteController.php`: one action per table, each authorized like the table's other actions.
    - New policy abilities: `delete` on `HotelPolicy` (same as archive), and on `TestPolicy`, `DepartmentPolicy`, `ReminderTemplatePolicy`, `AutomationRulePolicy` and `UserPolicy` (same as update).
    - Back-office users use the Users page's own permission and guards, not `UserPolicy` (that policy is the employee one).
- Routes, each in its table's permission group: `hotels.destroy`, `departments.destroy`, `employees.destroy`, `individuals.destroy`, `users.destroy`, `lessons.destroy`, `ai-scenarios.destroy`, `tests.destroy`, `subscriptions.plans.destroy`, `subscriptions.payment-methods.destroy`, `messages-reminders.templates.destroy`, `messages-reminders.rules.destroy`, `contact-messages.destroy`.
- Frontend: a destructive Delete item in the row menus (Hotels, Departments, Employees). The other tables get an outline Trash button: Individuals, Users, Lessons, AI Scenarios, Tests, message templates and rules, plans, payment methods, contact messages. Phone layouts have it too.
- Arabic: every string is in `lang/ar.json`, including the words that make up a refusal ("accounts", "completed lessons"…).

## Tests

`tests/Feature/Admin/SafeDeleteTest.php` covers, for each table, a refused delete and a clean delete, plus who may delete (a manager of another hotel gets 403; a hotel admin may not touch a Super Admin).

## Not changed, worth deciding

- `profile.destroy` (Settings → delete my own account) still hard-deletes the signed-in user, and the database cascades their training records with it. AGENTS.md §6 asks for it to be removed or restricted to the Super Admin (DATA-10).
- Layout: to fit a second button, some column widths were adjusted.
    - The lesson directory's Action column is wider.
    - The Users table's Actions column is wider, and its header now reads "Actions" instead of "Edit".
    - The Individuals table's Actions column is wider, and its phone cards have three buttons.
