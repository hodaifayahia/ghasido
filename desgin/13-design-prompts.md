# 13 — Design Prompts

Three prompts. The first goes at the top of every session (Cursor rules file, Claude Code `CLAUDE.md`, or pasted before any UI task). The second is the per-screen template. The third is for generating images.

---

## 13.1 Master prompt — paste once per session

```
You are building the front-end of Guesvia, an English-training web app for hotel staff.
Stack: Laravel 11 + Inertia + Vue 3 (<script setup>) + Tailwind CSS + Vite + lucide-vue-next.
Never invent new colours, fonts, radii or shadows. Use only the tokens below.

DESIGN LANGUAGE
Clean, bright SaaS dashboard. Very light blue page background, white cards with a 1px
light-blue border AND a soft blue-tinted shadow, generous 14px radii, one vivid royal blue
for every action. Colour variety comes from pastel-tinted icon chips, not from many button
colours. Warm hospitality feel: a photo strip fading into white at the top-right of every
page, a handwritten script accent near the page title, and a palm-island line drawing at the
foot of the sidebar. Adult and professional — lively and animated, never childish.

COLOURS (Tailwind names)
brand-50 #EFF5FF · brand-100 #DBE8FF · brand-300 #8FB7FF · brand-400 #5A92FF
brand-600 #0B5CFF  -> ALL filled buttons, progress fills, active states, selected options
brand-700 #1249C9  -> ALL page titles, the wordmark, big numbers, primary hover
success #2FBE7A / tint #E7F8F0 · warning #F5A623 / tint #FEF3E2
danger #EF4444 / tint #FDECEC · ai #8B6BF7 / tint #F1ECFE
teal #17B8C6 / tint #E4F7FA · gold #F9C338 · excel #1E8E5A
app #F4F9FE (page bg) · surface #FFFFFF · line #E4EDF8
ink #12233D · ink-muted #64748B · ink-faint #94A3B8

TYPE
Poppins 500/600/700 -> headings, buttons, stat numbers, nav labels
Inter 400/500/600   -> body, tables, forms
Caveat 700          -> handwritten taglines only, rotated -4deg, colour brand-700
Cairo               -> Arabic text (Show Meaning panels), dir="rtl"
h1 28/700 brand-700 with a 14px ink-muted subtitle under it. Body 14. Labels 12/600.
Headings are ALWAYS blue, never black.

SHAPE & DEPTH
radius: inputs 6, buttons 10, cards 14, hero/modal 20, pills 999
card = bg-white + border border-line + shadow-card + p-5
shadow-card  0 1px 2px rgba(18,35,61,.04), 0 4px 16px rgba(11,92,255,.06)
shadow-btn   0 2px 8px rgba(11,92,255,.28)   (primary buttons only)
focus ring   0 0 0 3px rgba(11,92,255,.15)
Icon chip on stat cards: 44x44, rounded-xl, semantic tint bg, 22px icon in the base colour.

COMPONENTS (reuse, do not re-style per page)
AppSidebar 200px white, active item = brand-50 pill with brand-600 icon+label
AppTopbar 72px: logo | Caveat tagline | bell with red badge | avatar+name+role | "EN" pill
PageHeader: blue h1 + muted subtitle, right slot for filters/primary action
StatCard, PanelCard, Donut, ProgressBar (8px, rounded-pill), BarChart
DataTable: header bg #F6F9FD, 52px rows, avatar+name cell, StatusPill, outlined blue actions,
  "Showing 1-10 of 80" + pager, and on mobile each row becomes a card
StatusPill: Active/Completed green tint · In progress amber tint · Not started/Inactive red tint
Buttons: primary (brand-600 fill, white, shadow-btn, hover brand-700) · secondary (white +
  border) · outline-brand · success (#1E8E5A, exports) · danger-outline · ghost · icon

MOTION
220ms fade+12px slide-up between pages/steps; cards lift 2px on hover; buttons scale .97 on
press; progress bars and donuts animate from 0 over 700ms; stat numbers count up; correct =
green check scale-in, wrong = 260ms shake; one short confetti burst on lesson complete.
Honour prefers-reduced-motion.

RESPONSIVE — MOBILE IS THE PRIORITY FOR EMPLOYEE SCREENS
Employees are hotel staff on phones. Below 768px: sidebar becomes a 64px bottom tab bar,
every grid goes one column, tables become stacked cards, modals become bottom sheets,
answer options are 56px full-width rows, the mic button is 96px and thumb-reachable,
minimum tap target 44px, nothing hover-only. Admin editors keep their 3-column desktop
layout and become tabbed sections on small screens.

CONTENT RULES THAT AFFECT UI
English is always the visible language; Arabic appears only after the user taps "Show
Meaning", which slides down a brand-50 panel with dir="rtl" Cairo text.
Word/sentence controls are always the same four: Normal audio, Slow audio, Save, Show Meaning.
During a test: no Show Meaning, no correct/incorrect colours, no feedback.

DON'T
No purple/teal/amber buttons (those colours are for chips and charts only).
No pure grey backgrounds, no black headings, no hard 1px grey borders without the soft shadow.
No emoji in the chrome (only the four content controls). No childish illustrations or mascots.
No localStorage-dependent state for progress — progress lives on the server.
```

---

## 13.2 Per-screen prompt template

```
Build {ScreenName} for Guesvia following the design system already in context.

Role: {Super Admin | Hotel Manager | Employee}
Route: {/admin/employees}
Purpose: {one sentence}

Layout (desktop 1280):
{e.g. AppSidebar + AppTopbar; PageHeader "Manage Employees" / "Create accounts, assign
departments, track progress and send reminders."; a row of 6 StatCards; a FilterBar with
search + 3 selects + Reset; a DataTable; a bottom row of 3 cards (Bulk Actions, Export,
Send Reminder); a right rail with the "Add New Employee" form.}

Data: {props / API shape}
States to include: loading skeletons, empty state, error, and the mobile layout.
Reuse existing components; only create a new one if nothing fits, and tell me when you do.
```

Pair this with a reference screenshot whenever you have one — say _"match this screenshot's layout exactly, but use the tokens in context for all colours, type and spacing."_

---

## 13.3 Image generation prompt

```
Professional hospitality photography, {subject}, modern four-star hotel interior, warm
natural window light, shallow depth of field, 35mm lens, realistic adult staff in a dark
hotel uniform with a gold name badge, friendly professional expression, clean uncluttered
composition with copy space on the {left|right}, soft blue-and-warm colour grade, no text,
no logos, no watermark, photorealistic, 4k.
```

Keep the grade phrase identical across every generation — that's what makes the library look like one set rather than a collection.

---

## 13.4 Review checklist

Before accepting any screen:

- [ ] Page title is Poppins 700 `brand-700`, with a muted subtitle
- [ ] Every filled button is `brand-600`; no other colour is used as a button fill except green exports and red destructive outlines
- [ ] Cards have border **and** soft blue shadow, radius 14
- [ ] Stat cards use tinted icon chips, and the tint matches the metric's meaning
- [ ] Status pills use the exact three tint/text pairs
- [ ] There is a script accent and a header photo strip
- [ ] The sidebar footer has the palm drawing and "Hotel People. Brighter Futures."
- [ ] Progress bars and donuts animate from zero
- [ ] Mobile: checked at 390px — no horizontal scroll, tap targets ≥44px, tables became cards
- [ ] Arabic only appears behind Show Meaning, rendered RTL in Cairo
- [ ] Nothing hover-only; keyboard focus rings visible
