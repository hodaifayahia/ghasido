# 10 — Design System: Colours, Type, Spacing, Motion

## 10.1 Colour palette

Values marked **(sampled)** were read directly from the mockup pixels with a median filter; the rest of each scale was built around them.

### Brand blue — the only action colour

| Token           | Hex                         | Where it appears in the mockups                                               |
| --------------- | --------------------------- | ----------------------------------------------------------------------------- |
| `brand-50`      | `#EFF5FF`                   | Active sidebar item background, info strip behind question instructions       |
| `brand-100`     | `#DBE8FF`                   | Chart track, light chips, hover on sidebar                                    |
| `brand-200`     | `#BDD5FF`                   | Progress-bar track on blue surfaces                                           |
| `brand-300`     | `#8FB7FF`                   | Disabled primary, light chart bars                                            |
| `brand-400`     | `#5A92FF`                   | Secondary bar in Pre/Post chart                                               |
| `brand-500`     | `#2B6CFF`                   | Gradients, hover states                                                       |
| **`brand-600`** | **`#0B5CFF`** **(sampled)** | **Primary buttons, links, progress fill, active step circle, selected radio** |
| `brand-700`     | `#1249C9` **(sampled)**     | Page titles, "Guesvia" wordmark, primary button hover, big numbers            |
| `brand-800`     | `#0F3A9E`                   | Pressed state                                                                 |
| `brand-900`     | `#0C2E7A`                   | Rare — dark headings on tinted surfaces                                       |

> Note the mockups use **two blues**: bright `#0B5CFF` for filled buttons and bars, and deeper `#1249C9` for large heading text and the logo. Keep both — using only one loses the look.

### Semantic colours

| Purpose     | Base                              | Tint (badge/icon bg) | Used for                                                                                        |
| ----------- | --------------------------------- | -------------------- | ----------------------------------------------------------------------------------------------- |
| Success     | `#2FBE7A` **(sampled `#3DC07A`)** | `#E7F8F0`            | "Active" / "Completed" pills, green progress bars, check icons, donut "Completed" segment       |
| Warning     | `#F5A623`                         | `#FEF3E2`            | "In progress" pill, average-progress stat, attention states                                     |
| Danger      | `#EF4444`                         | `#FDECEC`            | "Not started" / "Inactive" pills, notification dot, "Needs Attention" header, Export PDF button |
| Info / AI   | `#8B6BF7` **(sampled `#9670FB`)** | `#F1ECFE`            | AI Role-play charts, "Completed Pre-test" icon chip, AI-related accents                         |
| Teal        | `#17B8C6`                         | `#E4F7FA`            | "Completed Lessons" chip, secondary chart series                                                |
| Gold        | `#F9C338` **(sampled)**           | `#FEF6DC`            | Trophy, certificate, star / phrasebook icon                                                     |
| Excel green | `#1E8E5A`                         | `#E6F5EE`            | "Export to Excel" button                                                                        |

### Neutrals / surfaces

| Token           | Hex       | Use                                                |
| --------------- | --------- | -------------------------------------------------- |
| `bg-app`        | `#F4F9FE` | Page background (very light blue, never pure grey) |
| `bg-app-alt`    | `#EFF7FD` | Section bands, table zebra                         |
| `surface`       | `#FFFFFF` | Cards, sidebar, topbar, modals                     |
| `border`        | `#E4EDF8` | Card borders, table dividers, input borders        |
| `border-strong` | `#CBDCF0` | Input focus ring base, dropdown borders            |
| `text`          | `#12233D` | Primary text                                       |
| `text-muted`    | `#64748B` | Subtitles, labels, table meta                      |
| `text-faint`    | `#94A3B8` | Placeholders, disabled, timestamps                 |

### Gradients

```css
--grad-page: linear-gradient(
    180deg,
    #f7fbff 0%,
    #eaf3fd 100%
); /* app background */
--grad-brand: linear-gradient(
    135deg,
    #0b5cff 0%,
    #1249c9 100%
); /* CTA hover, hero chips */
--grad-header: linear-gradient(
    90deg,
    #ffffff 55%,
    rgba(255, 255, 255, 0) 100%
); /* fade over header photo */
```

The header photo (palms / hotel façade) on the top-right of every screen sits **behind** a white-to-transparent fade so the nav stays readable. That fade is `--grad-header`, mirrored for RTL.

---

## 10.2 Typography

Three families, all free on Google Fonts.

| Role              | Family                       | Weights       | Notes                                                                                                                   |
| ----------------- | ---------------------------- | ------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Headings & UI** | **Poppins**                  | 500, 600, 700 | The geometric rounded look of "Admin Dashboard", "Guesvia", stat numbers, buttons                                       |
| **Body & data**   | **Inter**                    | 400, 500, 600 | Table cells, paragraphs, form labels, long English content                                                              |
| **Script accent** | **Caveat**                   | 500, 700      | The handwritten taglines: "Empowered Staff. Happier Guests.", "Better Communication Brighter Careers", sticky-note text |
| **Arabic**        | **Cairo** (fallback Tajawal) | 400, 600      | Show Meaning panels, Arabic UI mode                                                                                     |

Closest alternates if you want a different feel: headings → Outfit or Plus Jakarta Sans; script → Kalam or Shadows Into Light.

### Type scale

| Token     | Size / line-height | Weight                | Family  | Example in mockups                               |
| --------- | ------------------ | --------------------- | ------- | ------------------------------------------------ |
| `display` | 34px / 1.2         | 700                   | Poppins | "Welcome, Mr. Ben Ali!"                          |
| `h1`      | 28px / 1.25        | 700                   | Poppins | "Admin Dashboard", "Pre-test & Post-test"        |
| `h2`      | 20px / 1.3         | 600                   | Poppins | Card titles: "Team Progress", "Questions"        |
| `h3`      | 16px / 1.4         | 600                   | Poppins | Sub-card titles, question prompt                 |
| `stat`    | 30px / 1.1         | 700                   | Poppins | "80", "62", "54%"                                |
| `body`    | 14px / 1.6         | 400                   | Inter   | Paragraphs, table cells                          |
| `body-sm` | 13px / 1.5         | 400                   | Inter   | Meta, timestamps, helper text                    |
| `label`   | 12px / 1.4         | 600, +0.02em tracking | Inter   | Form labels, table headers (often uppercase-ish) |
| `pill`    | 12px / 1           | 600                   | Inter   | Status badges                                    |
| `script`  | 20–26px            | 700                   | Caveat  | Taglines, sticky notes                           |

**Heading colour is `brand-700`, not black.** Every page title in the mockups is blue. Subtitles under them are `text-muted` 14px.

Employee-facing learning content runs one step larger (16px body, 18–20px for vocabulary and dialogue lines) because of the weak-English audience.

---

## 10.3 Spacing, radius, elevation

**Spacing scale (4px base):** 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.

- Card padding: 20px (24px for large panels)
- Gap between cards: 16px
- Page padding: 24px desktop, 16px mobile
- Sidebar width: 200px; topbar height: 72px

**Radii**

| Token          | Value | Use                                                 |
| -------------- | ----- | --------------------------------------------------- |
| `rounded-sm`   | 6px   | Inputs, small chips                                 |
| `rounded-md`   | 10px  | Buttons, table action buttons                       |
| `rounded-lg`   | 14px  | Cards, panels                                       |
| `rounded-xl`   | 20px  | Hero blocks, modals, image frames                   |
| `rounded-pill` | 999px | Status pills, language selector, avatar, icon chips |

**Shadows** — soft, blue-tinted, never grey-black:

```css
--shadow-card:
    0 1px 2px rgba(18, 35, 61, 0.04), 0 4px 16px rgba(11, 92, 255, 0.06);
--shadow-hover:
    0 4px 8px rgba(18, 35, 61, 0.06), 0 12px 28px rgba(11, 92, 255, 0.1);
--shadow-pop: 0 12px 40px rgba(18, 35, 61, 0.16); /* modals, dropdowns */
```

Cards use `border: 1px solid var(--border)` **plus** `--shadow-card`. Both, not one.

**Icon chips** (the coloured rounded squares next to stat numbers): 44×44px, `rounded-xl`, background = semantic tint, icon 22px in the semantic base colour.

---

## 10.4 Motion

The client asked for a lively, animated feel that is not childish. Rules:

| Interaction               | Spec                                                       |
| ------------------------- | ---------------------------------------------------------- |
| Page / step transition    | 220ms fade + 12px slide-up, `cubic-bezier(.22,.61,.36,1)`  |
| Card hover                | 150ms — lift 2px, shadow to `--shadow-hover`               |
| Button press              | 100ms scale to .97                                         |
| Progress bar / donut fill | 700ms ease-out on mount, animating from 0                  |
| Stat numbers              | count-up over 800ms on first paint                         |
| Correct answer            | green flash + check icon scale-in 300ms                    |
| Wrong answer              | 3-shake 260ms, red border, no harsh sound                  |
| Lesson complete           | trophy scale-in + a short confetti burst (≤1.2s, one time) |
| Audio playing             | pulsing ring on the speaker button                         |
| Skeletons                 | shimmer while loading, never a blank card                  |

Respect `prefers-reduced-motion: reduce` — drop to opacity-only fades.

---

## 10.5 Layout grid

**Desktop (≥1280px)** — the mockups are drawn at 1280×853:

- Fixed sidebar 200px + fluid content
- Content max-width 1400px, 24px gutters
- Dashboards: 5–6 stat cards in a row, then a 12-column grid (common splits: 4/4/4, 5/4/3, 8/4)
- Admin editors (lesson builder, test builder, scenario editor) are **3-column**: list/tree (300px) — editor (fluid) — preview/settings (320px)

**Tablet (768–1279px):** sidebar collapses to icons (72px); stat cards wrap 3-up; 3-column editors become 2-column with the preview panel moving under the editor.

**Mobile (<768px):** sidebar becomes a bottom tab bar (Home / Lessons / Phrasebook / Progress / Profile); every grid goes 1-column; stat cards become a horizontal scroll row; tables become stacked cards; the 3-column admin editors become tabbed sections.

Mobile is the priority surface for employees — see `11-components.md` §11.10.
