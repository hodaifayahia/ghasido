# 01 — Overview, Roles, Accounts & Subscriptions

## 1.1 Organisational hierarchy

```
Super Admin (platform owner)
└── Hotel
    └── Department (Reception / F&B / Housekeeping / Marketing-Commercial / ...)
        └── Employee
```

Content is organised in parallel:

```
Hotel (or global library) → Department → Course / Unit → Lesson → Blocks (steps)
```

| ID     | Requirement                                                                                             |
| ------ | ------------------------------------------------------------------------------------------------------- |
| ORG-01 | The system supports multiple hotels, each with multiple departments, each with multiple employees.      |
| ORG-02 | Departments are configurable, not hard-coded — the Super Admin can add, rename or disable a department. |
| ORG-03 | Every employee account is bound to exactly one hotel and one department.                                |
| ORG-04 | Courses/Units and Lessons are assigned to a department (and optionally to a specific hotel).            |

---

## 1.2 Roles and permissions

### Super Admin (the client)

Full control over everything.

- Create / edit / disable **any** account on the platform, including hotel manager and employee accounts, at any time, without waiting for the hotel.
- Create and manage hotels, departments, subscriptions and seat quotas.
- Build and publish all content: courses, lessons, blocks, practice activities, tests, AI scenarios.
- Create, edit and test AI role-play scenarios; set feedback criteria and attempt limits.
- Set AI usage limits per employee.
- View, filter and export all data across all hotels.
- View **full AI conversation transcripts** for research purposes.
- Send reminders and configure automatic reminder rules.
- Manage all images, media and decorative elements.

### Hotel Manager / HR

Scoped to their own hotel only. Reduced permissions.

**Can:**

- Create employee accounts for their hotel, assign each to a department — **within the seat quota approved by the Super Admin per department**.
- Edit / deactivate their own employees' accounts.
- See their hotel's dashboard: number of employees, who started, who finished, progress %, lessons completed, Pre/Post results, last activity, who is inactive and needs a reminder.
- Send reminders to their own employees.

**Cannot:**

- Edit lessons, content, tests or AI scenarios.
- See other hotels' data.
- See full AI conversation transcripts by default (summary/score only).
- Exceed the seat quota, or extend the contract period.

### Employee

- Logs in with username + password.
- Sees only the content of their own hotel + department.
- Takes Pre-test, works through lessons, does practice, AI role-play and writing tasks, takes Post-test, downloads certificate.
- Manages their own My Phrasebook and (optionally) their email + reminder consent.

| ID      | Requirement                                                                                                                                                                                                                              |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ROLE-01 | Three role levels: Super Admin, Hotel Manager/HR, Employee. Permissions enforced **server-side**, not just hidden in the UI.                                                                                                             |
| ROLE-02 | An employee must not be able to reach another department's or another hotel's content — **including by typing a direct URL or calling the API directly**. Every content request is authorised against the employee's hotel + department. |
| ROLE-03 | Super Admin permissions always override hotel-level permissions.                                                                                                                                                                         |
| ROLE-04 | Full AI conversation transcripts are visible to Super Admin only; Manager sees aggregated results unless the Super Admin explicitly grants access.                                                                                       |

---

## 1.3 Authentication and first login

| ID      | Requirement                                                                                                                                                                                                                                                                                          |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| AUTH-01 | Login is **username + password**. The email address is never used as the login identifier.                                                                                                                                                                                                           |
| AUTH-02 | Accounts are created by Super Admin or Hotel Manager/HR. There is **no public self-registration**.                                                                                                                                                                                                   |
| AUTH-03 | The creator sets the username and an initial password and can hand them to the employee.                                                                                                                                                                                                             |
| AUTH-04 | On **first login only**, the employee is asked to add an **email address** and to tick a consent checkbox for receiving reminder emails.                                                                                                                                                             |
| AUTH-05 | Email entry can be made **mandatory or optional per hotel / per cohort**, controlled by the Super Admin. When mandatory, the employee cannot proceed past the first-login screen without entering a valid email. (Needed for the PhD cohort, where the client must be able to contact participants.) |
| AUTH-06 | The employee can later update their email and change their reminder consent from their profile.                                                                                                                                                                                                      |
| AUTH-07 | Password reset / change: employee can change their own password; Super Admin and Hotel Manager can reset an employee's password.                                                                                                                                                                     |
| AUTH-08 | Accounts can be **deactivated** (login blocked, data preserved) rather than deleted.                                                                                                                                                                                                                 |
| AUTH-09 | Sessions persist across devices — the employee can log out on a phone and continue on a laptop with no loss of progress.                                                                                                                                                                             |
| AUTH-10 | Basic protections: hashed passwords, rate limiting on login attempts, session expiry.                                                                                                                                                                                                                |

---

## 1.4 Subscriptions, seat quotas and contract period

This is how the commercial model is enforced in the product.

| ID     | Requirement                                                                                                                                                                                                                                     |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SUB-01 | The Super Admin sets, **per hotel and per department**, the maximum number of employee accounts allowed. Example: Reception = 5 accounts, F&B = 4 accounts.                                                                                     |
| SUB-02 | The Hotel Manager/HR can create accounts freely **up to** those per-department limits. When a limit is reached, account creation for that department is blocked with a clear message ("Reception: 5/5 seats used — contact the administrator"). |
| SUB-03 | The Super Admin can raise or lower a quota at any time. Lowering below the current usage does not delete accounts; it blocks new ones (and flags the overage to the Super Admin).                                                               |
| SUB-04 | Each hotel has a **contract / access period** — a start date and a duration (e.g. 60 days) or an explicit end date.                                                                                                                             |
| SUB-05 | When the period ends, employees of that hotel can no longer log in (or are shown a read-only "Your training period has ended" screen). Their data is **kept**, not deleted.                                                                     |
| SUB-06 | The Super Admin can extend, shorten, pause or reactivate a hotel's period at any time.                                                                                                                                                          |
| SUB-07 | The hotel dashboard and the Super Admin dashboard both show days remaining, with a warning state as the end date approaches.                                                                                                                    |
| SUB-08 | Deactivating a hotel deactivates all of its accounts; reactivating restores them.                                                                                                                                                               |

---

## 1.5 External services and billing

| ID     | Requirement                                                                                                                                                                                     |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| API-01 | All third-party accounts, API keys and billing (AI model provider, text-to-speech, email sending, hosting if applicable) belong to the **client**. The developer performs the integration only. |
| API-02 | API keys are stored as server-side environment variables / secrets — never in the frontend, never in the repository.                                                                            |
| API-03 | The admin panel shows AI/TTS usage so the client can monitor consumption against their own billing.                                                                                             |
| API-04 | The integration must be configurable (model name, keys, endpoints) without a code change, so the client can swap providers or rotate keys.                                                      |
