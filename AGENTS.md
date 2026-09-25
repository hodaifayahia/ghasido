# AGENTS.md — Guesvia

You are building **Guesvia**, an English-training web platform for hotel staff, on Laravel + Inertia + Vue.

Two folders in this repo are the specification. Read the relevant one before building anything:

- **`system/`** — the product spec (roles, journeys, features, data, security). Every requirement carries an ID (`AUTH-01`, `CTRL-04`, `TEST-06`…). `system/09-requirements-checklist.md` is the flat index — grep it first.
- **`desgin/`** — the visual design system (the folder really is spelled `desgin`). Colours, type, spacing, motion, and every component measured from the client's 20 mockups.
- **`desginphotos/`** — the approved mockup images. **They are the visual specification — see §0, which overrides everything else in this file about design.**

Cite requirement IDs in code comments, PR text and your replies when a decision traces to the spec.

---

## 0. Design fidelity — STRICT RULE (read this before touching any UI)

**This rule binds every agent and every person working in this repo — Claude Code, OpenAI Codex, GitHub Copilot, Cursor, Junie, Gemini, humans — and every UI change: pages, components, layouts, CSS, Blade views, emails.** `CLAUDE.md`, `.github/copilot-instructions.md`, `.github/instructions/design.instructions.md` and the header of `resources/css/app.css` all point here; this section is the canonical text.

> **The approved mockups are the specification. Every screen must match its mockup exactly — layout, order, spacing, sizes, colours, typography, icons, imagery, decoration and copy — at the 1280×853 reference size, and must stay intact (nothing overflowing, clipped, scrolled or hidden) at every smaller size. "Close enough", "inspired by", "cleaner" or "modernised" re-interpretations are defects.**

_Client decision, 2026-09-15._ It supersedes UX-08's "reference, not a pixel-for-pixel specification" (`system/07-design-and-technical.md`) and every older sentence in this file or in `desgin/` that says the mockups are structure-only or tells you not to copy them.

### 0.1 Sources of truth, in priority order

1. **Mockup images** in `desginphotos/` (1280×853 px) — see the index in 0.5.
2. **The approved implementation — locked.** The shared chrome of the Admin Dashboard was matched to its mockup and approved by the client on 2026-09-15. It is the reference for the chrome of **every** screen:
   `resources/js/components/AppSidebar.vue`, `NavMain.vue`, `AppLogo.vue`, `shell/AppTopbar.vue`, `shell/PageHeader.vue`, `shell/ScriptAccent.vue`, `components/icons/*`, `layouts/app/AppSidebarLayout.vue`.
3. **Design tokens** — the `@theme` blocks in `resources/css/app.css`. The only allowed colours, fonts, radii, shadows and easings.
4. **Brand and decoration assets** — `public/brand/`, `public/decor/`, processed from the client's originals in `desgin/assets/` (asset map in 0.6).
5. **`desgin/*.md`** and the rest of §3 — only where 1–4 say nothing. When they disagree with 1–4, 1–4 win.

### 0.2 Non-negotiable rules

1. **Match the mockup, measured — never eyeballed.** Take positions, sizes and colours from the image's pixels (crop, zoom, sample the median colour of solid areas; compare rendered text widths). Same structure, same element order, same labels and copy, same icons (solid where the mockup's are solid), same visible states.
2. **Shared chrome is locked.** Do not restyle, re-space, re-colour, re-order, resize or "clean up" the sidebar, topbar, logo, page header, script accent or solid icons unless the user explicitly asks for that change. New screens use them through `AppLayout` (→ `AppSidebarLayout`); never fork or copy them. When a screen's own mockup shows extra chrome decoration (a centre topbar tagline, a header photo strip, a sticky note, a different script accent), add it through props/slots on the shared components without changing their approved geometry.
3. **Tokens only.** No hex/rgb/hsl literals in components and no Tailwind built-in palette classes (`blue-500`, `gray-*`, `indigo-*`, `slate-*`…). If the mockup shows a colour that has no token: sample it, add a named token to `@theme` with a comment naming the mockup it came from, and tell the user in your report. **Never change the value of an existing token.** Never name a token after a Tailwind built-in scale (`sky`, `orange`, `teal`…).
4. **Reuse the approved text styles through their components** instead of restating classes: page title + subtitle = `PageHeader` (Poppins Bold 28px, −0.02em, `text-ink-royal`; subtitle Inter 16px `text-ink-slate`); card = `common/PanelCard`; nav = `NavMain`.
5. **Icons:** `@lucide/vue` when its glyph matches the mockup. When the mockup's glyph is solid, or Lucide has no equivalent, add a small solid SVG component in `resources/js/components/icons/` (`currentColor` fill, cut-outs via an SVG `<mask>` with a `useId()` id). No emoji in chrome, no other icon packages.
6. **Use the client's images.** When the client supplied an asset (logo, palm island, handwritten taglines, photos), use it — never a stock photo, generated placeholder or hand-redrawn approximation. Process the original (tight crop, transparent background, ~3× the rendered size) into `public/brand/` or `public/decor/` and keep the untouched original in `desgin/assets/`. **Never keep source assets in `public/build/`** — every Vite build empties it.
7. **Fit, never overflow.** At 1280×853 the screen matches the mockup. On shorter or narrower windows — users commonly run Windows at 125% scaling, i.e. a ~1240×700 CSS px viewport — nothing clips, nothing scrolls horizontally, and the sidebar does not scroll at heights ≥640px: spacing scales down and decorations shrink instead of pushing content (see `AppSidebar.vue`'s `--sb-unit` rhythm). Below 768px the mobile rules in §7 apply.
8. **No unrequested redesign.** Do not "improve" the design, swap components, change colours or follow a conflicting suggestion in `desgin/*.md`. If a mockup looks wrong, inconsistent between screens, or impossible to reproduce (e.g. a label that only fits in a condensed face), **ask the user** — do not decide silently.

### 0.3 Verification — required before you report a UI change as done

- Run the app (`composer dev`, or Sail) and screenshot the screen at **1280×853** (and at 2× device scale for detail). Compare it with the mockup side by side or as an overlay, region by region — chrome, page header, every card — and fix every difference in position, size, colour, weight, icon or image.
- Re-check at **1240×698** and **390×844**: no horizontal scrollbar, no clipped or wrapped labels the mockup doesn't wrap, no scrolling sidebar.
- `npm run check` and `npm run types:check` pass.
- In your report, list every remaining difference and why it remains. **If you could not run a browser, say so explicitly — never claim a pixel match you did not compare.**

### 0.4 Approved shared chrome — measured values (1280×853)

Change none of these without an explicit user request.

| Element          | Approved value                                                                                                                                                                                                                                                                                                                                   |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Frame            | Topbar 72px (`h-topbar`), white; sidebar 200px, white, **no border** — separated from the `#f4f9fe` content only by tone; content gutter 24px; page content starts 20px below the topbar                                                                                                                                                         |
| Logo (`AppLogo`) | Mark 58×58 at x 32 / y 11; wordmark 136px wide at x 109; tagline 144px wide, 8px under the wordmark                                                                                                                                                                                                                                              |
| Nav (`NavMain`)  | 42px items on a ~44.6px pitch, first pill y 97–139, 10px radius; 26px icons in `text-brand-900`; labels at x 73, Inter Medium 13px, −0.02em, `text-ink-indigo`, one line; active = `bg-brand-100/70` + `text-brand-600` semibold; divider 2px at y ≈ 512                                                                                         |
| Sidebar footer   | `/decor/palm-island-tagline.png`, 128px wide at x 16, bottom edge 42px above the window bottom, 65% opacity; scales down on short windows                                                                                                                                                                                                        |
| Topbar right     | Bell 24px `text-brand-800/85` + 11px `bg-danger` count badge; avatar 34px `bg-brand-800/85`; greeting Poppins SemiBold 13.5px −0.02em `text-ink-indigo`; role Inter 12.5px `text-ink-slate`; language box 111×46 at x 1147 / y 15, `rounded-md border-line shadow-card`, globe 22px `text-brand-700`, "EN" Inter SemiBold 16px; 22px end padding |
| Page header      | Title Poppins Bold 28px, −0.02em, 40px line box, `text-ink-royal`; subtitle Inter 16px `text-ink-slate`; script accent `/decor/better-communication-header.png` 136px wide at x 1124 / y 87, 75% opacity, hidden below md                                                                                                                        |

### 0.5 Mockup index (`desginphotos/`)

| File                            | Screen                                                                | Status                                                       |
| ------------------------------- | --------------------------------------------------------------------- | ------------------------------------------------------------ |
| `photo_2026-09-15_18-13-24.jpg` | Admin Dashboard — `/dashboard`, `pages/Dashboard.vue`                 | Built. Header + sidebar **approved and locked** (2026-09-15) |
| `photo_2026-09-15_18-11-40.jpg` | Manage Employees                                                      | Built: `/employees`, `pages/admin/Employees.vue`             |
| `photo_2026-09-15_18-11-28.jpg` | Lessons & Content (`photo_2026-09-15_17-53-24.jpg` is the same image) | Built: `/lessons-content`, `pages/admin/LessonsContent.vue`  |
| `photo_2026-09-15_18-11-10.jpg` | AI Role-play Scenarios                                                | Built: `/ai-scenarios`, `pages/admin/AiScenarios.vue`        |
| `photo_2026-09-15_18-11-15.jpg` | Pre-test & Post-test                                                  | Not built                                                    |
| `photo_2026-09-15_18-11-20.jpg` | Reports & Export                                                      | Built: `/reports-export`, `pages/admin/ReportsExport.vue`    |

Hotels (`/hotels`), Departments (`/departments`) and Messages & Reminders (`/messages-reminders`) are also built under `pages/admin/`, but no mockup for them is in this folder. Ask the client for theirs before changing their layout further.

The client's other screens (manager dashboard, employee journey, test runner…) are not in the repo yet — ask for the mockup before building any of them; do not invent their look.

### 0.6 Asset map

| Public file                                                                                                                     | From (`desgin/assets/`)                                     | Used by                                                                                                                                                                                                 |
| ------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/brand/ghasido-mark.png`, `/brand/ghasido-tagline.png`                                                                         | `guesvia-logo-original.png` (client artwork, unchanged)     | `AppLogo` (full lockup = mark + wordmark + tagline; collapsed rail/mobile = mark only)                                                                                                                  |
| `/brand/ghasido-wordmark.png`                                                                                                   | `ghasido-wordmark-generated.png` (not client-supplied)      | `AppLogo`. Product renamed Guesvia → GHASIDO on 2026-09-25 at the user's request; the wordmark is Poppins Bold in the logo's blue, rendered by us. Replace it when the client sends a GHASIDO logo       |
| `/brand/ghasido-logo.png`                                                                                                       | lockup with the generated wordmark swapped in               | Full lockup as one image, for surfaces outside the app shell (landing, checkout, 403)                                                                                                                   |
| `/favicon.ico`, `/favicon-32x32.png`, `/favicon-192x192.png`, `/apple-touch-icon.png`                                           | the palm mark from the lockup                               | `app.blade.php`, `errors/403.blade.php`. The old `/brand/guesvia-*.png` files are kept only for stored content that may still point at them                                                             |
| `/decor/palm-island-tagline.png`                                                                                                | `palm-island-tagline-original.png`                          | Sidebar footer                                                                                                                                                                                          |
| `/decor/better-communication-header.png` (lines laid out as the dashboard mockup draws them), `/decor/better-communication.png` | `better-communication-original.png`                         | `ScriptAccent`                                                                                                                                                                                          |
| `/decor/script-real-situations.png`                                                                                             | `script-real-situations-original.png`                       | Topbar centre tagline in the Lessons & Content mockup (`AppTopbar`)                                                                                                                                     |
| `/decor/ai-scenarios-mockup.jpg`, `/decor/lessons-content-mockup.jpg`, `/decor/reports-export-mockup.jpg`                       | _none: copies of the mockup screenshots in `desginphotos/`_ | Cropped into the AI Scenarios, Lessons & Content and Reports & Export screens. Not client originals, so they break §0.2 rule 6 and show bits of mockup interface; replace them with the client's photos |

---

## 1. The product in one page

A hotel buys a subscription for a fixed number of employee seats **per department** (SUB-01). The hotel's HR/Manager creates accounts inside that quota (SUB-02). Each employee logs in with **username + password** (AUTH-01), takes a **Pre-test**, works through lessons built for their own department, then takes a **Post-test** and downloads a **certificate**. The Super Admin (the client) builds all content in a no-code CMS with a drag-and-drop builder, AI assistance and text-to-speech, and monitors every hotel.

**The platform is also the data-collection instrument for the client's PhD research.** Answer-level capture and export are hard requirements. _A feature that works perfectly for the learner but loses per-answer data is a defect._

### Core principles, as build rules

| #   | Principle                         | Rule                                                                                                                                                                                                                                                   |
| --- | --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | Mobile first                      | Employees are hotel staff on phones. Design and verify at **390px first**. A desktop-only layout is a defect (RESP-01).                                                                                                                                |
| 2   | Low English level                 | Every learning screen leads with image + audio + one short sentence. Never a wall of English (LESSON-06).                                                                                                                                              |
| 3   | English primary, Arabic on demand | Arabic is **never** rendered until the user taps Show Meaning (CTRL-01, CTRL-02, I18N-01).                                                                                                                                                             |
| 4   | Nothing is ever lost              | Persist server-side on every step completion and every answer — never only at the end (PROG-01, PROG-03, PROG-04).                                                                                                                                     |
| 5   | The admin needs no developer      | Block set and order, question types, criteria, attempt counts, timers, completion conditions, result visibility, certificate template — all DB rows editable in the CMS. Hard-coding any of them is a defect (ADM-02, CMS-_, BLD-_, TEST-04, CERT-05). |
| 6   | Adult, professional tone          | Lively, animated, colourful. Never childish, no mascots (UX-02, UX-03).                                                                                                                                                                                |

### The two hierarchies

```
Super Admin (platform owner)
└── Hotel                  (contract period, per-department seat quotas, settings)
    └── Department         (Reception / F&B / Housekeeping / Marketing-Commercial / …)
        └── Employee       (bound to exactly one hotel AND one department — ORG-03)
```

```
Hotel (or global library) → Department → Course / Unit → Lesson → Block (= one employee step)
```

Departments are **data, not an enum** (ORG-02). Content is assigned to a department and optionally scoped to one hotel; `hotel_id IS NULL` means shared across hotels (ORG-04, CMS-04).

### Roles

| Capability                                           | Super Admin                                  | Hotel Manager / HR                                                    | Employee          |
| ---------------------------------------------------- | -------------------------------------------- | --------------------------------------------------------------------- | ----------------- |
| Create/edit/deactivate accounts                      | Any, platform-wide                           | Own hotel's employees, within quota (SUB-02)                          | No                |
| Manage hotels, departments, quotas, contract periods | Yes                                          | No                                                                    | No                |
| Build/publish content, tests, AI scenarios           | Yes                                          | **No**                                                                | No                |
| Set AI usage limits                                  | Per employee and per hotel                   | No                                                                    | No                |
| Dashboards & reports                                 | All hotels                                   | Own hotel only                                                        | Own progress only |
| Full AI transcripts & recordings                     | Yes                                          | **No** — aggregates only unless explicitly granted (ROLE-04, PRIV-04) | Own attempts      |
| Export data                                          | Everything, incl. anonymised research export | Own hotel, **without** full transcripts (REP-08)                      | No                |
| Reset an employee's progress                         | Yes, audit-logged (PROG-06)                  | **No**                                                                | No                |
| Send reminders                                       | Anyone + automatic rules                     | Own employees only (REM-07)                                           | No                |

**Negative permissions are the security-relevant ones. Enforce them server-side in policies and query scopes, never by hiding UI** (ROLE-01, SEC-01):

- An employee must not reach another department's or hotel's content, **including by typing a URL or calling an endpoint directly** (ROLE-02).
- A Manager must not read another hotel's data, edit any content, or open full transcripts/recordings.
- A Manager must not exceed a seat quota or extend a contract period. Lowering a quota below current usage blocks new accounts; it never deletes existing ones (SUB-03).
- Super Admin overrides all hotel-level permissions (ROLE-03).
- **Write a feature test per negative rule.**

### The employee journey and its two hard gates

```
Login (username + password)
   ↓
First login only: email + reminder consent + research notice   (AUTH-04, AUTH-05, PRIV-01)
   ↓
PRE-TEST                    ← GATE 1
   ↓
My Training (courses for my department only)
   ↓
Lesson → Lesson → …         (progress saved continuously — PROG-03)
   ↓
POST-TEST                   ← GATE 2
   ↓
CERTIFICATE (PDF download)
```

- **Gate 1 (JOURNEY-01):** no lesson starts until the Pre-test is completed. Lessons render **locked** _and_ the endpoints reject the request server-side. A locked-looking card with a reachable route is a bug.
- **Gate 2:** the Post-test unlocks on the admin-defined completion condition (JOURNEY-04); the certificate on its own condition, which may require a minimum Post-test score (JOURNEY-05, CERT-04). Both are stored configuration, never constants.
- Test result visibility is an admin setting — score only, score + breakdown, or hidden entirely (TEST-04). **Never assume results are shown.**
- Home always offers "Continue where you left off" as the primary action (JOURNEY-02, PROG-05).

### Lesson step model

Default block order — a seed template, **not** a hard-coded pipeline:

```
Situation → Vocabulary → Useful Expressions → Listen & Repeat → Dialogue
→ Video → Practice → AI Role-play → Lesson Complete
```

- **Each block is its own step/page** (LESSON-01). Never render a lesson as one long scroll.
- The admin chooses which blocks a lesson has and in what order (LESSON-02, BLD-07). The renderer is a `type → component` map driven by stored block rows.
- Always show "Step 3 of 7" (LESSON-03), Next/Back, and the ability to revisit a completed step (LESSON-04).
- Other block types: Text, Note, Image, Audio, Email Activity, Phone Activity (CMS-02, BLD-02). Writing tasks are insertable **anywhere** — lessons, practice, Pre-test, Post-test (WRITE-05).

### The four universal controls and the Show Meaning contract

| Control         | Behaviour                                          | Rule                                                                           |
| --------------- | -------------------------------------------------- | ------------------------------------------------------------------------------ |
| 🔊 Normal       | Play at normal speed                               | Plays a **pre-generated stored file**, never a live TTS call (CTRL-05, TTS-02) |
| 🐢 Slow         | Play the slowed version                            | Its own stored file (`audio_slow`) (TTS-01)                                    |
| ⭐ Save         | Add to My Phrasebook                               | Available from anywhere (PHRASE-02)                                            |
| 🌐 Show Meaning | Reveal Arabic + simple explanation + hotel example | See below                                                                      |

1. English is the base layer and is always visible (CTRL-01).
2. Arabic renders **only after an explicit tap** and is re-hideable (CTRL-02). Never ship it expanded.
3. It is a **reusable capability**, switchable per item from the CMS, usable on vocabulary, expressions, examples, dialogue lines, activity instructions and role-play briefings (CTRL-03, PRAC-06, RP-02). Build it once, not per screen.
4. It is **disabled during Pre-test and Post-test** (CTRL-04, TEST-03). Harden beyond the letter: **do not put the Arabic text in the Inertia payload of a test page at all** — in an SPA, "disabled" is otherwise only a UI state.
5. Audio controls need large thumb-friendly targets (CTRL-06, ACC-03).

### Invariants that must never be broken

1. **Progress is server-side truth, saved continuously** — every step completion and answer, never only at lesson end; survives device switching and dropped connections (PROG-01/03/04, AUTH-09). Browser storage is a cache at most.
2. **Never store only a score.** Store the actual answer to every question, for practice and both tests (TEST-06, DATA-01, PRAC-04).
3. **Store the artefacts:** audio recordings playable/downloadable from admin (TEST-07, DATA-02); written answers **verbatim** with the AI evaluation attached (TEST-08, DATA-03); **full** role-play transcripts for every attempt (RP-11, DATA-05).
4. **AI evaluations are structured data** — criterion → {score, comment} — not a free-text blob, so they export to long format (AIE-04). Admin can override a score (AIE-05).
5. **Time taken is always recorded**, even with no timer configured (TIME-04, DATA-08).
6. **Timers are server-timestamped** and survive a refresh. A client-only countdown is a defect (TIME-05).
7. **Content is versioned.** An answer records the version of the item it referred to (DATA-11, TEST-09). Never mutate a version that already has attempts.
8. **Data is never auto-deleted.** Deactivation and contract expiry block access and preserve everything (AUTH-08, SUB-05, DATA-10).
9. **Only the Super Admin resets progress, and it is logged** (PROG-06).
10. **AI output is never auto-published** — it lands as an editable draft with a Regenerate action (GEN-03, GEN-04). Generated audio is previewable, regenerable, replaceable by upload (TTS-03).
11. **Full AI transcripts and recordings are Super Admin only** (ROLE-04, PRIV-04).
12. **Reminders go only to consenting employees**; consent is recorded, dated and revocable (REM-05, PRIV-02, AUTH-06).
13. **Role-play evaluation rewards communicative success**, not grammatical perfection; tone is encouraging and adult (RP-08, RP-09).
14. **One attempt = one complete conversation**, not one message; 3 attempts by default, configurable (RP-05, RP-06).
15. **Exports are long format** — one row per answer — plus an anonymised participant-code variant (REP-06, REP-07).
16. **AI/TTS provider, model, keys and endpoints are configuration**, server-side only, swappable without a code change; usage metered and visible (API-02/03/04, SEC-03).

---

## 2. Stack reality — read before copying anything from the spec folders

The repo started as the **Laravel Vue starter kit**. Besides auth and settings it now has the Admin Dashboard and seven admin screens, built UI first: their controllers return hardcoded sample data. No domain model, policy or domain migration exists yet; those are yours to create.

`desgin/14-laravel-vue-setup.md` §14.1 describes a **different project** and is historical — skip it. Take component _names_ from §14.2 and nothing else.

| The docs say                                                                                                         | Reality in this repo                                                                                                                       | What to do                                                                                                                        |
| -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| Laravel 11                                                                                                           | **Laravel ^13.17, PHP ^8.3**                                                                                                               | L13 idioms: `#[Fillable([...])]` / `#[Hidden([...])]` class attributes + a `casts()` method. No `$fillable`/`$hidden` properties. |
| Tailwind v3, `tailwind.config.js`, `@tailwind base/…`                                                                | **Tailwind v4** via `@tailwindcss/vite`, CSS-first. **No config file exists and none may be created.**                                     | Translate `desgin/tailwind.config.js` + `desgin/tokens.css` into an `@theme` block in `resources/css/app.css`.                    |
| `npx tailwindcss init -p`                                                                                            | Tailwind is a Vite plugin                                                                                                                  | Never run it. There is no PostCSS config.                                                                                         |
| `lucide-vue-next`                                                                                                    | **`@lucide/vue`** is installed                                                                                                             | `import { Languages } from '@lucide/vue';` Never install `lucide-vue-next`.                                                       |
| `composer require laravel/sanctum`                                                                                   | **Fortify** + `@laravel/passkeys` + 2FA, views rendered as Inertia pages in `app/Providers/FortifyServiceProvider.php`                     | Extend `app/Actions/Fortify/`. Username login means changing `config/fortify.php`, not adding Sanctum.                            |
| `resources/js/Layouts\|Components\|Pages` (PascalCase dirs)                                                          | Lowercase `layouts/ components/ pages/ composables/ lib/ types/`                                                                           | Lowercase directories, `PascalCase.vue` files, `Inertia::render('employee/Lesson')`.                                              |
| Build `BaseButton`, `Modal`, `Tabs`, `Tooltip`… from scratch                                                         | **shadcn-vue already ships 22 primitives** on reka-ui                                                                                      | Re-point the shadcn tokens to Guesvia values; build only the Guesvia-specific components.                                         |
| `@tailwindcss/forms`, `@tailwindcss/typography`                                                                      | Not installed                                                                                                                              | Style inputs with token utilities (`desgin/11-components.md` §11.4).                                                              |
| `chart.js`, `vue-chartjs`, `vuedraggable`                                                                            | Not installed                                                                                                                              | Hand-roll the donut and bar chart as inline SVG. Drag-and-drop: native pointer events first; propose a library before installing. |
| `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `intervention/image`, `laravel/horizon` | None installed                                                                                                                             | Three fixed roles fit a `role` enum + Policies. CSV export can be a streamed response. **Ask before adding any of these.**        |
| Google-Fonts `@import` in `tokens.css`                                                                               | Fonts load via `bunny(...)` in `vite.config.ts`: Inter, Poppins, Caveat, Cairo                                                             | Keep all four in the `fonts: [bunny(…)]` array. No CDN `<link>`, no CSS `@import`.                                                |
| Light-only palette                                                                                                   | Starter ships a **working dark mode** (`HandleAppearance`, `appearance` cookie, `useAppearance`, `AppearanceTabs`, `@custom-variant dark`) | See §3 Dark mode — open decision. Do not rip the plumbing out.                                                                    |
| The mockups                                                                                                          | UX-08 called them a reference, not pixels                                                                                                  | **Superseded (client decision 2026-09-15): the mockups in `desginphotos/` are the pixel-exact specification — follow §0.**        |

**Installed and available — reach for these first:** `@vueuse/core`, `vue-sonner`, `vue-input-otp`, `reka-ui`, `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `@laravel/passkeys`, `laravel/wayfinder`.

---

## 3. Design System (Tailwind v4)

Guesvia is a bright SaaS dashboard: very light blue page, white cards with a 1px light-blue border **and** a soft blue-tinted shadow, 14px radii, one vivid royal blue for every action, pastel-tinted icon chips for colour variety, a handwritten script accent, and a hospitality photo fading into white in every header. Adult and professional — lively and animated, never childish; the learners are working hotel staff (UX-02, UX-03).

### Translating `desgin/` into this repo — read before touching CSS

`desgin/tailwind.config.js` and `desgin/tokens.css` are **Tailwind v3 artefacts**. This repo is Tailwind v4, CSS-first (`package.json` pins `tailwindcss: ^4.1.1`; `node_modules` currently resolves 4.3.3).

| Do not                                                                                                                    | Do instead                                                                                                                                                                                                                    |
| ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Create `tailwind.config.js` or run `npx tailwindcss init`                                                                 | Add an `@theme` block to `resources/css/app.css`. No config file exists and none must be created. (`components.json` names `tailwind.config.js` and `vite.config.ts` lint-ignores it — both are dead references; leave them.) |
| Copy `desgin/tokens.css` into `resources/css/` and `@import` it                                                           | Transcribe its `:root` values into `@theme` so they become **real utilities**, not just custom properties                                                                                                                     |
| Keep `tokens.css`'s `@import url('https://fonts.googleapis.com/…')`, or `@tailwind base/components/utilities` (per §14.1) | Register the families in the `fonts:` list in `vite.config.ts` (see Typography); `@import 'tailwindcss';` is already line 1 of `app.css`                                                                                      |
| `require('@tailwindcss/forms')` / `@tailwindcss/typography`                                                               | Neither is installed. Style form controls with the existing `ui/input`, `ui/label`, `ui/select`, `ui/checkbox`.                                                                                                               |
| Port `.g-card` / `.g-btn` / `.g-pill` / `.g-chip` as global CSS classes                                                   | Rebuild them as Vue components with utility classes (`cva` for variants), matching the recipes below                                                                                                                          |
| `chart.js` / `vue-chartjs` / `vuedraggable` (per §14.1)                                                                   | None installed. Donuts, progress bars and bar charts are hand-rolled inline SVG; drag & drop uses native HTML5 DnD or `@vueuse/core`.                                                                                         |

`14-laravel-vue-setup.md` §14.1 is wrong for this repo (Laravel 11, Sanctum, `npx tailwindcss init`, `lucide-vue-next`), and its §14.3–14.5 examples are wrong too (`<script setup>` without `lang="ts"`, `lucide-vue-next` imports). Use §14.2 **only** as a component-naming reference — its `Layouts/ Components/ Pages/` directories contradict this repo's lowercase `layouts/ components/ pages/`. Also: `desgin/README.md` and `12-assets.md` point at `assets/logo-guesvia.svg`; that SVG is only a reconstruction and sits at `desgin/` root. `desgin/assets/` now holds the client's **real** image originals — use those (§0.6).

### The Guesvia `@theme` block

Append this **immediately after** the existing `@theme inline { … }` block in `resources/css/app.css`. Namespace decides the utility: `--color-*` → `bg-/text-/border-/ring-`, `--font-*` → `font-*`, `--text-*` → `text-*` (+ `--line-height` / `--font-weight` / `--letter-spacing` companions), `--radius-*` → `rounded-*`, `--shadow-*` → `shadow-*`, `--ease-*` → `ease-*`, `--spacing-*` → `w-/h-/p-/m-/gap-*`, `--container-*` → `max-w-*`, `--background-image-*` → `bg-*`, `--animate-*` → `animate-*`.

```css
@theme {
    /* Brand blue — the only action colour */
    --color-brand-50: #eff5ff;
    --color-brand-100: #dbe8ff;
    --color-brand-200: #bdd5ff;
    --color-brand-300: #8fb7ff;
    --color-brand-400: #5a92ff;
    --color-brand-500: #2b6cff;
    --color-brand-600: #0b5cff;
    --color-brand-700: #1249c9;
    --color-brand-800: #0f3a9e;
    --color-brand-900: #0c2e7a;
    /* Semantic — base / tint (chip + pill bg) / text (pill label) */
    --color-success: #2fbe7a;
    --color-success-tint: #e7f8f0;
    --color-success-text: #1b7f4f;
    --color-warning: #f5a623;
    --color-warning-tint: #fef3e2;
    --color-warning-text: #8a5a06;
    --color-danger: #ef4444;
    --color-danger-tint: #fdecec;
    --color-danger-text: #b42318;
    --color-ai: #8b6bf7;
    --color-ai-tint: #f1ecfe;
    /* The design system calls this "Teal"; named `aqua` so it never sits next to Tailwind's built in teal scale (§0.2 rule 3) */
    --color-aqua: #17b8c6;
    --color-aqua-tint: #e4f7fa;
    --color-gold: #f9c338;
    --color-gold-tint: #fef6dc;
    --color-excel: #1e8e5a;
    --color-excel-tint: #e6f5ee;
    /* Surfaces & ink */
    --color-app: #f4f9fe;
    --color-app-alt: #eff7fd;
    --color-surface: #ffffff;
    --color-line: #e4edf8;
    --color-line-strong: #cbdcf0;
    --color-ink: #12233d;
    --color-ink-muted: #64748b;
    --color-ink-faint: #94a3b8;
    /* One-off mockup neutrals absent from tokens.css — see note below */
    --color-tint-header: #f6f9fd;
    --color-tint-track: #e9f0f8;
    --color-tint-grid: #eef3f9;
    --color-tint-step: #e2e9f3;
    --color-tint-note: #fff8e1;
    /* Gradients -> bg-grad-page / bg-grad-brand / bg-grad-header */
    --background-image-grad-page: linear-gradient(
        180deg,
        #f7fbff 0%,
        #eaf3fd 100%
    );
    --background-image-grad-brand: linear-gradient(
        135deg,
        #0b5cff 0%,
        #1249c9 100%
    );
    --background-image-grad-header: linear-gradient(
        90deg,
        #ffffff 55%,
        rgb(255 255 255 / 0) 100%
    );
    /* Families — registered in vite.config.ts, see Typography */
    --font-heading: Poppins, ui-sans-serif, system-ui, sans-serif;
    --font-script: Caveat, cursive;
    --font-arabic: Cairo, Tajawal, ui-sans-serif, sans-serif;
    /* Type scale */
    --text-display: 2.125rem;
    --text-display--line-height: 1.2;
    --text-display--font-weight: 700;
    --text-h1: 1.75rem;
    --text-h1--line-height: 1.25;
    --text-h1--font-weight: 700;
    --text-h2: 1.25rem;
    --text-h2--line-height: 1.3;
    --text-h2--font-weight: 600;
    --text-h3: 1rem;
    --text-h3--line-height: 1.4;
    --text-h3--font-weight: 600;
    --text-stat: 1.875rem;
    --text-stat--line-height: 1.1;
    --text-stat--font-weight: 700;
    --text-body: 0.875rem;
    --text-body--line-height: 1.6;
    --text-body-sm: 0.8125rem;
    --text-body-sm--line-height: 1.5;
    --text-label: 0.75rem;
    --text-label--line-height: 1.4;
    --text-label--font-weight: 600;
    --text-label--letter-spacing: 0.02em;
    --text-pill: 0.75rem;
    --text-pill--line-height: 1;
    --text-pill--font-weight: 600;
    --text-script: 1.375rem;
    --text-script--line-height: 1.2;
    --text-script--font-weight: 700;
    /* Radii — supersede the shadcn calc() ladder, see below */
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
    --radius-xl: 20px;
    --radius-pill: 999px;
    /* Elevation — blue-tinted, never grey-black */
    --shadow-card:
        0 1px 2px rgb(18 35 61 / 0.04), 0 4px 16px rgb(11 92 255 / 0.06);
    --shadow-hover:
        0 4px 8px rgb(18 35 61 / 0.06), 0 12px 28px rgb(11 92 255 / 0.1);
    --shadow-pop: 0 12px 40px rgb(18 35 61 / 0.16);
    --shadow-btn: 0 2px 8px rgb(11 92 255 / 0.28);
    /* Motion — this REDEFINES Tailwind's built-in ease-out (was cubic-bezier(0,0,.2,1)) */
    --ease-out: cubic-bezier(0.22, 0.61, 0.36, 1);
    --animate-fade-up: fade-up 220ms var(--ease-out) both;
    --animate-shake: shake 260ms var(--ease-out);
    --animate-pulse-ring: pulse-ring 1.4s infinite;
    @keyframes fade-up {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }
    @keyframes shake {
        0%,
        100% {
            transform: translateX(0);
        }
        25% {
            transform: translateX(-6px);
        }
        75% {
            transform: translateX(6px);
        }
    }
    @keyframes pulse-ring {
        0%,
        100% {
            box-shadow: 0 0 0 0 rgb(11 92 255 / 0.35);
        }
        50% {
            box-shadow: 0 0 0 10px rgb(11 92 255 / 0);
        }
    }
    /* Layout */
    --spacing-sidebar: 200px;
    --spacing-rail: 72px;
    --spacing-topbar: 72px;
    --spacing-bottomnav: 64px;
    --container-content: 1400px;
}
```

- **Gradients belong in `--background-image-*`, not plain `:root` vars** — `bg-grad-brand` compiles to `background-image: var(--background-image-grad-brand)`; a plain var would force `bg-[image:var(--grad-brand)]` at every call site. A plain `@theme` (not `@theme inline`) also emits every token into `:root`, so `var(--color-brand-300)` is usable for SVG `stroke`/`fill` in hand-rolled donuts, charts and inline SVG icons.
- The live block in `resources/css/app.css` is the source of truth and holds more than this listing: `azure` / `azure-tint`, `sunset`, `tint-donut`, and the three ink tokens sampled from the approved dashboard (`ink-royal`, `ink-indigo`, `ink-slate`, §0.4).
- `tint-header` = table header row, `tint-track` = progress-bar track, `tint-donut` = donut track (`#edf2f9`), `tint-grid` = chart gridlines + guest dialogue bubble, `tint-step` = upcoming step circle, `tint-note` = sticky note. These are measured in `11-components.md` but missing from `tokens.css` — tokenise them, never inline the hex.
- **Never use the same suffix in both `--color-*` and `--text-*`.** With `--color-body` and `--text-body` both defined, `text-body` compiles to `color:` only and the font-size is silently dropped.
- `--ease-out` overrides Tailwind's default easing app-wide. Four starter files use `ease-out` (`components/TextLink.vue`, `pages/settings/Profile.vue`, `pages/auth/TwoFactorChallenge.vue` ×2) and will pick up the Guesvia curve; `ui/` uses only `ease-linear`/`ease-in-out` and is unaffected. Accept that, or name it `--ease-brand` and leave `ease-out` alone.
- `10-design-system.md` gives Success as `#2FBE7A` "(sampled `#3DC07A`)" and AI as `#8B6BF7` "(sampled `#9670FB`)". Use the primary values — `tokens.css`, `tailwind.config.js` and the master prompt in `13-design-prompts.md` all agree on them; the sampled variants appear nowhere else.

### Coexisting with the shadcn semantic layer

**Rule: remap the shadcn tokens to Guesvia values so every generated `ui/` component inherits the brand for free; keep the Guesvia-named tokens alongside for what shadcn has no concept of** (brand ramp, tint/text triples, script + Arabic fonts, `rounded-pill`, blue shadows, gradients). Never restyle a `ui/` component per page — `resources/js/components/ui/*` is excluded from lint **and** fmt in `vite.config.ts`; treat it as vendored. Edit the `:root` block in `app.css`:

| shadcn var                                                                                                | Guesvia value                                                  | Why                                                                                                                                                                             |
| --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--background`                                                                                            | `#f4f9fe`                                                      | app background, never pure grey                                                                                                                                                 |
| `--foreground`, `--card-foreground`, `--popover-foreground`                                               | `#12233d`                                                      | ink                                                                                                                                                                             |
| `--card`, `--popover`, `--sidebar-background`, `--sidebar`                                                | `#ffffff`                                                      | surface. Only `--sidebar-background` is wired (`--color-sidebar: var(--sidebar-background)`); `--sidebar` is declared but unused — set both so a shadcn regen can't regress it. |
| `--primary` / `--primary-foreground`                                                                      | `#0b5cff` / `#ffffff`                                          | every filled button                                                                                                                                                             |
| `--secondary` / `--secondary-foreground`                                                                  | `#eff5ff` / `#1249c9`                                          | soft brand chip. Judgement call: the design's "Secondary" button (white + 1px border) maps to shadcn's `variant="outline"`, not `variant="secondary"`.                          |
| `--muted` / `--muted-foreground`                                                                          | `#eff7fd` / `#64748b`                                          | section bands, table zebra, meta text                                                                                                                                           |
| `--accent` / `--accent-foreground`                                                                        | `#eff5ff` / `#0b5cff`                                          | hover + active nav pill                                                                                                                                                         |
| `--destructive` / `--destructive-foreground`                                                              | `#ef4444` / `#ffffff`                                          |                                                                                                                                                                                 |
| `--border`, `--input`                                                                                     | `#e4edf8`                                                      | line colour                                                                                                                                                                     |
| `--ring`                                                                                                  | `#0b5cff`                                                      | brand focus ring                                                                                                                                                                |
| `--chart-1…5`                                                                                             | `#0b5cff`, `#5a92ff`, `#8b6bf7`, `#17b8c6`, `#f5a623`          | derived. `11-components.md` §11.3 only fixes grouped Pre/Post bars as `brand-400` (pre) + `brand-700` (post) and the AI chart as `#8b6bf7`.                                     |
| `--sidebar-primary`/`-foreground`, `--sidebar-accent`/`-foreground`, `--sidebar-border`, `--sidebar-ring` | `#0b5cff`/`#ffffff`, `#eff5ff`/`#0b5cff`, `#e4edf8`, `#0b5cff` | accent pair is the active nav item                                                                                                                                              |
| `--radius`                                                                                                | `0.875rem`                                                     | 14px; `ui/sonner` passes it straight to `--border-radius`                                                                                                                       |

Also in `app.css`:

- Delete `--radius-lg/md/sm: calc(var(--radius) …)` from `@theme inline` — the Guesvia ladder replaces them (6/10/14, not shadcn's −2px steps). The later `@theme` wins either way, but dead lines invite confusion.
- Set `--font-sans: Inter, ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', …` in `@theme inline` **and** update the duplicate `--font-sans` inside `@layer utilities { body, html }` — that one overrides the theme on the body, so missing it leaves body text on Instrument Sans.
- `@layer base` sets `body { @apply bg-background text-foreground; }`. Add `background-image: var(--background-image-grad-page); background-attachment: fixed;` for the page gradient.
- The v3-compat rule in `@layer base` sets the default border colour to `var(--color-line, currentColor)`, and `resources/js/app.ts` sets `progress: { color: '#0B5CFF' }`. Keep both.
- `:root` currently uses `hsl(…)`. Hex is equally valid in v4 (the `/opacity` modifier works on both), but keep one notation per token — never `hsl(#0B5CFF)`. Leave the `.dark` block alone (see Dark mode).

### Colour usage law

| Colour                        | May be used for                                                                                                                                 | Must never be                                    |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| `brand-600` `#0B5CFF`         | every filled button, progress/bar fill, active nav pill text+icon, selected option border, completed step circle, current page in pager, links  | a page-title colour                              |
| `brand-700` `#1249C9`         | "Guesvia" wordmark, big numbers, primary-button hover, current-step circle, "Post" series bars, type chips on test questions, topbar globe icon | a resting button fill; page titles (`ink-royal`) |
| `brand-50` / `brand-100`      | active/hover nav bg, instruction strips, Show Meaning panel, employee dialogue bubble, avatar bg, chart tracks                                  | text colour                                      |
| `brand-300` / `brand-400`     | disabled primary, "Pre" series bars, secondary-button hover border                                                                              | a filled CTA                                     |
| `success` / `-tint` / `-text` | Active/Completed/Published pills, completed progress fill, check icons, donut "Completed"                                                       | a button fill                                    |
| `warning` / `-tint` / `-text` | In-progress + Draft pills, average-progress stat, attention chips                                                                               | a button fill                                    |
| `danger` / `-tint` / `-text`  | Not-started/Inactive pills, notification dot, "Needs Attention" header, **outline-only** destructive buttons (`Export PDF Report`, `Remove`)    | a filled button                                  |
| `ai` `#8B6BF7`                | AI role-play charts, AI + pre-test icon chips, AI accents                                                                                       | a button fill                                    |
| `aqua` `#17B8C6` ("Teal")     | "Completed Lessons" chip, secondary chart series                                                                                                | a button fill                                    |
| `gold` `#F9C338`              | trophy, certificate, ⭐ phrasebook icon                                                                                                         | a button fill                                    |
| `excel` `#1E8E5A`             | **the only non-brand filled button**: "Export to Excel"                                                                                         | anything else                                    |
| `app` / `app-alt`             | page bg, section bands, table zebra                                                                                                             | card bg                                          |
| `line` / `line-strong`        | card + table + input borders; `line-strong` for dropdown borders and the focus-ring base                                                        | text                                             |
| `ink` / `-muted` / `-faint`   | body text / subtitles+labels+meta / placeholders+disabled+timestamps                                                                            | page titles (use `ink-royal`)                    |

Hard prohibitions: no purple, teal, amber or gold **fills** on buttons — those colours live in chips and charts only. No pure-grey backgrounds. No black headings — page titles are `ink-royal`, nav labels and the greeting `ink-indigo`, subtitles and the role line `ink-slate` (tokens sampled from the approved mockup, §0.4). No 1px grey border without the soft blue shadow. During a test, no correct/incorrect colours and no Show Meaning (TEST-03, CTRL-04).

### Typography

`font-heading` = Poppins 500/600/700 (headings, buttons, stat numbers, nav labels) · `font-sans` = Inter 400/500/600 (default: body, tables, forms) · `font-script` = Caveat 500/700 (handwritten taglines and sticky notes **only**) · `font-arabic` = Cairo 400/600, fallback Tajawal (Show Meaning panels, Arabic UI mode).

| Token          | Size / LH                  | Weight | Family  | Use                              |
| -------------- | -------------------------- | ------ | ------- | -------------------------------- |
| `text-display` | 34px / 1.2                 | 700    | Poppins | "Welcome, Mr. Ben Ali!"          |
| `text-h1`      | 28px / 1.25                | 700    | Poppins | page titles                      |
| `text-h2`      | 20px / 1.3                 | 600    | Poppins | card titles                      |
| `text-h3`      | 16px / 1.4                 | 600    | Poppins | sub-card titles, question prompt |
| `text-stat`    | 30px / 1.1                 | 700    | Poppins | stat-card numbers                |
| `text-body`    | 14px / 1.6                 | 400    | Inter   | paragraphs, table cells          |
| `text-body-sm` | 13px / 1.5                 | 400    | Inter   | meta, timestamps, helper text    |
| `text-label`   | 12px / 1.4, +0.02em        | 600    | Inter   | form labels, table headers       |
| `text-pill`    | 12px / 1                   | 600    | Inter   | status badges                    |
| `text-script`  | 22px / 1.2 (20–26 allowed) | 700    | Caveat  | taglines, sticky notes           |

- **Page titles use `PageHeader`** (Poppins Bold 28px, −0.02em, `text-ink-royal`) with its Inter 16px `text-ink-slate` subtitle — the approved values in §0.4; never black. Card titles come from `PanelCard`.
- **Employee learning content runs one step larger** than admin chrome: 16px body, 18–20px for vocabulary, expressions and dialogue lines; question prompts 18px, answer options 15–16px (LESSON-06, ACC-01).
- Arabic blocks: `<p class="font-arabic leading-[1.9]" dir="rtl" lang="ar">` — and only inside Show Meaning (CTRL-01, CTRL-02, I18N-03).
- **The four families load through `bunny(...)` in `vite.config.ts`**, with the weights and subsets below. Do **not** add a Google Fonts `@import` to CSS.

```ts
fonts: [
    bunny('Poppins', { weights: [500, 600, 700] }),
    bunny('Inter', { weights: [400, 500, 600] }),
    bunny('Caveat', { weights: [500, 700] }),
    bunny('Cairo', { weights: [400, 600], subsets: ['latin', 'arabic'] }),
],
```

`subsets` is a real `RemoteFontOptions` key (default `['latin']`), but whether Bunny serves all four families and accepts the `arabic` subset label is **unverified** — run `npm run build` and confirm `public/build/fonts-manifest.json` lists four families before relying on it. The plugin also emits `--font-poppins` / `.font-poppins`-style artefacts; ignore them and use the `@theme` families, as the starter already does.

### Spacing, radii, elevation

- 4px scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 → `p-1 … p-16`. Card padding 20px (`p-5`), 24px (`p-6`) for large panels; gap between cards `gap-4` (16px); page padding `p-6` desktop / `p-4` mobile.
- Radii: inputs `rounded-sm` (6), buttons + nav items `rounded-md` (10), cards `rounded-lg` (14), hero/modal/image frames/icon chips `rounded-xl` (20), pills/avatars `rounded-pill`. Two spec conflicts, resolved: §10.3's radius table lists icon chips under `rounded-pill` while its own prose and `tokens.css` `.g-chip` say 20px — use `rounded-xl`; §11.1 gives nav items a 12px radius that is not on the ladder — use `rounded-md`.
- **Card recipe — border AND soft blue shadow, both, never one:**
    ```html
    <div class="rounded-lg border border-line bg-surface p-5 shadow-card"></div>
    ```
    Hover variant: append `transition-[transform,box-shadow] duration-150 ease-out hover:-translate-y-0.5 hover:shadow-hover`. `shadow-pop` is for modals, dropdowns and the lifted card while dragging only.
- Stat-card icon chip: `grid size-11 place-items-center rounded-xl bg-success-tint text-success` (44px box, 22px icon in the base colour; swap the tint/base pair per metric).
- Primary button: `h-10 rounded-md bg-brand-600 px-4 font-heading text-sm font-semibold text-white shadow-btn hover:bg-brand-700 active:scale-[.97]` (`h-11` on employee screens, `h-12` for large CTAs). Focus ring on **every** interactive element: `focus-visible:border-brand-600 focus-visible:ring-3 focus-visible:ring-brand-600/15 focus-visible:outline-none`.

### Motion

| Interaction                   | Spec                                                                                            |
| ----------------------------- | ----------------------------------------------------------------------------------------------- |
| Page / lesson-step transition | 220ms fade + 12px slide-up → `animate-fade-up`                                                  |
| Card hover                    | 150ms, lift 2px, `shadow-card` → `shadow-hover`                                                 |
| Button press                  | 100ms `active:scale-[.97]`                                                                      |
| Progress bar / donut fill     | 700ms ease-out on mount, from 0                                                                 |
| Stat numbers                  | count-up over 800ms on first paint                                                              |
| Correct answer                | green flash + check icon scale-in 300ms — always icon **and** text, never colour alone (ACC-02) |
| Wrong answer                  | `animate-shake` 260ms + red border, no harsh sound                                              |
| Lesson complete               | trophy scale-in + one confetti burst ≤1.2s, once                                                |
| Audio playing                 | `animate-pulse-ring` on the speaker button                                                      |
| Loading                       | skeleton shimmer (`ui/skeleton`), never a blank card                                            |

`app.css` carries the global `@media (prefers-reduced-motion: reduce)` guard (ported from `desgin/tokens.css`). Still put `motion-reduce:animate-none motion-reduce:transition-none` on every animated element. `tw-animate-css` is installed and imported — use its `animate-in` / `fade-in` / `slide-in-from-*` utilities before writing new keyframes; anything custom goes next to `--animate-*` in `@theme`.

### Layout grid and breakpoints

- Desktop reference **1280×853**. Fixed sidebar `w-sidebar` (200px), topbar `h-topbar` (72px), content `max-w-content` (1400px) with `px-6` gutters.
- Dashboards: a row of 5–6 stat cards, then a 12-column grid (4/4/4, 5/4/3, 8/4). Admin editors are 3-column: list/tree 300px — fluid editor — preview/settings 320px.
- **Tablet 768–1279px:** sidebar collapses to a 72px icon rail (`w-rail`), stat cards wrap 3-up, 3-column editors become 2-column with the preview below.
- **Mobile <768px:** sidebar becomes a `h-bottomnav` (64px) bottom tab bar — Home / Lessons / Phrasebook / Progress / Profile, active tab `brand-600` with a 3px top indicator; topbar shrinks to 56px; every grid goes 1-column; stat cards become a horizontal scroll row; tables become stacked cards; admin editors become tabbed sections; modals become bottom sheets (`ui/sheet`); tap targets ≥44px; nothing hover-only (RESP-01, RESP-03, ACC-03).
- **Reuse `ui/sidebar` and `components/AppSidebar.vue`; do not rebuild them.** `AppSidebar.vue` already renders `<Sidebar collapsible="icon" variant="inset">`, and the `sidebar_state` cookie already round-trips; `SidebarProvider` uses `useMediaQuery('(max-width: 768px)')` — the design's breakpoint exactly. Switch `variant="inset"` → `"sidebar"` for the design's flush full-height panel.
- Widths default to `16rem` / `3rem`. Override them on the `SidebarProvider` in `resources/js/components/AppShell.vue` (never in the vendored file):
    ```html
    <SidebarProvider
        v-else
        :default-open="isOpen"
        :style="{ '--sidebar-width': '200px', '--sidebar-width-icon': '72px' }"
    ></SidebarProvider>
    ```
    `style` lands in `$attrs`, and `SidebarProvider` spreads `v-bind="$attrs"` after its own `:style`, so Vue's `mergeProps` lets yours win. If it does not take effect, fall back to `[data-slot='sidebar-wrapper'] { --sidebar-width: 200px !important; }` in `app.css`. The mobile sheet hardcodes `SIDEBAR_WIDTH_MOBILE` (18rem) and ignores both.

### Decorative identity — do not drop these

| Element                                                                                                      | Where                                                            |
| ------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------- |
| Header photo strip faded by `bg-grad-header`                                                                 | top-right of every screen; mirror the gradient for RTL (I18N-02) |
| Handwritten script accent, `font-script text-brand-700 -rotate-4`                                            | near the page title                                              |
| Sticky note: `bg-tint-note rotate-2 shadow-card font-script`                                                 | test + scenario admin screens                                    |
| `/decor/palm-island-tagline.png` — the client's artwork incl. "Hotel People. Brighter Futures.", 65% opacity | sidebar footer                                                   |
| Blurred lobby photo (~25% opacity under a white overlay); desk-sign photo ("Small Steps Brighter Careers")   | bottom-right of employee test screens / employee screens         |

Every decoration gets `pointer-events-none select-none` and is hidden below 768px (`hidden md:block`) — except the header strip, which becomes a 96px banner. Use the client's processed images from `public/brand/` and `public/decor/` (asset map §0.6; originals in `desgin/assets/`) — the reconstructed `desgin/*.svg` files are superseded wherever a client image exists. For photographs the client has not supplied yet, ask before substituting anything; the shot list in `12-assets.md` §12.3 is only for briefing the client.

### Iconography

Icons come from **`@lucide/vue`** (installed, v1.45) — except where the mockup's glyph is solid or Lucide lacks it: those are small solid SVG components in `resources/js/components/icons/` (§0.2 rule 5). `11-components.md` §11.9 and `12-assets.md` §12.1 say `lucide-vue-next` — **wrong for this repo; never install it.** Import PascalCase names: `import { LayoutDashboard, BuildingComplex } from '@lucide/vue';`

Set the defaults once rather than per icon. `setLucideProps` is a `provide()`, so call it in a layout's `<script setup>`, not in `app.ts`:

```ts
import { setLucideProps } from '@lucide/vue';
setLucideProps({ size: 20, strokeWidth: 1.75 });
```

Lucide's own default stroke is 2; override the size to 22 in stat-card chips and 18 in tables (`:size="18"`).

| Nav / action      | Export               |     | Nav / action      | Export       |
| ----------------- | -------------------- | --- | ----------------- | ------------ |
| Dashboard         | `LayoutDashboard`    |     | Phrasebook (⭐)   | `Star`       |
| Hotels            | `BuildingComplex`    |     | Progress          | `TrendingUp` |
| Departments       | `Users`              |     | Certificate       | `Award`      |
| Employees         | `User`               |     | Audio normal (🔊) | `Volume2`    |
| Lessons & Content | `BookOpen`           |     | Audio slow (🐢)   | `Turtle`     |
| AI Scenarios      | `Bot`                |     | Show Meaning (🌐) | `Languages`  |
| Pre/Post tests    | `ClipboardCheck`     |     | Timer             | `Clock`      |
| Messages          | `Mail`               |     | Mic               | `Mic`        |
| Reports           | `ChartColumn`        |     | Video             | `Video`      |
| Settings          | `Settings`           |     | Upload            | `Upload`     |
| Help              | `CircleQuestionMark` |     | Export            | `Download`   |
| Log out           | `LogOut`             |     | Send reminder     | `Send`       |

The names the design docs use are legacy aliases that still resolve: `Building2` → `BuildingComplex`, `BarChart3` → `ChartColumn`, `HelpCircle`/`CircleHelp` → `CircleQuestionMark`. Write the canonical name.

**Emoji rule:** the only emoji allowed are the four content controls — 🔊 🐢 ⭐ 🌐 — used consistently; everywhere else use the Lucide equivalent. No emoji in the chrome.

### Dark mode — conflict, and the standing decision

The starter ships a **working** appearance switcher (`HandleAppearance` middleware, `appearance` cookie, `useAppearance`, `AppearanceTabs`, `settings/Appearance`, `@custom-variant dark (&:is(.dark *))`, a full `.dark` palette, and ~84 `dark:` utilities across starter pages/components). The Guesvia design system defines **light values only** — there is no dark palette anywhere in `desgin/` and no dark-mode requirement anywhere in `system/`.

**Default decision (confirm with the client):** Guesvia ships **light-only** for now.

- Keep all the appearance plumbing intact — do not delete the middleware, cookie, composable or settings page.
- Do **not** author dark values for Guesvia tokens, and do **not** add `dark:` utilities to new Guesvia components. The starter's existing `dark:` usages stay as they are.
- Leave the `.dark` block in `app.css` untouched so auth/settings pages don't break for a user who already chose dark. `system/08-open-questions-and-suggestions.md` does not raise dark mode at all; add it as a new client question — a dark palette needs a lighter brand ramp step and dark treatments for the photo/gradient decorations.

### Screen review checklist

Adapted from `desgin/13-design-prompts.md` §13.4 — run before accepting any screen:

- [ ] **§0 verification done:** compared with the mockup at 1280×853, re-checked at 1240×698 and 390×844, remaining differences listed
- [ ] Page title and subtitle rendered by `PageHeader` (approved values, §0.4)
- [ ] Every filled button is `bg-brand-600`; the only exceptions are the green `bg-excel` export and red **outline** destructive buttons
- [ ] Cards are `rounded-lg border border-line bg-surface p-5 shadow-card` — border and shadow, both
- [ ] Stat cards use 44px tinted icon chips and the tint matches the metric's meaning
- [ ] Status pills use the exact tint/text pairs (`success`/`warning`/`danger` + `-tint` + `-text`)
- [ ] A script accent (`font-script -rotate-4`), a header photo strip, and a sidebar footer with the palm drawing + "Hotel People. Brighter Futures." are present
- [ ] Progress bars and donuts animate from zero; `motion-reduce:` variants present
- [ ] Checked at 390px: no horizontal scroll, tap targets ≥44px, tables became cards
- [ ] Arabic appears only behind Show Meaning, `dir="rtl" lang="ar" font-arabic` (CTRL-01/02)
- [ ] Nothing hover-only; `focus-visible` ring visible on every interactive element
- [ ] No `dark:` utilities; no `tailwind.config.js`; no `lucide-vue-next`; no colour/radius/shadow literal that is not a `@theme` token
- [ ] `npm run check` and `npm run types:check` pass (lint runs with `denyWarnings: true`)

---

## 4. UI Component Architecture

### Layering — three rules, no exceptions

1. **`resources/js/components/ui/` is vendored.** Generated shadcn-vue (`components.json`: `style: "new-york-v4"`, `baseColor: "neutral"`, on reka-ui), excluded from lint _and_ fmt in `vite.config.ts` — `'resources/js/components/ui/*'` appears in both `lint.ignorePatterns` and `fmt.ignorePatterns`. Consequences:
    - Its 2-space / double-quote formatting is intentional. Never reformat it.
    - **Do not hand-edit it to brand it.** Re-point the shadcn semantic variables (`--primary`, `--destructive`, `--border`, `--sidebar-*`, …) in `resources/css/app.css` and every primitive inherits the Guesvia palette. Edits to `ui/button/index.ts` are clobbered by the next CLI run.
    - New _Guesvia_ variants (`outline-brand`, `success`, `danger-outline`, 11.4) go in an app-owned `cva` under `components/common/` that imports `buttonVariants` from `@/components/ui/button` — not into the vendored `cva`.
    - Add missing primitives with the CLI: `npx shadcn-vue@latest add table tabs radio-group textarea switch progress popover`.
    - `components.json` sets `"tailwind": { "config": "tailwind.config.js" }` and **that file does not exist** (Tailwind v4 is CSS-first). Set the field to `""` before running the CLI; never create a v3 config to satisfy it. _Not empirically verified that this shadcn-vue release accepts `""` — if the CLI errors, report it, do not add the file._
    - `components.json` sets `"iconLibrary": "lucide"`, so generated files may import `lucide-vue-next`. **Rewrite every such import to `@lucide/vue` after each `add`.** Never install `lucide-vue-next`.
2. **Guesvia components are hand-written and live outside `ui/`.** Anything named in `desgin/11-components.md` is ours. A Guesvia component inside `ui/` is silently unlinted, unformatted, and at risk from the CLI.
3. **Pages compose, they do not style.** A file under `resources/js/pages/` may hold grid/flex/spacing utilities, `<Head>`, `defineOptions({ layout: { breadcrumbs: [...] } })` and component usage — nothing else. Shadows, borders, brand colours, radii, hover treatments, skeletons and empty states belong in a component. Extract any bespoke visual block before committing.

### Directory layout

`desgin/14-laravel-vue-setup.md` 14.2 proposes `Layouts/ Components/ Pages/` (PascalCase) with its own `Components/ui/` of `BaseButton`-style primitives. **Both are wrong here:** this repo's directories under `resources/js` are lowercase, and `ui/` is already shadcn's. Translate, do not copy.

```
resources/js/
├─ components/
│  ├─ ui/            shadcn-vue, generated + vendored (rule 1)
│  ├─ *.vue          existing starter shell: AppShell AppContent AppSidebar AppSidebarHeader
│  │                 AppHeader NavMain NavUser NavFooter Breadcrumbs Heading InputError
│  │                 TextLink UserInfo UserMenuContent AppLogo AppLogoIcon AppearanceTabs
│  │                 PasswordInput AlertError DeleteUser Manage*/Passkey*/TwoFactor* …
│  ├─ shell/         AppTopbar BottomNav PageHeader ScriptAccent HeaderPhoto StickyNote
│  ├─ common/        StatCard PanelCard StatusPill IconChip EmptyState ErrorState
│  ├─ data/          DataTable DataTableCard ProgressBar Donut BarChart ActivityFeed
│  │                 NeedsAttentionList FilterBar Pagination
│  ├─ learning/      StepTracker VocabCard ExpressionCard DialogueLine AudioButton
│  │                 ShowMeaning PhrasebookButton LessonCompleteCard
│  ├─ test/          TestIntroCard TestQuestionCard OptionList TestSidebar QuestionNavigator
│  │                 TimerCard SpeakingRecorder OrderingActivity WritingActivity TestResultCard
│  ├─ roleplay/      ScenarioCard GetReadyPanel ChatWindow FeedbackPanel AttemptBadge
│  └─ builder/       BlockPalette BlockCanvas BlockWrapper LayoutSwitcher ImageLibrary
│                    MediaSlot AiGenerateButton AudioGenerator
├─ composables/      existing: useAppearance useCurrentUrl useInitials useTwoFactorAuth
│                    planned:  useProgress useAudio useShowMeaning useTimer useRecorder useAi
├─ layouts/          AppLayout.vue AuthLayout.vue  + AdminLayout ManagerLayout
│  │                 EmployeeLayout TestLayout
│  ├─ app/           AppSidebarLayout.vue AppHeaderLayout.vue
│  ├─ auth/          AuthCardLayout.vue AuthSimpleLayout.vue AuthSplitLayout.vue
│  └─ settings/      Layout.vue
└─ pages/            existing: Dashboard.vue Welcome.vue auth/ settings/
                     proposed: admin/ manager/ employee/  (see note)
```

- The tree is the target shape. Built so far: part of `shell/`, `common/` and `data/`, plus `icons/` (solid SVGs, §0.2 rule 5) and `pages/admin/`. Still to come: `learning/`, `test/`, `roleplay/`, `builder/`, the `planned:` composables, the four new layouts and the `manager/` and `employee/` page trees.
- Pieces used by only one screen live in a folder named after that screen, with the screen name as the file prefix: `components/{dashboard,hotels,departments,employees,lessons,ai-scenarios,messages,reports}/` (e.g. `ai-scenarios/AiScenariosEditorPanel.vue`).
- Page names are referenced lowercase from PHP, matching the existing `Inertia::render('settings/Profile')` / `Route::inertia('settings/appearance', 'settings/Appearance')`: e.g. `Inertia::render('employee/lesson/Show', [...])`.
- The flat components are the real shell — **adapt them in place, never fork a parallel copy.** `AppSidebar.vue` gets role-aware nav lists, `AppLogo` renders the client's logo pieces from `public/brand/`, and page titles come from `shell/PageHeader.vue` (§0.4). Delete `PlaceholderPattern.vue` once real content replaces the starter dashboard.

### Build or reuse

**Already in `ui/` — import it, do not rebuild:** `alert avatar badge breadcrumb button card checkbox collapsible dialog dropdown-menu input input-otp label navigation-menu select separator sheet sidebar skeleton sonner spinner tooltip`.

| Spec component (`desgin/11-components.md`)                               | Verdict   | Build on top of                                                                                                                                      |
| ------------------------------------------------------------------------ | --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| BaseButton                                                               | **Reuse** | `ui/button` + an app-owned variant `cva` in `common/`                                                                                                |
| Inputs, Label, Select, Checkbox                                          | **Reuse** | `ui/input`, `ui/label`, `ui/select`, `ui/checkbox`                                                                                                   |
| Modal / BottomSheet                                                      | **Reuse** | `ui/dialog` desktop, `ui/sheet` `side="bottom"` mobile (11.10). `ui/sheet` has no drag handle; add one or accept the difference                      |
| Toast, spinner, OTP, alerts                                              | **Reuse** | `ui/sonner` (fed by `Inertia::flash('toast', …)`), `ui/spinner`, `ui/input-otp`, `ui/alert`                                                          |
| AppSidebar, tooltips, breadcrumbs, dividers, collapsible tree, row menus | **Reuse** | `ui/sidebar`, `ui/tooltip`, `ui/breadcrumb`, `ui/separator`, `ui/collapsible`, `ui/dropdown-menu`                                                    |
| StatCard                                                                 | Build     | `ui/card` + `ui/skeleton`                                                                                                                            |
| PanelCard                                                                | Build     | `ui/card` + `CardHeader`/`CardContent`/`CardFooter`                                                                                                  |
| StatusPill                                                               | Build     | `ui/badge` + own `cva` state map (11.3 colour table). Override the badge base: it ships `px-2 py-0.5 text-xs`; spec is 24px tall, `px-2.5`, 12px/600 |
| IconChip, EmptyState, ErrorState, ScriptAccent, HeaderPhoto, StickyNote  | Build     | plain markup. Decorations are `pointer-events-none` and hidden below 768px except the header strip (11.8)                                            |
| ProgressBar                                                              | Build     | plain `<div>` pair — 8px pill track `#E9F0F8`; do not add shadcn `progress` for this                                                                 |
| Donut, BarChart                                                          | Build     | hand-rolled inline SVG — see below                                                                                                                   |
| DataTable                                                                | Build     | add `table` via CLI for the semantic parts, compose on top                                                                                           |
| ActivityFeed                                                             | Build     | `ui/avatar` + list markup                                                                                                                            |
| NeedsAttentionList                                                       | Build     | `ui/card` + `ui/badge` + `ui/button` + `tabs` (CLI)                                                                                                  |
| FilterBar                                                                | Build     | `ui/select` + `ui/button` + `ui/sheet` for the mobile drawer                                                                                         |
| StepTracker                                                              | Build     | plain markup + `ui/tooltip`                                                                                                                          |
| VocabCard, DialogueLine, ShowMeaning                                     | Build     | `ui/card` + `ui/collapsible` for the Arabic panel (CTRL-01, CTRL-02)                                                                                 |
| AudioButton, SpeakingRecorder                                            | Build     | `ui/button` `size="icon"` + a `useAudio` / `useRecorder` composable                                                                                  |
| TestQuestionCard, OptionList                                             | Build     | `ui/card` + `radio-group` (CLI) or plain buttons; **no correctness colours while answering** (TEST-03)                                               |
| TestSidebar, TimerCard, QuestionNavigator                                | Build     | `ui/card` + `ui/button`; the timer is driven by a server start timestamp (TIME-05)                                                                   |
| OrderingActivity                                                         | Build     | see drag-and-drop below (RESP-01, ACC-03; visual spec 11.6 "Ordering")                                                                               |
| TestIntroCard, TestResultCard, LessonCompleteCard                        | Build     | `ui/card` + `ui/button`                                                                                                                              |
| ScenarioCard, ChatWindow, FeedbackPanel                                  | Build     | `ui/card`, `ui/avatar`, `ui/badge`, `ui/skeleton` for the AI in-progress state (PERF-04)                                                             |
| BlockPalette, BlockCanvas, BlockWrapper, LayoutSwitcher                  | Build     | plain markup + `ui/tooltip` + `ui/dropdown-menu` for block actions (BLD-03)                                                                          |
| ImageLibrary, MediaSlot                                                  | Build     | `ui/dialog`/`ui/sheet` + `ui/input` + `ui/skeleton` (MED-02)                                                                                         |

Some shadcn-vue primitives pull extra npm packages (reportedly `carousel`→embla, `drawer`→vaul-vue, `calendar`→@internationalized/date; not verified here). Those count as new dependencies — see the rule below.

### The two gaps: charts and drag-and-drop

`chart.js`, `vue-chartjs` and `vuedraggable` are recommended by `desgin/14-laravel-vue-setup.md` 14.1 and **none of them are installed**. Do not import them. (14.1 also names Sanctum, `@tailwindcss/forms`, `@tailwindcss/typography`, `spatie/laravel-permission` — all equally absent.)

- **Charts — hand-roll SVG. This is the default and it is sufficient.** Hand-rolled SVG must still reproduce the mockup's charts exactly — ring thickness, segment order and colours, bar widths, gridlines, legend layout (§0; UX-08's "need not be copied literally" is superseded).
    - `Donut.vue`: one `<circle>` track (`#EDF2F9`) + one arc, `stroke-width="14"`, `stroke-linecap="round"`, `stroke-dasharray` = circumference, animate `stroke-dashoffset` from full to target over 700ms ease-out on mount (10.4). Centre number + right-hand legend are plain markup.
    - `BarChart.vue`: fixed **0–100** Y axis, gridlines `#EEF3F9`, 11px `text-muted` axis labels, 10px radius on top corners only, grouped Pre/Post in `brand-400` (pre) / `brand-700` (post), AI series `#8B6BF7` (11.3).
    - Reach for a chart library only if a report needs interactive tooltips/zoom across many series — and propose it first.
- **Drag and drop — native pointer handling, with a non-drag fallback always.**
    - `BlockCanvas` and the admin course tree are desktop surfaces (RESP-02): HTML5 drag events (`draggable`, `dragstart`, `dragover`, `drop`) are fine.
    - `OrderingActivity` and any employee-facing reorder: HTML5 DnD does not fire on touch and employees are on phones (RESP-01). Use pointer events, or tap-to-select-then-tap-to-place.
    - **Every drag interaction ships a keyboard/tap alternative** (move-up/move-down buttons, tap-to-place) with ≥44px targets (ACC-03).
    - `@vueuse/core` is installed and gives `useDraggable`, `usePointer`, `useElementBounding` — it does **not** give a sortable list.
- **Rule: propose any new dependency with a reason and wait for approval.** npm packages, Composer packages, and CLI-added shadcn components that carry their own deps.

### Authoring conventions

- `<script setup lang="ts">`, 4-space indent, single quotes, semicolons, `printWidth: 80`. `npm run check` (`vp check`) runs `denyWarnings: true`, `typeAware: true` — a warning fails.
- Props typed with a local `type Props = { … }` + `defineProps<Props>()` (+ `withDefaults` for defaults). No runtime `defineProps({ … })`, no `any`.
- `cn()` from `@/lib/utils` for every conditional or merged class list; expose `class?: HTMLAttributes['class']` on any component a page may restyle.
- `cva` **only** for genuine variants. A single tone lookup is a `Record<Tone, string>`, not a `cva`.
- Icons from `@lucide/vue` (`import { Users } from '@lucide/vue';`). `desgin/11-components.md` 11.9 and `12-assets.md` say `lucide-vue-next` — **wrong for this repo.** Spec sizing: 20px default, 22px in chips, 18px in tables, 1.75px stroke. The starter sizes icons with classes (`class="h-4 w-4"`); either that or `:size`/`:stroke-width` props is acceptable, but be consistent within a component.
- Emit with `defineEmits<{ … }>()`; no `v-model` on a prop you did not declare.

**`cn()` knows the Guesvia tokens; keep it that way.** Plain `twMerge` does not know them and _silently drops classes_: `cn('text-stat', 'text-ink')` yields `text-ink` (both are parsed as text-colour), and `cn('shadow-sm', 'shadow-card')` keeps both. `resources/js/lib/utils.ts` registers the custom scales (verified against tailwind-merge 3.7.0); add any new text, font, radius or shadow token there too:

```ts
import { extendTailwindMerge } from 'tailwind-merge';

const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display',
                'h1',
                'h2',
                'h3',
                'stat',
                'body',
                'body-sm',
                'label',
                'pill',
                'script',
            ],
            radius: ['pill'],
            shadow: ['card', 'hover', 'pop', 'btn'],
            font: ['heading', 'script', 'arabic'],
        },
    },
});
```

Canonical example — `resources/js/components/common/StatCard.vue`, 14.3 rewritten for this repo:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'ai' | 'aqua';

type Props = {
    value: string | number;
    label: string;
    sub?: string;
    tone?: Tone;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), { tone: 'brand' });

const chip: Record<Tone, string> = {
    brand: 'bg-brand-50 text-brand-600',
    success: 'bg-success-tint text-success',
    warning: 'bg-warning-tint text-warning',
    danger: 'bg-danger-tint text-danger',
    ai: 'bg-ai-tint text-ai',
    aqua: 'bg-aqua-tint text-aqua',
};

const subTone: Record<Tone, string> = {
    brand: 'text-brand-600',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    ai: 'text-ai',
    aqua: 'text-aqua',
};
</script>

<template>
    <Card
        :class="
            cn(
                'min-h-24 flex-row items-center gap-4 rounded-lg p-5 shadow-card',
                'transition duration-150 hover:-translate-y-0.5 hover:shadow-hover',
                props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'grid size-11 shrink-0 place-items-center rounded-xl',
                    chip[tone],
                )
            "
        >
            <slot name="icon" />
        </div>

        <div class="min-w-0">
            <p class="font-heading text-stat text-ink">{{ value }}</p>
            <p class="truncate text-[13px] text-ink-muted">{{ label }}</p>
            <p v-if="sub" :class="cn('text-xs font-semibold', subTone[tone])">
                {{ sub }}
            </p>
        </div>
    </Card>
</template>
```

What this encodes, and what it depends on:

- `Card` ships `flex flex-col gap-6 rounded-xl border py-6 shadow-sm`. `flex-row`, `p-5` and `rounded-lg` override through `cn()`/`tailwind-merge` (verified); `rounded-lg` is **required** — without it you inherit `rounded-xl`, and the spec card is 14px, not 20px (10.3). `shadow-card` only beats `shadow-sm` once the `extendTailwindMerge` fix above is in place.
- `size-11` = 44px icon chip, `min-h-24` = 96px minimum, `p-5` = 20px padding, `gap-4` = 16px, `duration-150` + `-translate-y-0.5` = the 150ms/2px hover lift (10.3, 10.4, 11.2).
- `brand-*`, `ink`, `ink-muted`, `success-tint`, `shadow-card`, `shadow-hover`, `font-heading` and `text-stat` come from the `@theme` block of `resources/css/app.css`, a translation (never a copy) of the Tailwind v3 `desgin/tailwind.config.js`: `colors.brand.600` → `--color-brand-600`, `colors.ink.muted` → `--color-ink-muted`, `fontSize.stat` → `--text-stat` + `--text-stat--line-height` + `--text-stat--font-weight`, `boxShadow.card` → `--shadow-card`, `borderRadius.lg` → `--radius-lg`, `fontFamily.heading` → `--font-heading`.
- `app.css` defines the Guesvia radius ladder, so `rounded-lg` is 14px and `rounded-xl` is 20px. Never paper over a radius with `rounded-[14px]` in components.

### Naming

- `PascalCase.vue`, one file per component named in `desgin/11-components.md`, matching its name exactly. Directories stay lowercase.
- `index.ts` barrel **only** where a folder exports several related pieces (e.g. `components/data/index.ts` for `DataTable` + its row/cell parts). Do not barrel unrelated components.
- Props are `camelCase` in `<script>`, `kebab-case` in templates (`:show-email="true"`).
- Composables: `useThing.ts`, one per file, returning a plain object of refs/functions.

### Slots, props and required states

| Pattern       | Contract                                                                                                                                                                                                                                        |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `StatCard`    | `#icon` slot for the lucide component; `tone` picks chip + sub-line colour (11.2: employees→brand, active/completed→success, pre-test→ai, post-test→warning, lessons→aqua, inactive→danger). A mini `Donut` may be slotted into `#icon` instead |
| `PanelCard`   | `title` prop + `#header` (overrides the title row), `#actions` (the "View All →" link), default slot, `#footer`                                                                                                                                 |
| `DataTable`   | `columns` + `rows` props, `#cell-<key>` scoped slots, `#actions` per row, and a **mandatory** `#mobile-card` scoped slot — below `md` the table becomes stacked cards, never a horizontally scrolling table (11.3, RESP-01)                     |
| `ShowMeaning` | `arabic`, `explanation`, `example`; collapsed by default (CTRL-01, CTRL-02), rendered `lang="ar" dir="rtl"` (I18N-03), and **never rendered inside the test runner** (CTRL-04, TEST-03)                                                         |
| `AudioButton` | `src` + `variant: 'normal' \| 'slow'`. 11.5 specifies a 40px round button; on mobile the tap target must still reach 44px (CTRL-06, ACC-03) — pad, don't shrink                                                                                 |

**Every list, table, feed and chart ships three states — skeleton, empty, error. A blank card is a bug** (10.4: "Skeletons — shimmer while loading, never a blank card"). `ui/skeleton` for loading, `common/EmptyState.vue` (illustration + one sentence + primary action) for empty, `common/ErrorState.vue` (message + retry) for failures. Never signal state by colour alone — icon + text (ACC-02).

### The three role shells

| Layout               | Shell                                               | Nav (`desgin/11-components.md` 11.1, icons per 11.9)                                                                                                                                                                                                                                                                                                                                 |
| -------------------- | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `AdminLayout.vue`    | sidebar shell                                       | Dashboard `LayoutDashboard` · Hotels `BuildingComplex` · Departments `Users` · Employees `User` · Lessons & Content `BookOpen` · AI Scenarios `Bot` · Pre-test & Post-test `ClipboardCheck` · Messages & Reminders `Mail` · Reports & Export `ChartColumn` — divider — Settings `Settings` · Help `CircleQuestionMark` · Log out                                                     |
| `ManagerLayout.vue`  | same sidebar shell                                  | Dashboard `LayoutDashboard` · My Team `Users` · Training Progress `TrendingUp` · Test Results `ClipboardCheck` · Reminders `Send` · Messages `Mail` — divider — Help `CircleQuestionMark` · My Profile `User` · Log out                                                                                                                                                              |
| `EmployeeLayout.vue` | sidebar ≥768px, **64px bottom tab bar below 768px** | Home · My Lessons `BookOpen` · My Phrasebook `Star` · My Progress `TrendingUp` · Messages `Mail` — divider — Pre-test `ClipboardCheck` · Training · Post-test `ClipboardCheck` · Certificate `Award` — divider — Help `CircleQuestionMark` · Log out. Bottom bar carries five: Home, Lessons, Phrasebook, Progress, Profile; active tab `brand-600` with a 3px top indicator (11.10) |

- 11.9 gives no icon for Home or Training; `House` and `GraduationCap` are reasonable picks, not spec. Use the canonical export names from §3 — the design docs' `Building2` / `BarChart3` / `HelpCircle` are deprecated aliases.
- Build all three on the existing shell: inside `AppShell` sit `AppSidebar` and `AppContent` as **siblings**, with `AppSidebarHeader` inside `AppContent` (see `layouts/app/AppSidebarLayout.vue`). Extend that layout and parameterise `AppSidebar.vue` with a `NavItem[]`. Do not start a new shell and do not duplicate `AppShell`/`AppContent`.
- Nav items use the existing `NavItem` type from `@/types` (`title`, `href`, `icon?: LucideIcon`, `isActive?`) with Wayfinder route helpers as `href`. Active state comes from `useCurrentUrl()` — `isCurrentUrl(href)` for exact matches, `isCurrentOrParentUrl(href)` for section parents; `NavMain.vue` already does this.
- **Log out is not a nav link** — `logout()` from `@/routes` is a POST. Reuse the `<Link :href="logout()" as="button">` item already wired in `UserMenuContent.vue`.
- **`<Toaster />` is mounted per-layout**, currently only in `layouts/app/AppSidebarLayout.vue` and `AppHeaderLayout.vue`. Any new root layout (`TestLayout.vue`) must mount it or `Inertia::flash('toast', …)` is silently swallowed.
- Register every new layout in the `switch (true)` in `resources/js/app.ts`, before `default`:
    ```ts
    case name.startsWith('admin/'):
        return AdminLayout;
    case name.startsWith('manager/'):
        return ManagerLayout;
    case name.startsWith('employee/test/'):
        return TestLayout;
    case name.startsWith('employee/'):
        return EmployeeLayout;
    ```
    Order matters — `employee/test/` must precede `employee/`. `TestLayout.vue` is a stripped shell with no sidebar and no bottom tabs, so a learner cannot wander out of a running, timed test (TEST-03, TIME-05).
- **`auth.user.role` does not exist yet.** Shared Inertia props today are `name`, `auth.user`, `sidebarOpen` and `notifications.unread`, and `resources/js/types/auth.ts` has no `role` field (the `[key: string]: unknown` index signature will type-check `auth.user.role` as `unknown` — do not rely on that). Before writing role-aware nav: add the column in a migration, expose it from `HandleInertiaRequests::share`, and add it to the `User` type.
- Role visibility in the nav is cosmetic. Every route is still authorised server-side by policy (ROLE-01, ROLE-02, SEC-01).

---

## 5. Frontend Conventions (Inertia v3 + Vue 3 + Wayfinder)

Copy the existing starter files, not generic Inertia tutorials. `desgin/14-laravel-vue-setup.md` describes a _different_ project (Laravel 11, Tailwind v3, `app.js`, capitalised `Pages/`/`Layouts/`, `lucide-vue-next`, `vuedraggable`, `chart.js`): take component **names** from §14.2, never its structure, imports or install commands.

`npm run check` (vite-plus `vp check`, `denyWarnings: true`, `typeAware: true`) is the gate — 4-space indent, single quotes, semicolons, `printWidth: 80`, Tailwind classes auto-sorted inside `clsx`/`cn`/`cva`. `npm run types:check` runs `vue-tsc --noEmit` against `"strict": true`. Always `<script setup lang="ts">`; always `import type` for types.

### Pages and layouts

| Rule          | Detail                                                                                                                                                                                                                                                                                      |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Location      | `resources/js/pages/<area>/<Name>.vue` — directories **lowercase**, files **PascalCase**                                                                                                                                                                                                    |
| Areas         | on disk: `auth/`, `settings/`, plus root `Dashboard.vue` / `Welcome.vue`. Proposed for Guesvia: `employee/`, `admin/`, `manager/` — the lowercase form of `desgin/14-laravel-vue-setup.md` §14.2 (`Employee/`, `Admin/`, `Manager/`). **None of the three exists yet**; create on first use |
| Server render | `Inertia::render('employee/LessonStep', [...])` — the string is the path under `pages/`, lowercase area included. A page with no props uses `Route::inertia('path', 'Name')` (see `routes/web.php`)                                                                                         |
| Resolution    | automatic. `app.ts` calls `createInertiaApp` with **no `resolve` and no `setup`** (`@inertiajs/vite` resolves pages). Do not add either                                                                                                                                                     |
| Head          | all 12 existing pages render `<Head title="..." />`; `app.ts` renders it as `"<title> - <appName>"`. Keep it on every new page                                                                                                                                                              |
| a11y          | the three `settings/` pages render `<h1 class="sr-only">…</h1>`; `Dashboard.vue` does not. Add one on every new chrome page (ACC-01)                                                                                                                                                        |

Layout is chosen **centrally** in `resources/js/app.ts` — a page never imports its own layout. Current code:

```ts
layout: (name) => {
    switch (true) {
        case name === 'Welcome':
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
},
```

- To add an area: import the layout at the top of `app.ts` and insert `case name.startsWith('employee/'): return EmployeeLayout;` **before** `default`. Nested persistent layouts are an array, outermost first.
- Admin and manager pages stay on `AppLayout` unless the nav genuinely differs; the mobile-first employee shell (bottom nav, RESP-01) does need its own layout.
- `<Toaster />` (from `@/components/ui/sonner`) is mounted **only** in `layouts/app/AppSidebarLayout.vue` and `layouts/app/AppHeaderLayout.vue`. Any new shell (employee, test-taking) must render `<Toaster />` itself or flash toasts silently vanish.
- `app.ts` also sets `progress: { color: '#0B5CFF' }`, the brand-600 value (`desgin/10-design-system.md` §10.1).
- Active-nav state: `useCurrentUrl().isCurrentOrParentUrl(href)`. Href → string: `toUrl(href)` from `@/lib/utils`; class merging: `cn()` from the same file.

Pages pass props to their persistent layout through `defineOptions({ layout: { ... } })` — `breadcrumbs` for `AppLayout`, `title` / `description` for `AuthLayout` (see `pages/auth/ResetPassword.vue`):

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />
    <h1 class="sr-only">Dashboard</h1>
</template>
```

### Wayfinder

- **Never** hand-write a URL string and **never** call `route()` — Ziggy is not installed and there is no global `route()` helper.
- Named routes: `import { dashboard } from '@/routes';` / `import { edit } from '@/routes/profile';`
- Controller actions: `import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';` (default export is `{ edit, update, destroy }`), or a named import for one method: `import { destroy } from '@/actions/...';`. The path mirrors the PHP namespace exactly.
- A route helper returns `{ url, method }`; it also carries `.url(args)`, `.definition`, and one function per verb (`.get()`, `.patch()`, `.delete()`, …).
- `.form()` (enabled by `wayfinder({ formVariants: true })` in `vite.config.ts`) returns `{ action, method }` — the shape `<Form v-bind="…">` wants. For PUT/PATCH/DELETE it emits `method: 'post'` plus `?_method=PATCH`, so **never build a `_method` field by hand**.
- Args accept a bare id, a one-element array, or an object: `destroy.url(id)`, `destroy.url([id])`, `show({ lesson: lesson.id, step: step.id })`. A model-shaped `{ id }` object also works.
- `resources/js/routes/**`, `resources/js/actions/**` and `resources/js/wayfinder/**` are generated and lint-ignored in `vite.config.ts`. Never hand-edit them and never commit a fix into them.
- They regenerate on `npm run dev` / `npm run build`; with the dev server down run `php artisan wayfinder:generate --with-form` (full signature: `{--path=} {--skip-actions} {--skip-routes} {--with-form}`). A missing import means the route has not been generated — regenerate, do not fall back to a literal path.
- Programmatic visits: `router.delete(destroy.url(id), { preserveScroll: true, onError });` (see `components/ManagePasskeys.vue`).

### Forms

Default is the Inertia v3 `<Form>` component bound to a Wayfinder form variant — either a controller action (`ProfileController.update.form()`) or a named route (`update.form()` from `@/routes/password`).

```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Form
        v-bind="ProfileController.update.form()"
        :options="{ preserveScroll: true }"
        class="space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" name="name" required />
            <InputError class="mt-2" :message="errors.name" />
        </div>

        <Button :disabled="processing" data-test="update-profile-button">
            Save
        </Button>
    </Form>
</template>
```

- Inputs are **uncontrolled by default**: `name` + `:default-value`, not `v-model` (`pages/settings/Profile.vue`). Use `v-model` only when script code needs the live value — a read-only prefilled field (`auth/ResetPassword.vue`), the OTP input (`auth/TwoFactorChallenge.vue`), a dialog-local field (`PasskeyRegister.vue`).
- Slot props in use: `errors`, `processing`, `reset`, `clearErrors` (`components/DeleteUser.vue`). Attributes in use: `:options`, `:transform`, `reset-on-success` (boolean or field array), `@error`.
- Every submit/destructive button carries a stable `data-test="<verb>-<subject>-button"` (11 occurrences under `resources/js`). **No PHP test asserts on it today** — the PHPUnit feature tests never reference `data-test` — so treat it as the reserved hook for future browser tests and keep it consistent, rather than claiming tests depend on it.
- Fall back to `useForm` only when the payload is not a DOM form: multi-step wizard state accumulated across steps, drag-and-drop builder ordering (BLD-03), recorded audio blobs, optimistic/auto-save submits. **There is no `useForm` usage in the repo yet**; if you add one, still take the URL from Wayfinder.

### Server → client messaging

| Channel                                                                  | Use                                                                                                                               |
| ------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])` | one-off confirmations; `lib/flashToast.ts` listens on the router `flash` event and calls `toast[type](message)` from `vue-sonner` |
| `<Form>` `errors` slot prop                                              | **all** validation errors — never surface a validation failure as a toast                                                         |
| Page props                                                               | anything the page renders (state, results, scores)                                                                                |

`type` is constrained to `'success' | 'info' | 'warning' | 'error'` by `FlashToast` in `types/ui.ts`. Wrap messages in `__()` server-side (I18N-02).

### Shared props

`HandleInertiaRequests::share()` exposes `name`, `auth.user`, `sidebarOpen` and `notifications.unread` (the topbar bell count, hardcoded to 3 until a notifications table exists). Guesvia must add: `auth.user.role` (ROLE-01), `hotel` (id, name, days remaining — SUB-07), `department` (ORG-03), `locale` + `dir` (I18N-02), and small feature flags (AI limit reached AIL-03, pre-test gate JOURNEY-01). Keep them small — they ship on every response.

Every new shared prop goes into the `sharedPageProps` block of the `declare module '@inertiajs/core'` augmentation in `resources/js/types/global.d.ts` (that is what types `usePage()`), with its shape in `resources/js/types/`. `User` already carries an `[key: string]: unknown` index signature, so a new field must be declared explicitly to be type-safe. Consume it the existing way:

```ts
const page = usePage();
const user = computed(() => page.props.auth.user);
```

Per-page data stays in `Inertia::render(...)` props typed with `defineProps<Props>()`, never in `share()`.

### Types

- Existing: `types/auth.ts` (`User`, `Auth`, `Passkey`, `TwoFactorConfigContent`), `types/navigation.ts` (`NavItem`, `BreadcrumbItem`), `types/ui.ts` (`Appearance`, `ResolvedAppearance`, `AppVariant`, `FlashToast`) — all re-exported from `types/index.ts`. `global.d.ts` and `vue-shims.d.ts` are ambient and not re-exported.
- Add domain files and re-export them from `types/index.ts`: `types/learning.ts` (Course, Lesson, LessonStep, VocabItem, PhrasebookItem), `types/assessment.ts` (Test, Question, Attempt, Answer), `types/admin.ts` (Hotel, Department, Employee, Scenario).
- Use `type X = { … }`, not `interface` (the starter uses `type` everywhere except the `global.d.ts` module augmentations). No `any`. Export a component's prop type when another file consumes it (`export type Props = { … }`, see `ManagePasskeys.vue`).

### Composables

`resources/js/composables/useX.ts`, one concern each, exporting an explicitly typed `UseXReturn` alongside the function — every existing composable does this (`useAppearance`, `useCurrentUrl`, `useInitials`, `useTwoFactorAuth`).

Planned; none exist yet. Names normalised from `desgin/14-laravel-vue-setup.md` §14.2, which lists `useProgress useAudio useShowMeaning useTimer useRecorder useAi`:

| Composable       | Purpose                                                                                              |
| ---------------- | ---------------------------------------------------------------------------------------------------- |
| `useProgress`    | step completion + resume (PROG-03)                                                                   |
| `useAudio`       | normal/slow playback of pre-generated files (CTRL-05)                                                |
| `useShowMeaning` | per-item reveal state, forced off during tests (CTRL-02, CTRL-04)                                    |
| `useTimer`       | countdown from a **server** start timestamp (TIME-05)                                                |
| `useRecorder`    | mic permission + denial fallback (RESP-05)                                                           |
| `useAiLimit`     | attempts left / limit reached (RP-05, AIL-03); the spec names no composable — §14.2 calls it `useAi` |

`@vueuse/core` 12.8.2 is installed — do not reimplement `useMediaControls` (audio element), `useUserMedia`, `useIntervalFn` / `useTimestamp` (timers), `useOnline` / `useNetwork` (PERF-03 retry), `useIdle`, `useStorage`.

### i18n and RTL

- Learning content is always English; Arabic appears only inside a Show Meaning panel (I18N-01, CTRL-01). Render that panel `lang="ar" dir="rtl"` with the Arabic font (Cairo, fallback Tajawal — `desgin/10-design-system.md` §10.2) even when the surrounding UI is LTR (I18N-03).
- The **interface** language is separately switchable EN/AR with full RTL layout (I18N-02). New markup uses logical Tailwind utilities only: `ps-*`/`pe-*`, `ms-*`/`me-*`, `text-start`/`text-end`, `start-*`/`end-*`, `border-s`/`border-e`. Physical `pl-/pr-/ml-/mr-/left-/right-/text-left/text-right` is a defect except on a deliberately physical decoration.
- The header photo fade is `--grad-header: linear-gradient(90deg, #FFFFFF 55%, rgba(255,255,255,0) 100%)` and `desgin/10-design-system.md` §10.1 requires it "mirrored for RTL". No technique is prescribed — implement it direction-aware (a `[dir='rtl']` override, or a variable angle) instead of hard-coding `90deg`, and reuse the same approach everywhere.
- **No i18n library is installed.** Do not add one (vue-i18n, laravel-vue-i18n…) without asking first; server strings already go through `__()`. Until that decision, avoid burying user-facing strings in template literals.

### Client state

- Progress is **server truth** (PROG-01, PROG-02). Submit on each step completion and each answer (PROG-03); the server response re-renders the UI.
- Browser storage is a **short-lived offline buffer only** (PROG-04, PERF-03): queue the in-flight answer, retry when back online, clear on success. Never treat `localStorage`/`IndexedDB` as the source of truth for progress, scores, phrasebook or timers, and never compute "where you left off" client-side.
- The only current `localStorage` use is the appearance preference in `composables/useAppearance.ts`, mirrored into an `appearance` cookie so the server (`HandleAppearance`) can read it.
- Timers never trust `Date.now()` alone — the deadline comes from the server and must survive a refresh (TIME-05).

### Assets, icons, fonts

- Target `public/` layout (`desgin/12-assets.md` §12.2): `brand/`, `decor/`, `illustrations/`, `content/`. **None of those folders exists yet** — `public/` currently holds only `favicon.*`, `apple-touch-icon.png`, `robots.txt`, `index.php` and `build/`. `public/build/` is Vite output; never put source assets there.
- Client images: originals in `desgin/assets/`, processed copies in `public/brand/` and `public/decor/` (§0.6). Never keep source images in `public/build/` — builds empty it.
- `vite.config.ts` sets `transformAssetUrls: { base: null, includeAbsolute: false }`, so template URLs are **not** rewritten. Reference public files by root-relative path — `<img src="/decor/palm-island-tagline.png" alt="" />` — and `import` an asset in `<script setup>` only when you want it hashed into the bundle.
- CMS-uploaded media (`desgin/12-assets.md` §12.4 proposes `storage/app/public/content/{type}/{yyyy}/{mm}/`) is referenced by the URL the server sends in props; never concatenate `/storage/...` in Vue. Whether a `public` disk plus `storage:link` gets configured is still open — `FILESYSTEM_DISK` is `local` today.
- Icons: `import { LayoutDashboard } from '@lucide/vue';`. `desgin/12-assets.md` §12.1, `11-components.md` §11.9 and `13-design-prompts.md` all say to install `lucide-vue-next` — **ignore that**; it is not installed and must not be. The §11.9 mapping still applies, but several ids were renamed in `@lucide/vue` v1 — use the canonical table in §3 (`bar-chart-3` → `ChartColumn`, `building-2` → `BuildingComplex`, `help-circle` → `CircleQuestionMark`). Type nav icons as `LucideIcon` from `@lucide/vue` (see `types/navigation.ts`).
- Fonts: Inter, Poppins, Caveat and Cairo (with the `arabic` subset) load through the `fonts: [bunny(...)]` array inside the `laravel()` plugin, never a `<link>` tag. `resources/css/app.css` maps them to Tailwind families, and its `@layer utilities { body, html { --font-sans: … } }` override is set to Inter.

The font tokens themselves are defined once, in the `@theme` block in §3 — `--font-heading` (Poppins), `--font-script` (Caveat), `--font-arabic` (Cairo), plus `--font-sans` (Inter) in `@theme inline`. Do not declare a second, rival set here; there is **no `tailwind.config.js` in this repo** and `desgin/tailwind.config.js` must be translated into `@theme` CSS, never copied.

---

## 6. Backend Architecture & Data

### Starting point

`app/` is the Laravel Vue starter kit plus `DashboardController` and seven invokable `Admin\*Controller` classes (`routes/admin.php`) that serve hardcoded sample data to the admin screens. The domain layer is greenfield on top of it.

| Exists                                                                                                                              | Where                                                                                                  |
| ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Fortify auth (login, password reset, email verification, 2FA, passkeys)                                                             | `config/fortify.php`, `app/Providers/FortifyServiceProvider.php` (all views rendered as Inertia pages) |
| `User` — `name`, `email`, `password`, 2FA columns, passkeys relation                                                                | `app/Models/User.php`                                                                                  |
| Profile + security settings; appearance is a route-only Inertia page                                                                | `app/Http/Controllers/Settings/{ProfileController,SecurityController}.php`, `routes/settings.php`      |
| Shared validation traits                                                                                                            | `app/Concerns/PasswordValidationRules.php`, `app/Concerns/ProfileValidationRules.php`                  |
| `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `passkeys`, 2FA columns | `database/migrations/` — that is the complete list                                                     |

Absent — build all of it: hotels, departments, courses, lessons, blocks, activities, tests, AI scenarios, attempts, phrasebook, certificates, media, reminders, AI usage, audit log, and every policy for them. The admin controllers and pages that exist still need real queries in place of their sample arrays, and the manager and employee controllers and pages are all to build. There is no `app/Enums`, `app/Policies`, `app/Jobs`, `app/Services` or `app/Contracts`; create them as needed.

Stack facts that override the spec folders: **Laravel 13 (v13.31.0 installed), PHP `^8.3`, Fortify — not Laravel 11, not Sanctum, not Breeze.** `desgin/README.md` and `desgin/13-design-prompts.md` assume "Laravel 11"; `desgin/14-laravel-vue-setup.md` 14.1 tells you to `composer require laravel/sanctum` and recommends `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `intervention/image` and `laravel/horizon`. **None of those are installed.** Propose any package before adding it.

### Migration portability (read before the first migration)

`composer setup` copies `.env.example`, which ships `DB_CONNECTION=sqlite`; `.github/workflows/tests.yml` runs `composer setup` then `composer ci:check`, so **CI migrates against SQLite** while `.env` and `compose.yaml` use MySQL 8.4. Every migration must run on both.

- Use `$table->string(...)` for enumerated columns and cast to a PHP enum in the model. Do not use `$table->enum(...)`.
- `->after(...)` is a MySQL-only modifier and is silently ignored on SQLite — never rely on it for correctness.
- No `fullText()` indexes; use `LIKE` or a real search service, proposed first.
- Prefer editing `0001_01_01_000000_create_users_table.php` directly over an `->change()` migration: this app has no production data, and `->change()` on a uniquely-indexed column forces a table rebuild on SQLite.

### Auth adaptation — username login, no self-registration

The starter authenticates by email and exposes public registration. Both are wrong for Guesvia (AUTH-01, AUTH-02).

| Step                                        | Concrete change                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identifier                                  | `config/fortify.php` → `'username' => 'username'`. `Fortify::username()` is a **config reader, not a setter** (`vendor/laravel/fortify/src/Fortify.php:78` returns `config('fortify.username', 'email')`) — there is no call to make. Leave `'email' => 'email'`; that key names the password-reset _field_. Keep `'lowercase_usernames' => true`.                                                                                                                                                                                                          |
| Column                                      | Add `username` unique to the users migration; make `email` nullable while keeping its unique index (MySQL and SQLite both allow repeated `NULL` in a unique index). Employees may have no email until first login (AUTH-04).                                                                                                                                                                                                                                                                                                                                |
| Registration                                | Remove `Features::registration()` from `config/fortify.php`, then delete `Fortify::registerView(...)` and `Fortify::createUsersUsing(CreateNewUser::class)` from `FortifyServiceProvider`, plus `app/Actions/Fortify/CreateNewUser.php` and `resources/js/pages/auth/Register.vue`. Leave `tests/Feature/Auth/RegistrationTest.php` alone — its `setUp()` calls `$this->skipUnlessFortifyHas(Features::registration())` and self-skips.                                                                                                                     |
| Existing tests                              | `tests/Feature/Auth/AuthenticationTest.php` posts `'email' => $user->email` to `route('login')` / `route('login.store')` in four tests **and** builds the throttle key from `$user->email` in `test_users_are_rate_limited`. Change all of them to `username`.                                                                                                                                                                                                                                                                                              |
| Rate limiting (AUTH-10, SEC-02)             | Leave `FortifyServiceProvider::configureRateLimiting()` untouched — the `login` limiter keys on `Str::transliterate(Str::lower($request->input(Fortify::username())))`, so it follows the config switch automatically. Session expiry stays `SESSION_LIFETIME=120`.                                                                                                                                                                                                                                                                                         |
| Account creation (AUTH-02, AUTH-03, SUB-02) | Only `Admin\EmployeeController@store` (Super Admin) and `Manager\EmployeeController@store` create users, each behind a policy and a seat-quota check. The creator sets username + initial password; record `created_by`.                                                                                                                                                                                                                                                                                                                                    |
| Email verification                          | `User` does **not** implement `MustVerifyEmail` (the import is commented out at `app/Models/User.php:4`), so `verified` in `routes/web.php` and `routes/settings.php` is a no-op today. Email is optional contact data, not an identity: drop `Features::emailVerification()` and replace `verified` on learner routes with the first-login middleware below. `EmailVerificationTest` and `VerificationNotificationTest` self-skip on the feature flag. _(This removal is inferred from AUTH-01/AUTH-04, not stated in system/ — confirm with the client.)_ |
| Password reset (AUTH-07)                    | Self-service reset needs an email, so it only covers users who supplied one. The primary path is admin reset (Super Admin or Manager sets a new hashed password, audit-logged). Employees change their own password via the existing `user-password.update` route.                                                                                                                                                                                                                                                                                          |
| Deactivation (AUTH-08, SUB-05, SUB-08)      | `users.status` = `active                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | inactive`(string column + enum cast). Block at authentication via`Fortify::authenticateUsing()`in`FortifyServiceProvider`: reject inactive users, users of an inactive hotel, and users whose contract window has closed. **That callback replaces the entire credential check** — it must verify the password itself (`Hash::check`) and return the user or `false`. It only guards new logins, so also add a middleware on every authenticated group that logs out or shows the read-only "Your training period has ended" screen SUB-05 permits. **Never delete a user** — remove the `profile.destroy`route from`routes/settings.php`or restrict`ProfileController::destroy` to Super Admin (DATA-10). |
| Cross-device continuity (AUTH-09)           | `SESSION_DRIVER=database` already. All progress lives in server-side rows (PROG-01); browser storage is a short-lived offline buffer for PERF-03/PROG-04 only, never the source of truth.                                                                                                                                                                                                                                                                                                                                                                   |

**First-login gate (AUTH-04, AUTH-05, PRIV-01, PRIV-02).** Add `users.first_login_completed_at`, `users.email_consent_at`, and a per-hotel `hotels.require_email` flag — AUTH-05 says mandatory-email is settable per hotel **or per cohort**, so if cohort-level control is confirmed, resolve `require_email` as cohort override → hotel default. Write `app/Http/Middleware/EnsureFirstLoginCompleted.php`, alias it in `bootstrap/app.php`, apply it to every learner route group; it redirects to `to_route('first-login.edit')` until `first_login_completed_at` is set. The screen collects email + a reminder-consent checkbox and shows the research notice (PRIV-01). Validation is conditional:

```php
'email' => [
    $user->hotel->require_email ? 'required' : 'nullable',
    'string', 'email', 'max:255',
    Rule::unique(User::class)->ignore($user->id),
],
```

Store consent as a timestamp (`email_consent_at`), not a boolean, so grant and revocation are both dated and revocable (PRIV-02, REM-05). AUTH-06 requires the same two fields to remain editable from the profile page afterwards.

### Roles and authorization

`spatie/laravel-permission` is **not installed and is not the default choice**. With exactly three fixed roles (ROLE-01), use a `role` column + Policies + Gates. Propose a permissions package before introducing one, and only if per-permission grants become a real requirement.

- `App\Enums\Role`: backed string enum `SuperAdmin = 'super_admin'`, `Manager = 'manager'`, `Employee = 'employee'`. Cast it in `User::casts()`.
- Super Admin override (ROLE-03) once, in `AppServiceProvider::boot()`:

```php
Gate::before(fn (User $user, string $ability): ?bool => $user->role === Role::SuperAdmin ? true : null);
```

Return `null`, not `false`, so remaining abilities still evaluate for non-super-admins.

- **Every authorization decision is server-side** (ROLE-01, SEC-01). Hiding a nav item is never authorization. Controllers call `Gate::authorize(...)` / `$this->authorize(...)`, or type-hint a FormRequest whose `authorize()` delegates to a policy.
- **Every content and data query is scoped to the actor's hotel and department** (ROLE-02, JOURNEY-03). Two scoping shapes — do not mix them up:
    - _Learner data_ (attempts, enrollments, phrasebook, certificates): strict `hotel_id` / `user_id` equality via a `ScopedToHotel` trait adding a global scope.
    - _Content_ (courses, lessons, tests, scenarios): `hotel_id IS NULL` means shared across hotels (CMS-04, ORG-04), so scope as `where(fn ($q) => $q->whereNull('hotel_id')->orWhere('hotel_id', $user->hotel_id))` plus the department filter.
- A global scope is a safety net, not the authorization. Still write `LessonPolicy`, `TestPolicy`, `ScenarioPolicy`, `AttemptPolicy`, `HotelPolicy`, `EmployeePolicy`.
- **Required for every new resource**: a feature test proving an employee of hotel A gets **403** (not 404, not a redirect) on hotel B's record by direct URL, and the same for a manager crossing hotels. Add the factory states it needs (`UserFactory` today has only `unverified()` and `withTwoFactor()`).

```php
public function test_employee_cannot_open_another_hotels_lesson()
{
    $lesson = Lesson::factory()->for(Hotel::factory())->create();
    $intruder = User::factory()->employee()->create();

    $this->actingAs($intruder)
        ->get(route('learn.lessons.show', $lesson))
        ->assertForbidden();
}
```

- Full AI role-play transcripts and raw recordings are Super-Admin-only (ROLE-04, PRIV-04). Managers get aggregates, criterion scores and summaries unless the Super Admin explicitly grants transcript access (ROLE-04) — model that as an explicit per-manager grant, not a role check. Enforce in the policy **and** strip the fields from the Inertia payload; never ship a transcript the UI merely hides.

### Domain schema

`system/06-data-model-and-reporting.md` 6.1 lists **proposed entities and key fields only** — no table names, columns, types or indexes. The table and column names below are this project's design decision; check them against 6.1 before renaming. Migrate in this dependency order. Every table belonging to a hotel carries an indexed `hotel_id` even when reachable through a parent — scoping and reporting both need it.

| #   | Table                             | Key columns                                                                                                                                                                                                                                                            |
| --- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `hotels`                          | name, slug, status, contract_starts_on, contract_ends_on, require_email, settings json (SUB-04)                                                                                                                                                                        |
| 2   | `departments`                     | name, slug, hotel_id **nullable** (null = global catalogue), position, is_active (ORG-02)                                                                                                                                                                              |
| 3   | `users` (alter)                   | username uniq, role, hotel_id, department_id, email nullable, email_consent_at, status, participant_code uniq (REP-07), cohort, created_by, last_login_at, first_login_completed_at                                                                                    |
| 4   | `seat_quotas`                     | hotel_id, department_id, allowed_seats, unique(hotel_id, department_id) (SUB-01)                                                                                                                                                                                       |
| 5   | `media_assets`                    | disk, path, mime, kind, alt_text, width, height, duration_ms, variants json, uploaded_by, hotel_id nullable, library                                                                                                                                                   |
| 6   | `courses`                         | department_id, hotel_id nullable, title, position, status                                                                                                                                                                                                              |
| 7   | `lessons`                         | course_id, hotel_id nullable (denormalised from the course, kept in sync), title, cover_media_id, position, estimated_minutes, completion_condition json, status, published_at (CMS-05)                                                                                |
| 8   | `blocks`                          | lesson_id, type, position, layout, settings json, is_visible, blockable_type/blockable_id nullable (BLD-03, BLD-04)                                                                                                                                                    |
| 9   | `lexicon_items`                   | kind `word                                                                                                                                                                                                                                                             | expression`, english_text, arabic_meaning, simple_explanation, hotel_example, image/audio_normal/audio_slow media ids, source `manual                                | ai`, show_meaning_enabled (CMS-06, CTRL-03) |
| 10  | `activities`                      | type, prompt, prompt_media_id, payload json, scoring json, time_limit_seconds nullable, attempts_allowed (PRAC-07), show_meaning_enabled, current_version, status                                                                                                      |
| 11  | `activity_versions`               | activity_id, version, payload snapshot json, created_at (DATA-11)                                                                                                                                                                                                      |
| 12  | `activity_placements`             | activity_id, placeable_type/placeable_id (block **or** test), position, overrides json (PRAC-05, WRITE-05)                                                                                                                                                             |
| 13  | `tests`                           | type `pre                                                                                                                                                                                                                                                              | post`, department_id, hotel_id nullable, paired_test_id, settings json (shuffle, time limit, pass condition, results_visibility), status (TEST-04, TSTM-02, TSTM-03) |
| 14  | `ai_scenarios`                    | department_id, hotel_id nullable, difficulty, situation, ai_role, employee_role, objective, expected_language, feedback_criteria json, attempts_allowed (default 3, RP-05), input_mode, min/max_turns, thumbnail_media_id, status                                      |
| 15  | `enrollments`                     | user_id, course_id, status, progress_percent, current_lesson_id, current_block_id, started_at, completed_at, last_activity_at (PROG-02, DATA-06, DATA-07)                                                                                                              |
| 16  | `block_completions`               | user_id, lesson_id, block_id, completed_at                                                                                                                                                                                                                             |
| 17  | `test_attempts`                   | user_id, test_id, attempt_no, status, started_at (server clock), submitted_at, score, breakdown json, results_released_at                                                                                                                                              |
| 18  | `attempts`                        | user_id, activity_version_id, test_attempt_id nullable, lesson_id nullable, attempt_no, raw_answer json, response_media_id nullable, transcript, is_correct nullable, score, max_score, ai_feedback json, score_overridden_by, started_at, submitted_at, time_taken_ms |
| 19  | `roleplay_attempts`               | user_id, ai_scenario_id, attempt_no, transcript json, criteria_scores json, feedback json, duration_seconds, is_preview, started_at, ended_at (RP-11, RP-13)                                                                                                           |
| 20  | `phrasebook_items`                | user_id, saveable_type/saveable_id, custom_text nullable, saved_at, unique(user_id, saveable_type, saveable_id) (DATA-09)                                                                                                                                              |
| 21  | `certificate_templates`           | name, body json, background_media_id, is_default (CERT-05)                                                                                                                                                                                                             |
| 22  | `certificates`                    | user_id, course_id, type, template_id, verification_id uuid uniq, media_id, issued_at, revoked_at (CERT-06, CERT-07)                                                                                                                                                   |
| 23  | `reminder_templates`, `reminders` | template: name, subject, body, variables; log: user_id, template_id, channel, subject/body snapshot, sent_by, sent_at, status, provider_message_id (REM-04, REM-06)                                                                                                    |
| 24  | `ai_usages`                       | user_id, hotel_id, feature, model, prompt_tokens, completion_tokens, cost_estimate, occurred_at (AIL-04, API-03)                                                                                                                                                       |
| 25  | `audit_logs`                      | actor_id, action, auditable_type/id, changes json, ip, created_at (ADM-03, SEC-06)                                                                                                                                                                                     |

Decisions an agent gets wrong by default:

- **Blocks: typed enum + json, polymorphic only where it pays.** `blocks.type` is an `App\Enums\BlockType` with the thirteen types CMS-02 and BLD-02 list (`text`, `image`, `situation`, `vocabulary`, `expressions`, `audio`, `video`, `dialogue`, `practice`, `email_activity`, `phone_activity`, `ai_roleplay`, `note`; BLD-02 adds "and others as needed"). Simple blocks keep content in `settings` json. Only blocks pointing at a **shared, independently edited** entity use the `blockable` morph (→ `activities`, `ai_scenarios`, and `lexicon_items` through a `block_lexicon_item` pivot carrying `position`). Do not create thirteen block tables, and do not bury activities inside json.
- **One `activities` table and one `attempts` table serve lessons and tests alike** (PRAC-05, WRITE-05). Reuse is expressed by `activity_placements`, never by duplicating the activity row. `test_attempt_id` is null for lesson practice.
- **Versioning is mandatory on anything answerable** (DATA-11, TEST-09). Editing an activity's prompt, options or correct answers writes a new `activity_versions` row and bumps `activities.current_version`. Attempts reference `activity_version_id`, never `activity_id` alone. Never mutate a version that already has attempts.
- **Seats are counted, not cached.** 6.1 proposes a `used_seats` field; **do not add one** — it drifts, and SUB-03 explicitly allows lowering a quota below current usage without deleting accounts. `seat_quotas.allowed_seats` is the limit; usage is a live `users()->where('status', 'active')->count()` at check time, with an overage flag surfaced to the Super Admin (SUB-03).
- **Contract period lives on `hotels`** (SUB-04). SUB-06 allows extend / shorten / pause / reactivate; if that history must be auditable, add `hotel_contract_periods` rather than overwriting the dates.
- **No `SoftDeletes` on any attempt, answer, transcript, recording or progress table** (DATA-10). CMS-01 does allow deleting content — use `status` / `archived_at` there, and never let a content delete cascade into rows that hold answers.
- Numerics: scores and percentages `decimal(5,2)`; all durations stored in **milliseconds** as integers.

### Data capture invariants

These are the research requirements. Treat a change that breaks one as a defect, not a trade-off.

| Rule                                                                                                                                                        | IDs                        |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| Store the employee's **actual answer** to every question, not just correctness or a score. `attempts.raw_answer` is always written.                         | TEST-06, DATA-01, PRAC-04  |
| Audio answers are stored as files with a `media_assets` row referenced from the attempt, playable **and** downloadable from the admin panel.                | TEST-07, DATA-02           |
| Written answers are stored verbatim (no trimming, no normalising) with the AI evaluation attached to the same row.                                          | WRITE-04, TEST-08, DATA-03 |
| Store the **full** role-play transcript for **every** attempt, turn by turn, with attempt number, criteria scores and feedback blocks.                      | RP-11, DATA-05             |
| Record `time_taken_ms` on every item **whether or not a timer was configured**.                                                                             | TIME-04, DATA-08           |
| Timers start server-side: persist `started_at` when the item is served and compute remaining time from it, so a refresh cannot reset the clock.             | TIME-05                    |
| Store the question version with each answer (`activity_version_id`).                                                                                        | TEST-09, DATA-11           |
| Nothing is auto-deleted — not on deactivation, not at contract end, not on quota reduction. Progress reset is Super-Admin-only and written to `audit_logs`. | DATA-10, PROG-06, SUB-05   |

### Queues and slow work

`QUEUE_CONNECTION=database`; `jobs`, `job_batches` and `failed_jobs` already exist. Horizon is not installed — do not reference it.

- Queue **every** AI completion, TTS synthesis, AI evaluation, export build, PDF render and reminder email. No LLM or TTS call happens inside a request cycle.
- The UI must show a real in-progress state (PERF-04): persist a status on the owning row (`pending|running|done|failed` + `failed_reason`) and poll it from Inertia. No Echo/Pusher is installed, so polling is the mechanism.
- Local dev already runs a worker: `composer dev` → `php artisan dev`, and `Illuminate\Foundation\DevCommands::registerDefaults()` starts `queue:listen --tries=1 --timeout=0` alongside `serve`, `pail` and Vite. Nothing extra to start. Production runs a supervised `php artisan queue:work --tries=3 --backoff=10,30,60`.
- **Idempotency**: jobs that spend the client's API budget implement `ShouldBeUnique` keyed by the target row and check for an existing result before calling out. A retried job must never create a second attempt row or a second billed call.
- **Retry/failure**: set `$tries` and `$backoff`; implement `failed(Throwable $e)` to write the failed state the UI reads. Never leave a spinner with no terminal state.
- **Audio is generated once and served statically** (CTRL-05, TTS-02). Key stored audio by a hash of `(text, voice, speed)` so the same sentence reused in two lessons resolves to one file. Playback never triggers synthesis.
- `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so jobs run inline in feature tests. Use `Queue::fake()` when asserting dispatch rather than execution.

### AI and TTS integration

- Keys are **server-side env only** — never in `resources/js`, never in an Inertia prop, never committed (API-02, SEC-03). Add them to `.env` and `.env.example` with empty values; `composer setup` copies `.env.example`, so nothing may fail at boot when they are blank.
- **Owner console (spec 0007, `docs/specs/0007-owner-console/`).** The platform owner (a separate `owner` guard at `/owner`, not a `User`) can store the Qwen and Deepgram keys encrypted in the DB, fund each account with credit, and set the model prices. Read a key through `app(ApiKeyring::class)->key('services.ai.key')`, never `config('services.*.key')`, and do not bypass the `ApiCredit` checks in the provider bindings. Because the `owner` guard exists, Larastan types a bare `$request->user()` as `Owner|User`: name the guard, `$request->user('web')`.
- Provider, model and endpoint are swappable without a code change (API-04). Extend `config/services.php`:

```php
'ai' => [
    'provider' => env('AI_PROVIDER'),
    'model' => env('AI_MODEL'),
    'base_url' => env('AI_BASE_URL'),
    'key' => env('AI_KEY'),
],

'tts' => [
    'provider' => env('TTS_PROVIDER'),
    'voice' => env('TTS_VOICE'),
    'key' => env('TTS_KEY'),
],
```

Application code depends on an `App\Contracts\AiProvider` / `TtsProvider` interface resolved from that config, never on a concrete SDK scattered through controllers.

- **When writing code that calls an LLM, load the `claude-api` skill and check current model IDs and parameters. Never hardcode a model name from memory** — read it from `config('services.ai.model')`.
- Meter every call into `ai_usages` (user, hotel, feature, model, tokens, cost estimate) and enforce limits **before** dispatching the job — per employee and per hotel, with a clear blocking message that leaves the rest of the platform usable (AIL-01…AIL-04, API-03).
- AI output is a **draft**: generation writes to a draft field the admin reviews, edits and explicitly publishes; nothing AI-produced is ever auto-published (GEN-03). Always expose a Regenerate action next to it (GEN-04).
- Role-play prompts are assembled **from the `ai_scenarios` row** — situation, ai_role, employee_role, objective, expected_language, turn bounds — plus a fixed system instruction forbidding drift into a general assistant (RP-04). Never accept a raw prompt from the client.
- Evaluation output is stored **structured**, as criterion → {score, comment}, plus the suggested better answer (AIE-01, AIE-04). Free-text-only storage is a defect: it cannot be exported to long format.
- An admin can override an AI score: write `score_overridden_by` and keep the original value alongside (AIE-05).
- Preview/test runs set `is_preview = true`, never count toward an employee's attempts and never appear in exports — RP-13 requires a preview conversation "not saved to any employee's record".

### Media and storage

`FILESYSTEM_DISK=local`. `config/filesystems.php` defines `local` (root `storage/app/private`, `serve => true`) and `public` (root `storage/app/public`). `composer setup` does **not** run `php artisan storage:link` — add it to the `setup` script.

- **Learner-generated media (voice recordings) goes on the private disk** and is served only through an authorized controller route — never a public URL (PRIV-04, SEC-04).
- CMS content images go on the `public` disk under `content/{type}/{yyyy}/{mm}/` with a **UUID filename**; keep the original filename in `media_assets` as metadata only (`desgin/12-assets.md` 12.4).
- Validate every upload by MIME type **and** size on the server (SEC-04). Images: jpg/png/webp, **max 5MB** — `File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)` (`max()` is in kilobytes; `File::image()` would also admit gif/bmp). Extension checks alone are not enough.
- Generate derivatives on upload: **thumb 320w, card 800w, cover 1600w, all WebP**, recorded in `media_assets.variants` so the frontend can emit `srcset` (MED-04, PERF-01). Admin-facing recommended sizes are in 12.4.
- `alt_text` is **required** on every image upload — validate it; do not default it to the filename (MED-07, ACC-05).
- `intervention/image` is **not installed**. Propose it (or a plain GD/Imagick service) before writing resize code.

### Exports and reporting

- `maatwebsite/excel` is **not installed**. Default to a **streamed CSV** built in Laravel (`response()->streamDownload()` with `fputcsv`, chunked via `lazyById()`), so memory does not scale with row count. REP-03 says "Excel / CSV"; propose an XLSX package only if the client requires `.xlsx` specifically.
- The research export is **long format — one row per answer**: employee, participant code, hotel, department, test/lesson, activity id + version, question text, raw answer, correctness, score, criterion scores, time taken, timestamps (REP-06). The full dataset list is REP-04.
- Ship an **anonymised variant** keyed on `participant_code`, omitting name, username and email (REP-07).
- Manager exports are scoped to their own hotel and **strip full transcripts and raw recordings** (REP-08, ROLE-04).
- Audio bundles export as a zip with filenames traceable to employee + question (REP-05), e.g. `{participant_code}_{test}_{activity_id}v{version}_{attempt_no}.{ext}`. **No recording format is specified anywhere in system/ or desgin/** — decide it with the client before hardcoding an extension.
- Exports are **queued** (they are slow) and every export writes an `audit_logs` row with actor, filters and row count (SEC-06).

### Certificates

- PDF generation with **no PDF package installed** — propose `barryvdh/laravel-dompdf` or a headless-Chrome renderer before writing generation code (CERT-01).
- Render from a `certificate_templates` row the admin edits in the panel — background asset, logo, heading and body copy with variables — not from a hardcoded Blade file (CERT-05). Template assets are listed in `desgin/12-assets.md` 12.5.
- Generation is queued; the produced PDF is stored as a `media_assets` row referenced by `certificates.media_id`.
- Fields: employee name, hotel, department, course/programme, completion date, logo/signature (CERT-03). Type is `participation` or `completion`, selected by the admin-defined completion condition (CERT-02, CERT-04).
- Issue / revoke / re-issue are separate actions; revoking sets `revoked_at` and keeps both the row and the file (CERT-06). Every certificate carries a `verification_id` (uuid) (CERT-07 — "a unique certificate ID for verification", a _suggested_ requirement; confirm whether a public lookup route is wanted before building one).

### Backend code conventions

Copy the patterns already in the repo. `composer test` runs `config:clear` + Pint + PHPStan level 7 + `artisan test`; CI runs `composer ci:check`.

- **Thin controllers.** Read → authorize → delegate → `Inertia::render('admin/lessons/Index')` or `to_route(...)`. Page component paths use **lowercase directory segments** (`'settings/Profile'`, `'learn/LessonShow'`).
- **A FormRequest per write**, in `app/Http/Requests/<Area>/`; put `authorize()` logic there or delegate to a policy. Share repeated rules as traits in `app/Concerns/` (see `ProfileValidationRules`), never by copy-paste.
- **Laravel 13 model attribute style** — class attributes, not properties:

```php
#[Fillable(['username', 'name', 'email', 'role', 'hotel_id', 'department_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
```

with a `protected function casts(): array` method. Do **not** add `$fillable` / `$hidden` / `$casts` properties.

- **PHPDoc `@property` blocks on every model** (as `User` already has) and generics on relations — `@return BelongsTo<Hotel, $this>`, two type parameters in Laravel 13. `phpstan.neon` has no `databaseMigrationsPath`, so Larastan resolves unknown model attributes as `mixed`; at level 7 that surfaces as an error the moment one is passed into a typed parameter.
- **Enums, not strings**, in `app/Enums/`: `Role`, `AccountStatus`, `ContentStatus` (`draft|published`), `BlockType`, `ActivityType`, `TestType`, `AttemptStatus`, `AiFeature`, `CertificateType`. Cast them in `casts()`. The status vocabulary the UI expects is in `desgin/11-components.md` 11.3 (`StatusPill`: Active, Completed, Published, In progress, Draft, Not started, Inactive).
- **Named routes grouped per area**: add `routes/admin.php`, `routes/manager.php`, `routes/learn.php` and `require __DIR__.'/<file>.php';` from `routes/web.php`, exactly as `settings.php` is required today. Wayfinder regenerates `resources/js/routes/**` and `resources/js/actions/**` from them — never hand-edit those directories.
- **Flash toasts server-side**: `Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson published.')]);` then `return to_route('admin.lessons.index');` — the pattern `ProfileController::update` already uses. Wrap every user-facing string in `__()` (I18N-02 requires a switchable EN/AR interface).
- **Tests are class-based PHPUnit**, not Pest: `Tests\TestCase`, `use RefreshDatabase;`, methods named `test_snake_case()`. Add a factory under `database/factories/` for every new model.
- Run `composer lint` and `composer types:check` before handing work back.

---

## 7. Mobile, Accessibility, Performance & Security

Employee screens are phone screens that happen to also work on a laptop; admin/manager screens are the reverse (RESP-01, RESP-02). The audience has weak English, so image + audio + short sentences and icon/text redundancy are functional requirements, not polish (LESSON-06, ACC-01, ACC-02).

### Mobile-first (RESP-01, RESP-03)

- Verify every employee-facing screen at 390px width before calling it done. No horizontal scroll at 390px, ever. Base classes are mobile; `md:`/`xl:` are the enhancement.
- There is **no `tailwind.config.js`** in this repo and none is to be created. `components.json` (`"config": "tailwind.config.js"`) and `vite.config.ts` (`lint.ignorePatterns`) both still name one — stale references, the file is absent. Breakpoints come from Tailwind v4 defaults; all theme tokens live in `@theme` in `resources/css/app.css`.
- Design bands (`desgin/10-design-system.md` 10.5) map onto v4 defaults exactly: `<768px` = base, `768–1279px` = `md:`, `≥1280px` = `xl:`. Do **not** use `lg:` (1024px) — it is not a design band.

| Surface        | Base `<768px`                                                 | `md:` 768–1279px                                        | `xl:` ≥1280px                                                   |
| -------------- | ------------------------------------------------------------- | ------------------------------------------------------- | --------------------------------------------------------------- |
| Shell          | Bottom tab bar 64px (`h-16`) + topbar 56px, 16px page padding | 72px icon rail + 72px topbar                            | 200px sidebar + 72px topbar, content max-w 1400px, 24px gutters |
| Stat cards     | Horizontal scroll row                                         | Wrap 3-up                                               | 5–6 in a row                                                    |
| Grids / panels | 1-column                                                      | 2-column                                                | 12-column (4/4/4, 5/4/3, 8/4)                                   |
| Data tables    | Stacked cards (11.3)                                          | Full table; horizontal scroll allowed on **admin only** | Full table                                                      |
| Admin editors  | Tabbed sections                                               | 2-column, preview under editor                          | 3-column: list 300px / editor fluid / preview 320px             |

- Employee mobile rules (full list `desgin/11-components.md` 11.10; sources noted where they differ):

    | Element             | Rule below 768px                                                                                                                                                                                      |
    | ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
    | Primary nav         | Bottom tab bar: Home, Lessons, Phrasebook, Progress, Profile; active item `brand-600` + 3px top indicator. Desktop sidebar hidden.                                                                    |
    | Topbar              | 56px: logo mark + notification + avatar only                                                                                                                                                          |
    | Lesson step         | Sticky slim progress bar + "Step 4 of 9" chip; swipe left/right between steps (`useSwipe` from `@vueuse/core`) **alongside** visible Next/Back (LESSON-03, LESSON-04)                                 |
    | Answer options      | Full-width rows, 56px tall (`min-h-14`), 16px text                                                                                                                                                    |
    | Test chrome         | Timer + question navigator collapse into a sticky header bar with a "Questions ▾" bottom sheet                                                                                                        |
    | Mic button          | 96px (`size-24`). 11.6 specifies 88px (`size-22`) for the desktop speaking question; the docs do not say which wins — **ruling: mobile 96px below 768px**, 88px above. Revisit if the client objects. |
    | Vocabulary          | Swipeable card carousel, one card at a time                                                                                                                                                           |
    | Image + Text blocks | Stack **image first**                                                                                                                                                                                 |
    | Modals              | Bottom sheets with a drag handle — use the generated `Sheet` primitive, never hand-rolled                                                                                                             |
    | Decorations (11.8)  | `pointer-events-none`, hidden below 768px, except the header photo strip which becomes a 96px banner                                                                                                  |
    | Everything          | Minimum 44×44px tap target (`min-h-11 min-w-11`); **no hover-only affordance** — every hover action has a tap/focus path                                                                              |

```vue
<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="rounded-t-xl md:max-w-md">
            <div class="mx-auto mb-2 h-1.5 w-10 rounded-full bg-border" />
            <SheetHeader>
                <SheetTitle>Questions</SheetTitle>
                <SheetDescription class="sr-only">
                    Jump to a question
                </SheetDescription>
            </SheetHeader>
            <slot />
        </SheetContent>
    </Sheet>
</template>
```

- `SheetContent` already supports `side="bottom"`; reka-ui's `DialogContent` warns without a description, so always render `SheetDescription` (`sr-only` if not visible).
- Anything the drag-and-drop builder produces must render correctly on a phone (RESP-03, BLD-06); the CMS "Preview as employee" mode includes a phone preview (CMS-03) — wire real phone-width preview, not a CSS scale trick.

### Accessibility (ACC-01..ACC-05)

- **Never signal correct/incorrect with colour alone — icon + text always** (ACC-02): answer feedback, status pills, needs-attention rows, chart legends.

```vue
<script setup lang="ts">
import { Check, X } from '@lucide/vue';

defineProps<{ correct: boolean }>();
</script>

<template>
    <p
        class="flex min-h-11 items-center gap-2 text-base font-semibold"
        :class="correct ? 'text-success' : 'text-danger'"
    >
        <component
            :is="correct ? Check : X"
            class="size-5 shrink-0"
            aria-hidden="true"
        />
        {{ correct ? 'Correct' : 'Not quite' }}
    </p>
</template>
```

- `text-success`, `text-danger` and `font-arabic` come from the `@theme` block in `resources/css/app.css` (§3). There is no separate focus ring token; build the ring from `brand-600` utilities as described below.
- Icons: `@lucide/vue` (`import { Check, X, Mic, Volume2, Turtle, Languages } from '@lucide/vue';`). `desgin/11-components.md` 11.9 **and** `desgin/12-assets.md` 12.1 both tell you to install `lucide-vue-next` — **wrong for this repo, do not install it**. The icon-name mapping in 11.9 is still correct (verified: `Check`, `X`, `Mic`, `Volume2`, `Turtle`, `Languages` all export from `@lucide/vue`).
- Employee learning content runs one step up: 16px body, 18–20px vocabulary and dialogue lines (`desgin/10-design-system.md` 10.2; rationale ACC-01).
- Audio controls get large thumb-friendly targets specifically, beyond the 44px floor (ACC-03, CTRL-06).
- Alt text: ACC-05 requires it on instructional images; MED-07 and `desgin/12-assets.md` 12.4 require an alt-text field on **every** upload — enforce `required` on the alt field server-side in the FormRequest. Decorative SVGs get `alt=""` + `aria-hidden="true"`.
- Provide audio for **instructions**, not only content, wherever the admin generated it (ACC-04). Instruction strips carry the same 🔊 / 🐢 controls.
- Visible keyboard focus everywhere: border `brand-600` + ring `0 0 0 3px rgba(11,92,255,.15)` (`desgin/11-components.md` 11.4; `--ring-focus` in `desgin/tokens.css` — that file is plain Tailwind-v3-era CSS and is **not** imported by the build, so translate the value into `@theme`, do not `@import` it). Use `focus-visible:` utilities; never `outline-none` without a replacement ring.
- Arabic inside Show Meaning must render correctly while the surrounding UI is LTR (I18N-03): wrap the Arabic node in `<span dir="rtl" lang="ar" class="font-arabic">`, never rely on page direction. Cairo (fallback Tajawal) is the Arabic family; `vite.config.ts` loads it with the `arabic` subset.
- Show Meaning is off by default and toggleable (CTRL-01, CTRL-02), and **disabled during Pre-test and Post-test** (CTRL-04, TEST-03).

### Performance (PERF-01..PERF-04)

- Images: derivatives at thumb 320w / card 800w / cover 1600w, all WebP, served with `srcset`; upload cap 5MB, accept JPG/PNG/WebP (`desgin/12-assets.md` 12.4; MED-04). Shipped brand/decor photos additionally carry a JPG fallback (12.2). **No image-processing package is installed** (`intervention/image`, `spatie/laravel-medialibrary` both absent) — choose and install one, or resize at the edge, before claiming MED-04.
- Audio is pre-generated and stored as files; tapping Listen plays a saved file, never a fresh TTS call (CTRL-05, TTS-02). Encode for mobile bandwidth (TTS-05). Generation runs in a queued job (`QUEUE_CONNECTION=database`).
- Lazy-load media; do not download a whole lesson's audio before step one is usable (PERF-02): `loading="lazy"` + `decoding="async"` on off-screen images, `preload="none"` on `<audio>`, fetch step _n+1_ media only when step _n_ is on screen.
- Heavy dashboard panels (team progress, charts, reports) use Inertia deferred props so the shell paints first, with a `Skeleton` fallback — never a blank card.

```php
return Inertia::render('manager/Dashboard', [
    'stats' => $stats,
    'teamProgress' => Inertia::defer(fn () => $this->teamProgress($request->user())),
]);
```

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import TeamProgressPanel from '@/components/TeamProgressPanel.vue';
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    teamProgress?: { id: number; name: string; percent: number }[];
}>();
</script>

<template>
    <Deferred data="teamProgress">
        <template #fallback>
            <Skeleton class="h-40 w-full rounded-lg" />
        </template>
        <TeamProgressPanel :rows="teamProgress ?? []" />
    </Deferred>
</template>
```

- `Deferred` (slots `default`, `fallback`, `rescue`), `WhenVisible` and `usePoll` are exported by the installed `@inertiajs/vue3` 3.7.1 — use the `#rescue` slot for the error state, and `router.reload({ only: [...] })` for partial refreshes instead of a full visit.
- Tolerate intermittent connectivity (PERF-03, PROG-04): submit optimistically, keep the payload until the server confirms, retry with backoff, never lose an answer. Surface state through the existing toast pipeline (`resources/js/lib/flashToast.ts`; server side `Inertia::flash('toast', ['type' => 'error', 'message' => __('...')])`, `type` ∈ `success|info|warning|error`). Answer writes must be idempotent per (attempt, question) so a retry cannot duplicate a row.
- Progress is saved server-side on every step completion and every answer submission, not at lesson end (PROG-01, PROG-03). Test timers key off a server-side start timestamp so a refresh cannot reset them (TIME-05).
- **Every AI call shows a visible in-progress state** (PERF-04): disable the submit control, show a labelled state ("Evaluating your answer…"), never look idle. Applies to role-play turns (RP-03), AI evaluation (AIE-01, AIE-02) and AI content generation (GEN-01).

### Security & privacy (SEC-01..SEC-06, PRIV-01..PRIV-05)

- **Authorize server-side on every request** (SEC-01, ROLE-01, ROLE-02). Hiding a link is not authorization. Scope every content query to the actor's hotel + department. `spatie/laravel-permission` is **not installed** — use Laravel policies + `Gate::authorize` plus a global scope on tenant-owned models.
- Every tenant-scoped route ships with a test proving a cross-tenant request is refused. Class-based PHPUnit (`Tests\TestCase`, `RefreshDatabase`, `test_snake_case`); Pest is not used. No domain models exist yet — this is the shape to follow:

```php
public function test_employee_cannot_open_another_hotels_lesson()
{
    $lesson = Lesson::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('lessons.show', $lesson))
        ->assertForbidden();
}
```

- Passwords hashed (`User::casts()` already casts `password => 'hashed'`), sessions expire (`SESSION_LIFETIME=120`), login rate-limited (SEC-02, AUTH-10). Limiters are **already registered** in `app/Providers/FortifyServiceProvider.php::configureRateLimiting()` — `login` 5/min keyed on `Fortify::username()`+IP, `two-factor` 5/min, `passkeys` 10/min. Extend those, never add a parallel throttle.
- API keys (TTS, LLM) are read from `env()` in `config/*.php` only, server-side, and are never put into Inertia props or `resources/js/**` (SEC-03, API-02).
- Uploads validated by type and size in a FormRequest (`mimes`, `max`) (SEC-04). Storage split:
    - Employee recordings, transcripts, AI evaluations, exports, certificates → default `local` disk (root `storage_path('app/private')`), served only through an authorizing controller.
    - Public lesson media (images, generated audio, video) → `public` disk + `storage:link`, or a CDN, so it is cacheable. `desgin/12-assets.md` 12.4 says to store _all_ CMS uploads under `storage/app/public/...` — that is correct for lesson media, wrong for anything in the private list above.
    - `FILESYSTEM_DISK=local` is the default, so name the disk explicitly (`Storage::disk('public')`). Never trust the uploaded filename; store UUID names.
- HTTPS everywhere plus automated DB + media backups with a tested restore (SEC-05, PRIV-05). `SESSION_SECURE_COOKIE` is not in `.env.example` — add it and set it `true` in staging/production.
- Audit-log every admin action on accounts, content and exports: actor, action, target, timestamp (SEC-06, ADM-03; `AuditLog` entity in `system/06` 6.1). Progress resets are logged and Super-Admin-only (PROG-06).
- **Recordings and transcripts are Super Admin only** (PRIV-04, ROLE-04); manager exports are hotel-scoped and exclude full AI transcripts (REP-08). Enforce in the policy, not the Vue component.
- Reminder-email consent is recorded and revocable from the profile (PRIV-02, AUTH-04, AUTH-06, REM-05). Store `email_consent` **plus** `email_consent_at` — the timestamp is our implementation choice, not a literal spec line, but REM-05's "record of consent is kept" needs it.
- A short notice at **first login** tells the employee their activity is recorded for training follow-up and research (PRIV-01); persist the acknowledgement (implementation choice, so the notice is provably shown).
- Personal data limited to name, username, email, hotel, department (PRIV-03). Do not add phone, DOB, photo or national ID to `User` without a written client decision. Data is retained on deactivation — never auto-deleted (DATA-10, AUTH-08).

### Voice recording (RESP-05)

- Must work from mobile Safari (iOS) and Android Chrome. Use `MediaRecorder` via `useUserMedia` from `@vueuse/core` (12.8.x, installed); do not add a recorder library.
- Permission flow: explain _before_ prompting ("We need your microphone to record your answer"), then request on an explicit tap — never on mount. Track with `usePermission('microphone')`.
- Denied or unavailable → non-blocking fallback that names the recovery step, plus a text-input alternative where the activity allows one. A denied mic must never dead-end a test.
- Recording state = red pulsing ring + waveform + `0:00 / 0:20` counter (`desgin/11-components.md` 11.6); respect the per-activity duration cap.
- Recordings **upload and are stored**, playable and downloadable from the admin panel, filenames traceable to employee + question (TEST-07, DATA-02, REP-05). Client-side-only blobs are a bug.
- Audio and video players must be mobile-friendly on iOS Safari and Android Chrome (RESP-04).
- PWA install (RESP-06) is marked _(Suggested)_ in `system/07` and no PWA tooling exists here — do not add a manifest or service worker until the client confirms.

### Definition of done — employee-facing screen

- [ ] Renders at 390px with no horizontal scroll; checked, not assumed
- [ ] All interactive targets ≥ 44×44px; no hover-only affordance
- [ ] Skeleton, empty and error states present (never a blank card)
- [ ] Progress/answers persisted server-side on submit, idempotently, with retry on connection loss (PROG-03, PROG-04)
- [ ] Show Meaning off by default, toggleable, disabled inside tests (CTRL-01, CTRL-02, CTRL-04)
- [ ] Correct/incorrect conveyed by icon + text, not colour alone (ACC-02)
- [ ] Motion gated on `motion-safe:` / `motion-reduce:` variants — reduced motion drops to opacity-only fades (`desgin/10-design-system.md` 10.4). These variants are built into Tailwind v4 and already used in `resources/js/pages/Welcome.vue`. `resources/css/app.css` also has a global reduced motion guard; still gate each animated element per utility.
- [ ] Keyboard focus visible on every control using the brand focus ring
- [ ] Every image has meaningful `alt`, or `alt="" aria-hidden="true"` if decorative
- [ ] Arabic inside Show Meaning carries `dir="rtl" lang="ar"` and the Arabic font
- [ ] `npm run check`, `npm run types:check` and `composer test` pass

---

## 8. Working Agreement, Tooling & Guardrails

### Commands

Exactly what exists in `package.json` / `composer.json`. Do not invent scripts.

| Command                                        | What it actually runs                                                                                                                | Notes                                                                                                                                                                                                                       |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run dev`                                  | `vp dev`                                                                                                                             | Vite dev server. Wayfinder and Tailwind run as plugins inside it.                                                                                                                                                           |
| `npm run build`                                | `vp build`                                                                                                                           | Production client bundle.                                                                                                                                                                                                   |
| `npm run build:ssr`                            | `vp build && vp build --ssr`                                                                                                         | **SSR is not wired up**: no `resources/js/ssr.ts`, no SSR entry in `laravel({ input })`, `config/inertia.php` has `ssr.bundle` commented out. Do not run it or rely on `bootstrap/ssr/`. Wiring SSR is a decision to raise. |
| `npm run check`                                | `vp check`                                                                                                                           | oxfmt format check + oxlint (type-aware rules). **Fails on warnings.** Does _not_ run a full TS type check — `lint.options.typeCheck` is not set in `vite.config.ts`.                                                       |
| `npm run check:fix`                            | `vp check --fix`                                                                                                                     | Writes formatting and autofixes. Run before handing work back.                                                                                                                                                              |
| `npm run types:check`                          | `vue-tsc --noEmit`                                                                                                                   | The only full TS/Vue type check. `tsconfig.json` sets `strict: true`.                                                                                                                                                       |
| `composer dev`                                 | `php artisan dev`                                                                                                                    | Multiplexes `serve` + `queue:listen --tries=1` + `pail` (logs) + `npm run dev`.                                                                                                                                             |
| `composer lint`                                | `pint --parallel`                                                                                                                    | Rewrites PHP to the `laravel` preset (`pint.json`).                                                                                                                                                                         |
| `composer lint:check`                          | `pint --parallel --test`                                                                                                             | Non-writing; fails on any drift.                                                                                                                                                                                            |
| `composer types:check`                         | `phpstan analyse`                                                                                                                    | `phpstan.neon`: **level 7**, larastan + carbon extensions, paths `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`. `tests/` is _not_ analysed.                                                                |
| `composer test`                                | `artisan config:clear` → `lint:check` → `types:check` → `artisan test`                                                               | The PHP-side gate.                                                                                                                                                                                                          |
| `composer ci:check`                            | `npm run check` → `npm run types:check` → `composer test`                                                                            | **The full gate. This is what CI runs.**                                                                                                                                                                                    |
| `composer setup`                               | `composer install` → copy `.env.example`→`.env` **if absent** → `key:generate` → `migrate --force` → `npm install` → `npm run build` | First-run bootstrap. It does _not_ `touch database/database.sqlite`; `migrate --force` creates a missing SQLite file itself.                                                                                                |
| `php artisan test`                             | PHPUnit                                                                                                                              | Narrow it: `php artisan test --filter=SeatQuotaTest`.                                                                                                                                                                       |
| `php artisan migrate` / `migrate:fresh --seed` | —                                                                                                                                    | Never edit a migration already applied on a shared DB; add a new one. Only `DatabaseSeeder` exists; there is no domain seed data.                                                                                           |
| `php artisan queue:work`                       | —                                                                                                                                    | `QUEUE_CONNECTION=database` in `.env`. AI generation, TTS, exports and reminder mail must be queued jobs, so a worker has to be running or they never fire locally.                                                         |
| `php artisan wayfinder:generate --with-form`   | —                                                                                                                                    | Manual regen of `resources/js/routes/**` + `resources/js/actions/**`. Normally the `wayfinder({ formVariants: true })` vite plugin does it.                                                                                 |

- **CI** (`.github/workflows/tests.yml`, PHP 8.3 / Node 22): checkout → setup-php → setup-node → `composer setup` → `composer ci:check`. Nothing else.
- **Local services** are Sail/`compose.yaml`: `laravel.test`, `mysql` (8.4), `redis`, `mailpit`. Run `./vendor/bin/sail up -d`. Forwarded ports from `.env`: DB `3307`, Redis `6380`, Mailpit SMTP `1026`, Mailpit dashboard `http://localhost:8026`.
- `compose.yaml` builds `vendor/laravel/sail/runtimes/8.5` (a PHP **8.5** image) while CI pins PHP **8.3** and `composer.json` requires `php: ^8.3`. **Write PHP 8.3-compatible code** — anything that only parses on 8.4/8.5 passes locally and breaks CI. Raise the mismatch rather than silently bumping either side.
- `.env` sets `DB_HOST=mysql`, which resolves only inside the Sail network — run `sail artisan …`, not bare `php artisan`, unless you changed the host.
- `phpunit.xml` pins the test environment: `DB_DATABASE=testing`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`. Consequences: tests hit the **`testing`** database (Sail's MySQL init script creates it), and **queued jobs run inline** — assert dispatch with `Queue::fake()` / `Bus::fake()`, never by watching a worker.
- `DB_CONNECTION` is _not_ pinned by `phpunit.xml`, so tests follow `.env` (MySQL locally) while CI's copied `.env.example` is `DB_CONNECTION=sqlite`. Keep migrations and tests portable across **both** SQLite and MySQL 8.4: no MySQL-only DDL, no `JSON_TABLE`, no raw vendor SQL. _(Open: the exact CI database behaviour is unverified — `DB_DATABASE=testing` under the SQLite driver resolves to a path, not a schema. If CI goes red on database connection, fix it in `phpunit.xml`, and say so.)_

### The quality gate (hard rule)

**A task is not done until `composer ci:check` passes clean.** Run it (or the individual gates while iterating) and fix every failure yourself. Do not report completion on a red gate, and do not weaken a rule to make it pass.

| Gate               | Why it bites                                                                                                                                                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `vp check`         | `denyWarnings: true` and `typeAware: true` in `vite.config.ts` — a _warning_ is a failure. Unused imports and the type-aware rules (floating promises, unsafe `any`) fail here. It also fails on **unformatted** files; `npm run check:fix` fixes that. |
| `vue-tsc --noEmit` | Type props with `defineProps<{ lesson: Lesson }>()`; put shared shapes in `resources/js/types/` (`auth.ts`, `navigation.ts`, `ui.ts`, re-exported from `index.ts`). No `any`.                                                                           |
| `phpstan` level 7  | Real parameter/return types, generics on collections (`Collection<int, Lesson>`), and `@property` PHPDoc on models for attributes it cannot infer (see `app/Models/User.php` for the house pattern).                                                    |
| `pint --test`      | Any hand-formatting that disagrees with the `laravel` preset.                                                                                                                                                                                           |

If a PHPStan error is genuinely unfixable, propose a targeted `ignoreErrors` entry with the reason — never lower `level`.

### Formatting (match it, so `check:fix` produces no diff)

- **4-space indent**, **single quotes**, **semicolons**, **printWidth 80** (`fmt` block in `vite.config.ts`); `.editorconfig` adds LF, UTF-8, final newline, trimmed trailing whitespace.
- `htmlWhitespaceSensitivity: 'css'`, `singleAttributePerLine: false`.
- Tailwind classes are auto-sorted inside `clsx()`, `cn()` and `cva()`, with `resources/css/app.css` as the entry point. Write classes in any order; let the formatter settle them.
- PHP is formatted by **Pint, `laravel` preset**. Run `composer lint` rather than hand-aligning.
- `fmt.ignorePatterns` is only: `.github/**`, `composer.json`, `resources/js/components/ui/*`, `resources/views/mail/*`. Note that `resources/js/routes/**`, `actions/**` and `wayfinder/**` are **lint**-ignored but still **format**-checked — if a Wayfinder regen trips `vp check`, run `npm run check:fix`, never hand-edit the output.

### Generated — never hand-edit

| Path                           | Regenerated by                                                                                                                                                                                                         |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/routes/**`       | `wayfinder()` vite plugin on dev/build, or `php artisan wayfinder:generate --with-form`. Change `routes/*.php`, then regenerate.                                                                                       |
| `resources/js/actions/**`      | Same. Driven by controller method signatures.                                                                                                                                                                          |
| `resources/js/wayfinder/**`    | Same (the runtime shim).                                                                                                                                                                                               |
| `resources/js/components/ui/*` | Vendored shadcn-vue (`style: new-york-v4`, base color `neutral`, built on reka-ui). Need a variant? Wrap it in your own component under `resources/js/components/`, or pass `class` through `cn()` from `@/lib/utils`. |
| `bootstrap/ssr/**`             | Would come from `npm run build:ssr` — see the SSR caveat above; the directory does not exist and is not part of any gate.                                                                                              |

All five are in `lint.ignorePatterns`, so a mistake there is silent. **`components.json` points `tailwind.config` at `tailwind.config.js`, which does not exist in this repo.** If you need a new shadcn component, ask the user before running the CLI, and do not let anything create that file.

### Testing

- PHPUnit ^12.5, **class-based**. Pest is not installed; do not write `test('…', fn () => …)`.
- Feature tests live in `tests/Feature/**`, mirroring the route groups (`tests/Feature/Auth/`, `tests/Feature/Settings/` today; add `Admin/`, `Employee/`, `Manager/`).
- Extend `Tests\TestCase`, `use RefreshDatabase;` per class, methods `test_snake_case` with no return type (match `tests/Feature/Settings/ProfileUpdateTest.php`). Assert via `route()` names, not literal URLs.
- `Tests\TestCase` provides `skipUnlessFortifyHas('two-factor-authentication')` — use it for anything gated by a Fortify feature flag instead of asserting a hard 404.
- Every new model gets a factory in `database/factories/` with states for the role/scope variants (`User::factory()->employee()`, `->manager()`). Only `UserFactory` exists today and it has no states yet.
- `data-test` attributes already exist on the starter's interactive controls (`login-button`, `update-profile-button`, `delete-user-button`, `confirm-delete-user-button`, `logout-button`, …). **Nothing consumes them yet** — no browser runner (Dusk/Playwright) is installed and no PHPUnit test references them. Keep adding them to every new button, form and destructive confirm so a runner can be added later; do not add a runner unprompted.

Guesvia-specific tests that are **required**, not optional:

| Test                                                                                                                                        | Requirement      |
| ------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| Employee/manager of hotel A gets **403** on every scoped resource of hotel B, by direct URL and by form POST — one test per scoped resource | ROLE-02, SEC-01  |
| Lessons are locked and return 403/redirect until the Pre-test is submitted                                                                  | JOURNEY-01       |
| A submitted test attempt persists **one row per question** with the raw answer, not just the score                                          | TEST-06, DATA-01 |
| Creating the (quota + 1)th account in a department is blocked with the seat message                                                         | SUB-02           |
| Login by an employee of a hotel whose contract period has ended is blocked, and their data still exists afterwards                          | SUB-05, DATA-10  |

Shape to follow once the models exist (none of `Hotel`/`Lesson` are in the repo yet — this is greenfield):

```php
public function test_employee_cannot_open_a_lesson_from_another_hotel()
{
    $lesson = Lesson::factory()->for(Hotel::factory())->create();
    $outsider = User::factory()->employee()->for(Hotel::factory())->create();

    $this->actingAs($outsider)
        ->get(route('lessons.show', $lesson))
        ->assertForbidden();
}
```

### Dependency policy

**Do not run `composer require` or `npm install` for a new package without proposing it first** — name the package, the requirement ID it serves, and the no-dependency alternative. Wait for a yes.

| Likely need                             | Requirement          | Cheaper alternative to weigh first                                                                                    |
| --------------------------------------- | -------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Drag & drop for the lesson/test builder | BLD-01, BLD-03, 11.7 | Native HTML5 drag events, or `@vueuse/core` pointer utilities (installed).                                            |
| PDF certificate rendering               | CERT-01              | Server-rendered Blade → headless print, or a signed HTML certificate page.                                            |
| Excel/CSV export                        | REP-03, REP-06       | Streamed CSV via `response()->streamDownload()` + `fputcsv`, long format, one row per answer.                         |
| AI provider SDK + TTS                   | RP-_, GEN-_, TTS-*   | Laravel's HTTP client against the REST endpoint, keys in config so the provider swaps without a code change (API-04). |
| Image processing on upload              | MED-04, PERF-01      | GD through Laravel's built-in image handling, or a queued resize job.                                                 |
| Realtime/broadcast for long AI jobs     | PERF-04              | Poll job status from the Inertia page. `laravel/echo` and pusher are **not** installed; `BROADCAST_CONNECTION=log`.   |

`@vueuse/core`, `vue-sonner`, `vue-input-otp`, `reka-ui`, `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css` **are** installed — reach for those first.

### How to approach a feature

1. **Find the requirement IDs.** Grep `system/09-requirements-checklist.md`, then read the owning document. Work from IDs, not a paraphrase.
2. **Check `desgin/11-components.md`** before inventing UI — every mockup component is measured there. If nothing fits, say so explicitly when you create a new one.
3. **Reuse in order:** shadcn `ui/` primitive → existing Guesvia component → new component.
4. **Server side first:** migration + model (+ factory) → Policy scoped on `hotel_id`/`department_id` (ROLE-02) → FormRequest (shared rules go in an `app/Concerns/*ValidationRules.php` trait) → controller in `app/Http/Controllers/**` → routes. `bootstrap/app.php` registers only `routes/web.php` and `routes/console.php`; `routes/settings.php` is `require`d from `web.php`, so **a new route file must be `require`d there too**. There is no `routes/api.php` (though `withExceptions` already renders JSON for `api/*`) — adding one means registering it. Then the **feature test, including the cross-tenant 403**.
5. **Then the page.** `Inertia::render('employee/Lesson', [...])` with a lowercase path segment; route helpers from `@/routes/<group>`, form actions from `@/actions/App/Http/Controllers/…`; submit with the Inertia v3 `<Form v-bind="Controller.method.form()" v-slot="{ errors, processing }">`; breadcrumbs via `defineOptions({ layout: { breadcrumbs: [...] } })`; flash with `Inertia::flash('toast', ['type' => 'success', 'message' => __('…')])` (picked up by `resources/js/lib/flashToast.ts`). Shared props available today are only `name`, `auth.user`, `sidebarOpen` and `notifications.unread`.
6. **Verify at 390px** — no horizontal scroll, tap targets ≥44px, tables collapsed to cards (RESP-01, ACC-03, `desgin/11-components.md` 11.10). Then check dark mode still renders.
7. **Run `composer ci:check`** and fix everything.
8. **Report which requirement IDs are satisfied**, and which parts are not.

### Do not

- Introduce a colour, font, radius, shadow or easing that is not a `@theme` token in `resources/css/app.css`, or change an existing token's value. A mockup colour without a token → sample it, add a named token, report it (§0.2 rule 3).
- Use purple (`#8B6BF7`), teal (`#17B8C6`), warning amber (`#F5A623`) or gold (`#F9C338`) as a **button fill**. Filled buttons are `brand-600` `#0B5CFF`; the only other fill is Excel green `#1E8E5A`, and destructive is an **outline** (`#EF4444`) — `desgin/11-components.md` 11.4.
- Set headings in black. Page titles are `PageHeader`: Poppins 700 28px in `ink-royal` `#110DA8`.
- Use a pure-grey page background. The app background is `#F4F9FE`.
- Give a card a hard grey border alone. Cards are `1px solid #E4EDF8` **plus** `--shadow-card` (blue-tinted), radius 14px. Both, never one.
- Put emoji in the chrome (nav, buttons, headings). The only sanctioned emoji are the four content controls 🔊 🐢 ⭐ 🌐 — all emoji or all Lucide, consistently (11.9).
- Ship childish illustrations, mascots or cartoon characters. Adult hospitality professionals (UX-03).
- Hold progress only in the client. Every step completion and answer POSTs to the server immediately; `localStorage` is a short-lived offline buffer at most (PROG-01, PROG-03, PERF-03).
- Render Arabic by default anywhere. Arabic appears only after **Show Meaning**, in Cairo, correctly shaped and RTL inside an LTR page (CTRL-01, CTRL-02, I18N-01, I18N-03).
- Enable Show Meaning, correctness colours, hints or any feedback inside a Pre-test or Post-test (CTRL-04, TEST-03).
- Put an API key, model name or provider endpoint in frontend code or an Inertia prop. Server-side config only (API-02, SEC-03).
- Hand-edit `resources/js/routes/**`, `resources/js/actions/**`, `resources/js/wayfinder/**`, `resources/js/components/ui/*`, `bootstrap/ssr/**`.
- Create `tailwind.config.js`, or run `npx tailwindcss init`.
- Install `lucide-vue-next` (or any package) without asking.
- Deviate from the mockups or restyle the approved shared chrome (§0). Re-interpreting, "improving" or approximating the design is a defect; if something can't be matched, ask the user.
- Build Phase 3 features unprompted — spaced review, badges/streaks, bulk import, content health check, PWA, anonymised research export (REP-07), advanced analytics (`system/08-open-questions-and-suggestions.md` 8.3). Phase 2 (AI role-play, writing/email evaluation, voice answers, reminders, certificates, manager dashboard) is ordered work, not off-limits — and 8.3 notes role-play and writing evaluation may move into Phase 1. Confirm the phase before starting one.

### Communication

- Be concise. Say **what changed**, **how to test it** (the exact command, or the URL + role to log in as), and **which requirement IDs it satisfies**.
- Cite IDs inline when a decision traces to the spec, e.g. "blocked at the policy, not the UI (ROLE-02)".
- When a task depends on an unresolved item in `system/08-open-questions-and-suggestions.md` 8.1 — department list, shared vs hotel-specific content, CEFR levels, test visibility, Post-test unlock condition, certificate condition, AI/TTS provider, AI usage limit, contract length, end-of-contract behaviour, mandatory-email scope — **flag it and state the assumption you are coding against**. Do not silently invent the answer.
- Ask before: adding a dependency, breaking any invariant in the Do-not list, editing an applied migration, or changing `vite.config.ts` lint/fmt settings, `phpstan.neon`, `pint.json`, `phpunit.xml` or `components.json`.
- If a gate fails for a reason outside your change, say so explicitly rather than "fixing" unrelated code.

---

## 9. Glossary and proposed model names

Nothing below exists in the repo yet. These names are proposals derived from the entity table in `system/06-data-model-and-reporting.md` 6.1, which names entities but not Eloquent classes. **If another AGENTS.md section or a committed migration fixes different names, that wins.**

| Term                      | Meaning                                                                                                                                                    | Proposed model                                                                                                                                                                            |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Hotel                     | Tenant. Contract period, per-department seat quotas, settings (e.g. email mandatory)                                                                       | `Hotel`                                                                                                                                                                                   |
| Department                | Configurable job function (Reception, F&B, Housekeeping, Marketing/Commercial, …). Scopes all content                                                      | `Department`                                                                                                                                                                              |
| Course / Unit             | Ordered group of lessons for a department, optionally hotel-scoped                                                                                         | `Course`                                                                                                                                                                                  |
| Lesson                    | One learning session: cover image, estimated duration, prerequisites, completion condition, draft/published                                                | `Lesson`                                                                                                                                                                                  |
| Block / Step              | One content unit in the CMS **and** one page for the employee — the same thing from two sides (LESSON-01, BLD-07)                                          | `Block`                                                                                                                                                                                   |
| VocabItem / Expression    | english_text, arabic_meaning, simple_explanation, hotel_example, image, audio_normal, audio_slow, source (manual/AI)                                       | `VocabItem`, `Expression`                                                                                                                                                                 |
| Activity                  | One answerable item: prompt, media, options, correct answer(s), scoring rules, optional time limit. Usable in a lesson **or** a test (PRAC-05)             | `Activity`                                                                                                                                                                                |
| Practice                  | A lesson block holding one or more Activities; multiple attempts allowed when configured, tests single-attempt by default (PRAC-07)                        | block type                                                                                                                                                                                |
| Pre-test / Post-test      | Paired assessments: same template, skills and difficulty, different questions (TEST-02, TSTM-03)                                                           | 6.1 puts `type (pre/post)` and `paired_test_id` on one entity, so one `Test` model with a type column is the reading of the spec — an inference, not a settled decision                   |
| Attempt                   | One submission: raw answer, correctness/score, time taken, timestamps, content version                                                                     | `ActivityAttempt`, `TestAttempt` + `TestAnswer`, `RoleplayAttempt`                                                                                                                        |
| Progress / Enrollment     | Per user + course: status, progress %, current lesson, current step, started_at, completed_at, last_activity_at — what "Continue where you left off" reads | `Enrollment`                                                                                                                                                                              |
| Scenario                  | Role-play definition: department, difficulty, situation, AI role, employee role, objective, expected language, criteria, attempts, input mode              | 6.1 spells it `AIScenario`; pick one spelling and use it repo-wide                                                                                                                        |
| Phrasebook                | Per-employee saved words/expressions; persists across devices; playable, meaning-revealable, removable                                                     | `PhrasebookItem`                                                                                                                                                                          |
| Seat quota                | Max employee accounts per hotel **per department**; Manager creation blocked at the limit                                                                  | `SeatQuota`                                                                                                                                                                               |
| Contract period           | A hotel's access window (start + duration or end date); expiry blocks login, preserves data                                                                | fields on `Hotel`                                                                                                                                                                         |
| Certificate               | Participation or Completion; admin-defined condition, editable template, revocable/re-issuable                                                             | `Certificate`                                                                                                                                                                             |
| Cohort / participant code | Research grouping tag and the anonymous identifier used instead of a name in published exports                                                             | Not located by the spec: 8.2 lists both as _suggested additions_, REP-07 requires the anonymised export. User-level fields are the obvious home — confirm before building reports on them |

---

## 10. Phasing and open decisions

| Phase                           | Scope                                                                                                                                                                                                                                                                                                                                      |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1 — core (needed for the study) | Accounts & roles, hotel/department structure, seat quotas + contract period, login + first-login email, lessons with all step types, Show Meaning, audio playback, Phrasebook, progress saving, practice activities, Pre/Post tests with full answer capture, CMS + drag & drop builder, AI content generation + TTS, reports & CSV export |
| 2                               | AI Role-play with full criteria & attempts, writing/email activities with AI evaluation, voice answers + recording storage, reminders (manual + automatic), certificates, manager dashboard                                                                                                                                                |
| 3                               | Spaced review, badges/streaks, bulk import, content health check, PWA install, anonymised research exports, advanced analytics                                                                                                                                                                                                             |

8.3 itself notes that if the client judges AI Role-play and writing evaluation essential to the study design, they move into Phase 1. Treat the table as a proposal, not settled scope (8.1 #17).

### Open decisions — ask, do not invent

Never hard-code an answer to any of these; make it configuration and flag it.

- **Name/branding** — the product was renamed **GHASIDO** on 2026-09-25 (user request); user-facing text says GHASIDO, internal identifiers (`config('guesvia.*')`, `MediaLibrary::GuesviaLibrary`, `guesvia_library`) are unchanged. Open: a client-made GHASIDO logo (the wordmark is ours, `desgin/assets/ghasido-wordmark-generated.png`; the palm mark is the client's `guesvia-logo-original.png`) and the domain.
- **Canonical department list** for launch.
- **Content sharing model** — shared by department across hotels, hotel-specific lessons, or both.
- **Levels** — CEFR (A1/A2/B1) or difficulty tags only.
- **Tests** — one Pre-test per department or one global; whether results are shown to the employee at all (showing Pre-test results could bias the research); whether the Post-test is single-attempt; what exactly unlocks it (all lessons, or a %).
- **Certificates** — condition for Completion vs Participation, minimum Post-test score, whose name/signature/logo appears.
- **AI** — provider and model; TTS provider, voice, accent (British/American); whether speech-to-text is required or the client listens to recordings manually; voice input in role-play from day one or text first.
- **Usage limits** — default AI quota per employee.
- **Contracts** — default length (60 days is only an example); same for every hotel or set individually; on expiry, hard block or read-only access.
- **PhD cohort** — is the mandatory-email rule cohort-only or platform-wide (AUTH-05).
- **Phasing** — which features the first usable study version must include.
- **Mockups** — six admin screens are in `desginphotos/` (index §0.5); ask for the remaining client screens (manager, employee, test runner…) before building them (8.1 #18).

---

## Build approach

<TBD, set by /scope>

## Specs

Build specs are stored in `docs/specs/` as `docs/specs/NNNN-title.md` and written by `/architect`. The feature scope lives in `docs/scope/` and is written by `/scope`. Cite the requirement IDs from `system/` inside both.
