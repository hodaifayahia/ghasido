# Copilot instructions — Guesvia

This repo's full rulebook for every AI agent is **`AGENTS.md`** at the repository root. Read it before making changes; where anything below is shorter, `AGENTS.md` is the authority.

## STRICT RULE — design fidelity (AGENTS.md §0)

This rule is not optional and applies to every suggestion, chat answer and coding-agent change that touches UI (Vue pages/components/layouts, CSS, Blade views, emails).

1. **The approved mockups in `desginphotos/` are the specification.** Every screen must match its mockup exactly — layout, element order, spacing, sizes, colours, typography, icons, images, decoration and copy — at the 1280×853 reference size. "Close enough", "inspired by", "cleaner" or "modernised" versions are defects.
2. **Never overflow.** At smaller windows (for example 1240×698, a 125%-scaled laptop, and 390×844 mobile) nothing may clip, wrap unexpectedly, scroll horizontally, or make the sidebar scroll; spacing and decorations scale down instead.
3. **The shared chrome is approved and locked:** `resources/js/components/AppSidebar.vue`, `NavMain.vue`, `AppLogo.vue`, `shell/AppTopbar.vue`, `shell/PageHeader.vue`, `shell/ScriptAccent.vue`, `components/icons/*`, `layouts/app/AppSidebarLayout.vue`. Do not restyle, re-space, re-colour or re-order them unless the user explicitly asks. New screens reuse them through `AppLayout`; never copy or fork them.
4. **Design tokens only** — the `@theme` blocks in `resources/css/app.css`. No hex/rgb literals in components, no Tailwind built-in palette classes (`blue-500`, `gray-*`, `indigo-*`…), and never change an existing token's value. If a mockup colour has no token, sample it from the image, add a named token with a comment naming the mockup, and say so.
5. **Page titles** come from `PageHeader` (Poppins Bold 28px, −0.02em, `text-ink-royal`, subtitle Inter 16px `text-ink-slate`); **cards** from `common/PanelCard`.
6. **Icons:** `@lucide/vue` when the glyph matches the mockup; solid or missing glyphs become small SVG components in `resources/js/components/icons/`. No emoji in chrome, no other icon packages.
7. **Use the client's images** — originals in `desgin/assets/`, processed copies in `public/brand/` and `public/decor/`. Never substitute placeholders or stock images, and never store source assets in `public/build/` (every build empties it).
8. **No unrequested redesign.** If a mockup looks wrong, inconsistent, or impossible to reproduce, ask — do not decide silently.
9. **Verify before saying done:** run the app, screenshot the screen at 1280×853, compare it with the mockup region by region, fix every difference, re-check 1240×698 and 390×844, run `npm run check` and `npm run types:check`, and list any remaining difference. If you could not compare in a browser, say so explicitly.

## Stack essentials

Laravel 13 + Inertia v3 + Vue 3 (`<script setup lang="ts">`) + Tailwind CSS v4 (CSS-first, **no** `tailwind.config.js`) + Wayfinder routes (never hand-written URLs). Don't install packages without asking. `resources/js/components/ui/*` is vendored shadcn-vue — don't edit it. Full conventions: `AGENTS.md` §3–§8.
