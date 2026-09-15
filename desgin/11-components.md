# 11 — Component Specifications

Every component visible in the 20 mockups, with the values needed to rebuild it. Component names are the suggested Vue file names.

---

## 11.1 App shell

### `AppSidebar.vue`

- Width 200px, white, full height, `border-right: 1px solid var(--border)`
- Logo block at top: mark 36px + "Guesvia" in Poppins 700 22px `brand-700`, subtitle "English for Hotel Staff" Inter 11px `text-muted`
- Nav item: 44px tall, 12px radius, icon 20px + label Inter 14px 500
    - default: `text-muted` icon + `text` label, transparent bg
    - hover: bg `brand-50`
    - **active: bg `brand-50`, icon and label `brand-600`, weight 600** (no left bar in the mockups — the filled pill is the indicator)
- A thin divider before the secondary group (Settings / Help / Log out)
- **Footer:** the palm-island line drawing (`assets/palm-island.svg`, `brand-300` stroke) above the tagline "Hotel People. Brighter Futures." in Poppins 600 13px `brand-700`, centred

Admin nav order: Dashboard, Hotels, Departments, Employees, Lessons & Content, AI Scenarios, Pre-test & Post-test, Messages & Reminders, Reports & Export — then Settings, Help, Log out.
Manager nav: Dashboard, My Team, Training Progress, Test Results, Reminders, Messages — then Help, My Profile, Log out.
Employee nav: Home, My Lessons, My Phrasebook, My Progress, Messages — divider — Pre-test, Training, Post-test, Certificate — then Help, Log out.

### `AppTopbar.vue`

- Height 72px, white, sits above the content area
- Left: logo lockup (on screens without the sidebar logo)
- Centre: script tagline in Caveat 22px `brand-700`, rotates per screen ("Empowered Staff. Happier Guests.", "Real Learning. Real Progress.", "Assess Today. Improve Tomorrow.", "Real Situations. Confident Conversations.", "Practice Today. Brighter Guest Encounters Tomorrow.")
- Right: bell with red count badge → avatar circle 40px (`brand-100` bg, `brand-700` initial) + name/role stack + chevron → language pill `🌐 EN ▾` (white, `border`, `rounded-pill`, 40px tall)
- Far right: header photo strip (palms / Moorish façade) faded out with `--grad-header`

### `PageHeader.vue`

- `h1` in `brand-700`, subtitle 14px `text-muted` underneath
- Right slot: filters (hotel select, date range), primary action button, or the script accent + "sticky note" decoration
- The handwritten accent sits top-right, rotated −4°, Caveat, `brand-700`, with an optional ♥ glyph

---

## 11.2 Cards

### `StatCard.vue`

White, `rounded-lg`, `border`, `--shadow-card`, padding 16–20px, min-height 96px.
Layout: icon chip 44px left, then stack: number (`stat`, `text`), label 13px `text-muted`, optional third line with a percentage in the semantic colour.
A stat card may replace the icon chip with a **mini donut** (see `Donut.vue`) — used for "14 Active Learners 78%".

Colour mapping seen in the mockups: employees → brand blue, active/completed → green, pre-test → purple, post-test → warning/amber, lessons → teal, inactive → red.

### `PanelCard.vue`

Generic white card: header row (h2 title + optional "View All →" link in `brand-600` 13px), body, optional footer. Padding 20px, gap 16px.

---

## 11.3 Data display

### `Donut.vue`

SVG donut, 14px stroke, rounded caps, track `#EDF2F9`. Centre shows a big number + small label. Legend to the right: 10px dot + label + count + right-aligned percentage. Animate the arc from 0 on mount.

### `ProgressBar.vue`

Height 8px, `rounded-pill`, track `#E9F0F8`. Fill: green when complete, `brand-600` in progress, `text-faint` at 0. Percentage label right-aligned 12px 600. Used in the department list, lesson completion list and employee tables.

### `BarChart.vue`

Vertical bars, 10px radius on top corners, grouped Pre/Post uses `brand-400` (pre) + `brand-700` (post); AI performance chart uses `#8B6BF7`. Y axis 0–100, gridlines `#EEF3F9`, axis labels 11px `text-muted`. Legend as small dots above right.

### `DataTable.vue`

- Header row: bg `#F6F9FD`, 12px 600 `text-muted`, 44px tall, first cell can hold a select-all checkbox
- Row height 52px, divider `border`, hover bg `brand-50`
- Name cell: 32px round avatar + name 14px 500
- Numeric cells centred; `12 / 15` style fractions for completion counts
- Status cell uses `StatusPill.vue`
- Actions cell: outlined blue button ("View Details" / "View Progress") 32px tall `rounded-md`, or a row of icon buttons (👁 ✏️ ✉️ ⋮) 32px square, `text-muted` → `brand-600` on hover
- Footer: "Showing 1–10 of 80" left, pager right (current page = filled `brand-600` square, `rounded-md`), plus a "Rows per page" select
- **Mobile:** each row becomes a card — avatar + name on top line, department + last activity as meta, progress bar full width, pill top-right, actions in an overflow menu

### `StatusPill.vue`

Height 24px, `rounded-pill`, padding 0 10px, 12px 600, with a 6px leading dot or small icon.

| State                  | Text      | Bg        | Dot/icon    |
| ---------------------- | --------- | --------- | ----------- |
| Active / Completed     | `#1B7F4F` | `#E7F8F0` | `#2FBE7A` ✓ |
| In progress            | `#8A5A06` | `#FEF3E2` | `#F5A623`   |
| Not started / Inactive | `#B42318` | `#FDECEC` | `#EF4444`   |
| Draft                  | `#8A5A06` | `#FEF3E2` | —           |
| Published              | `#1B7F4F` | `#E7F8F0` | ✓           |

### `ActivityFeed.vue`

Rows of: 28px avatar or coloured icon chip, then "**Name** did _thing_" (name 14px 600, action 14px `text-muted`), right-aligned relative time 12px `text-faint`. Admin variant is a 4-column table (Date & Time / Employee / Activity / Details) with a coloured icon before the activity label.

### `NeedsAttentionList.vue`

Red-accented header with a count badge. Tabs: Inactive / Not Started / Pre-test Finished. Each row: avatar, name, department, reason in red 12px ("Low activity (5 days)"), and a **Send Reminder** button — white bg, `border`, `brand-600` text with ✉ icon, 32px, `rounded-md`.

---

## 11.4 Buttons & inputs

### `BaseButton.vue`

| Variant        | Spec                                                                                                                                                     |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Primary        | bg `brand-600`, white text, 40px (44px on employee screens), `rounded-md`, Poppins 600 14px, shadow `0 2px 8px rgba(11,92,255,.28)`; hover → `brand-700` |
| Secondary      | white bg, 1px `border`, `text` label; hover border `brand-300`, text `brand-600`                                                                         |
| Outline-brand  | white bg, 1px `brand-600`, `brand-600` text — table actions                                                                                              |
| Success        | bg `#1E8E5A` white text — "Export to Excel"                                                                                                              |
| Danger-outline | white bg, `#EF4444` border+text — "Export PDF Report", "Remove"                                                                                          |
| Ghost          | transparent, `text-muted`; hover bg `brand-50`                                                                                                           |
| Icon           | 32/36px square, `rounded-md`, `text-muted` → `brand-600`                                                                                                 |

Large CTAs ("Start Pre-test →", "Continue to Training →", "Continue to Next Lesson →") are full-width or ≥280px, 48px tall, with a trailing arrow that slides 4px right on hover.

### Inputs

- Height 40px, white, 1px `border`, `rounded-sm`, 14px Inter, placeholder `text-faint`
- Focus: border `brand-600` + ring `0 0 0 3px rgba(11,92,255,.15)`
- Label above: 12px 600 `text`; required marked with a red `*`
- Selects show a chevron `text-muted`; search inputs have a leading 🔍 at 16px
- Textarea: min 96px, with a character counter bottom-right ("105/300") in 11px `text-faint`
- Checkbox/radio: 18px, `rounded-sm` / round, checked = `brand-600` fill + white glyph
- Toggle: 44×24px, off `#CBD5E1`, on `brand-600`, 200ms knob slide

### `FilterBar.vue`

A white card holding 4–6 labelled selects in a row + a "Reset Filters" ghost button on the right. Wraps to 2 columns on tablet, stacks with a "Filters" drawer on mobile.

---

## 11.5 Employee learning screens

### `StepTracker.vue` (lesson steps)

Horizontal row of numbered circles connected by a 2px line, one per lesson block: Situation, Vocabulary, Expressions, Listen, Dialogue, Video, Practice, AI Role-play, Complete.

- Completed: `brand-600` fill, white check
- Current: `brand-700` fill, white number, 4px `brand-100` halo
- Upcoming: `#E2E9F3` fill, `text-faint` number
- Labels 11px under each circle; on mobile collapse to "Step 4 of 9" + a slim progress bar.

### `LessonCompleteCard.vue`

Trophy/medal badge circle 64px `brand-700`, "Lesson Completed!" h1, image card on the left with an overlaid quote, and a summary list on the right: each row = icon chip, block name, count ("8 words", "1 of 3 attempts completed"), green check on the right. Right rail: progress donut + an encouragement note + "Continue to Next Lesson" primary and "Back to My Lessons" secondary.

### `VocabCard.vue`

Image top (16:9, `rounded-lg`), English word Poppins 600 20px, phonetic optional, then a control row: 🔊 Normal / 🐢 Slow / ⭐ Save / 🌐 Show Meaning as 36px pill buttons with `brand-50` bg and `brand-600` icons. Show Meaning expands a `brand-50` panel below with Arabic (Cairo, RTL), simple explanation, and a hotel example — 200ms slide-down.

### `DialogueLine.vue`

Alternating alignment. Guest bubble: `#EEF3F9` bg, left, with a small speaker icon. Employee bubble: `brand-50` bg, right. 14px avatar chip per side. Each bubble has 🔊 and 🌐 on hover/tap.

### `AudioButton.vue`

Round 40px, `brand-50` bg, `brand-600` icon; while playing shows a pulsing ring and a waveform; slow variant carries the 🐢 glyph.

---

## 11.6 Test / question screens

### `TestQuestionCard.vue` (the pre-test screens)

- Top: "Pre-test" h1 `brand-700` + "Question 24 of 25" `text-muted`
- Full-width progress bar (8px, `brand-600` fill) with the percentage to the right
- Instruction strip: `brand-50` bg, `rounded-md`, ⓘ icon + 13px text ("Look at the situation and choose the best response.")
- **Type chip** above the prompt: dark blue pill `brand-700` with white 12px label + icon — "Real-life Situation", "Reading for Detail", "Speaking", "Vocabulary in Context", "Ordering a Conversation", "Reading for Meaning", "Situation"
- Prompt: Poppins 600 18px, numbered ("24. A guest wants to check out.")
- Body: image left (`rounded-lg`, 4:3) + options right, or image full width above options
- Options: full-width rows 56px, white, 1px `border`, `rounded-md`, radio + letter (A/B/C/D) + text 15px; hover border `brand-300`; selected border `brand-600` + bg `brand-50`. **No correctness colours during a test.**
- Footer: "← Previous Question" secondary left, "Next Question →" primary right; last question shows "Finish Test ✓"

### `TestSidebar.vue`

- **Timer card:** clock icon in a `brand-50` circle, "Time Remaining" 12px `text-muted`, `01:00` in Poppins 700 26px; turns `#EF4444` under 60s with a soft pulse
- **Question navigator:** 5-column grid of 40px squares, `rounded-md`; unanswered `#F1F5FA`/`text-muted`, answered `brand-100`/`brand-700`, current `brand-600`/white
- **Hint card:** `brand-50`, ⓘ icon, 13px guidance text

### Special question bodies

- **Speaking:** big round mic button 88px, `brand-600` ring, `0:00 / 0:20` counter, "Play My Recording" secondary; recording state = red pulsing ring + waveform
- **Ordering:** source cards in a row (image + caption, letter badge A–D top-left) and 4 dashed drop slots numbered 1–4 below; dragging lifts the card with `--shadow-pop`
- **Reading/Email:** the email or notice rendered as a realistic document card (`#FBFCFE` bg, `border`, monospace-free, From/To/Subject rows in 12px `text-muted`)
- **Complete the sentence:** sentence on a `brand-50` strip with a long underscore gap, options in a 2-column grid

### `TestIntroCard.vue` ("Let's start with a short Pre-test")

Two-column: left white card with eyebrow "WELCOME TO GUESVIA" 11px tracked `text-muted`, h1, explanation, then an icon list (Time / Question types / Number of questions / No feedback / Your results), primary CTA and an "I'll do it later" text link. Right: hero photo + a "Good to know" checklist card (green ✓ rows) + a pink "Remember!" card (`#FDECEC` bg, ♥ icon). Above everything: the 4-step journey tracker **Pre-test → Training → Post-test → Certificate**.

### `TestResultCard.vue`

Centred trophy illustration with confetti, "Well Done, {name}!" 36px `brand-700`, subtitle, then a score card: "Your Score" label, `22 / 25` at 40px 700, `88%` under it in `brand-600`, and a `brand-50` interpretation panel to the right. Actions: "Review My Answers" secondary, "Continue to Training →" primary. Right rail: 100% progress ring, a motivational quote, and a "Next Step" card.

---

## 11.7 Admin editors

### Lesson builder (`LessonsContent.vue`)

- Breadcrumb selector row: 5 selects — Hotel / Department / Course / Unit / Lesson
- Tab bar: Lesson Content · Preview · Settings · Materials · AI Role-play · Quiz/Practice. Active tab = `brand-600` text + 2px underline; right side holds "Save Draft" secondary and "Publish Lesson" primary
- **Left: course tree** — collapsible Course → Unit → Lesson with drag handles, coloured emoji/icon per course, "+ Add Lesson" ghost rows, "+ Add" primary top-right
- **Centre: editor** — Lesson Title input, Cover image frame (16:9, `rounded-lg`) with "Change Image" / "Remove" buttons under it, rich-text Lesson Introduction with a simple toolbar, and an Objectives list (check icon + text + delete, "+ Add Objective")
- **Right: block palette** — "Add Content Blocks", a 4-column grid of tiles 88×72px, `rounded-lg`, each with a pastel tint + icon + label: Situation (blue), Vocabulary (green), Useful Expressions (teal), Dialogue (purple), Watch & Listen (blue), Image/Gallery (green), Video (red/pink), Practice Activity (amber), AI Role-play (indigo), Quiz/Test (purple), Downloadable File (blue), Note/Tip (amber). Tiles are **draggable into the canvas**
- Below the palette: **Image Library** with tabs (My Images / Guesvia Library / Icons & Stickers), search, category select, a 4-column thumbnail grid, an upload dropzone ("JPG, PNG – Max 5MB") and a "Browse Images" button

### `BlockCanvas.vue` (drag & drop)

Each block on the canvas: drag handle (⠿) top-left, type label, and hover actions — duplicate, hide (eye), delete. Drop indicator is a 2px `brand-600` line with a `brand-50` ghost area. Sections carry a layout switcher: Full width / Image + Text / Text + Image / Cards.

### Test builder (`TestsAdmin.vue`)

Three columns: **Test List** (searchable cards with thumbnail, title, department, "30 questions • 30 min", type pill and status pill) — **Create/Edit Test** (title, type, department, time limit, number of questions, description with counter, then question-type selector tiles: Multiple Choice, True/False, Fill in the Blank, Matching, Short Answer, Audio, Image, Video question; then repeatable question cards with drag handle, text, image, options with a "Correct answer" green marker, "+ Add Option") — **right rail** (Question Preview with pager "1/25", Add Media tabs Image/Audio/Video + suggested images, Test Settings checkbox list, pass mark input, "Save Test" primary + "Preview Test" secondary, and a Recent Results 4-tile strip).

### AI scenario editor (`ScenariosAdmin.vue`)

Three columns: **Scenario Library** (cards with thumbnail, title, department, level, status pill, ⋮ menu; filters for department and status) — **Edit Scenario** (title with counter, department + level selects, scenario image with Change/Remove and a recommended-size hint, rich-text description, two role cards side by side — "AI Role (Guest)" with a purple person icon and "Employee Role (User)" with a green one, "Edit AI Instructions" button, Learning Objectives list) — **right rail** (Conversation Preview rendered as a real chat with "Test Scenario" button and a disabled composer; Scenario Settings: Number of Attempts select, Feedback Style select, Focus Areas checkbox grid (Vocabulary, Grammar, Pronunciation, Fluency, Task Completion), two toggles — "Allow hints during conversation", "Show suggested language after completion" — and a tag input; footer buttons Preview / Save as Draft / Update Scenario).
Top tabs: Scenarios · Scenario Categories · AI Instructions · Feedback Templates · Preview & Test.

### Employees admin (`EmployeesAdmin.vue`)

Stat row (6 cards) → search + 3 filter selects + Reset → table → bottom row of three cards: **Bulk Actions** (select + Apply), **Export Employees** (Excel / CSV buttons), **Send Reminder**. Right rail: **Add New Employee** form — Full Name, Username, Password with a "Generate" chip, Hotel select, Department select, Email (optional), "Allow training reminder emails" checkbox with an ⓘ tooltip, Account Status select, and a full-width "⊕ Create Employee" primary button.

### Reports (`ReportsAdmin.vue`)

Filter bar (Hotel, Department, Employee, Activity Type, Completion Status + Reset) → 6 stat cards → 4 chart cards → a **tabbed results panel**: Employee Results · Detailed Answers · AI Role-play Logs · Lesson Progress · Pre/Post Comparison · Download Center, each with a searchable table and row checkboxes → sticky footer: "Export Selected Data:" + Export to Excel (green) + Export to CSV (blue outline) + Export PDF Report (red outline) + an "Include detailed answers" checkbox. Top-right: date range + "Export Data ▾" primary.

---

## 11.8 Decorative elements

These are part of the identity — don't drop them:

1. **Header photo strip** top-right of every screen, faded into white
2. **Handwritten script accent** near the page title, rotated −4°
3. **Sticky note** (cream `#FFF8E1`, 2° rotation, soft shadow, Caveat text) — used on the test and scenario admin screens
4. **Palm-island line drawing** at the bottom of the sidebar
5. **Blurred hospitality photo** as the bottom-right backdrop on employee test screens, at ~25% opacity behind a white overlay
6. **Small desk-sign photo** ("Small Steps Brighter Careers") bottom-right on employee screens

All decorations are `pointer-events: none`, hidden below 768px except the header strip (which becomes a 96px banner).

---

## 11.9 Iconography

Line icons, 1.75px stroke, 20px default (22px in chips, 18px in tables). **Lucide** matches the mockups closely — use `lucide-vue-next`.

Mapping: Dashboard→`layout-dashboard`, Hotels→`building-2`, Departments→`users`, Employees→`user`, Lessons→`book-open`, AI Scenarios→`bot`, Tests→`clipboard-check`, Messages→`mail`, Reports→`bar-chart-3`, Settings→`settings`, Help→`help-circle`, Log out→`log-out`, Phrasebook→`star`, Progress→`trending-up`, Certificate→`award`, Audio→`volume-2`, Slow audio→`turtle` (or `volume-1` + 🐢), Show meaning→`languages`, Timer→`clock`, Mic→`mic`, Video→`video`, Upload→`upload`, Export→`download`, Reminder→`send`.

Emoji appear in the mockups only inside content (🔊 🐢 ⭐ 🌐) — keep those four as emoji or as the matching Lucide icons, consistently.

---

## 11.10 Mobile adaptations (employee — the priority)

- **Bottom tab bar** 64px: Home, Lessons, Phrasebook, Progress, Profile; active tab `brand-600` with a 3px top indicator
- Topbar shrinks to 56px: logo mark + notification + avatar
- Lesson steps: sticky slim progress bar + "Step 4 of 9" chip, swipe left/right to move between steps
- Question options become 56px full-width rows with 16px text
- Timer and question navigator collapse into a sticky header bar with a "Questions ▾" sheet
- Mic button 96px, centred, thumb-reachable
- Vocabulary becomes a swipeable card carousel
- Image + Text sections stack image-first
- Modals become bottom sheets with a drag handle
- Minimum tap target 44×44px; no hover-only affordances anywhere
