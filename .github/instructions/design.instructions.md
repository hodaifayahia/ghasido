---
applyTo: 'resources/js/**,resources/css/**,resources/views/**,public/brand/**,public/decor/**'
---

# Design fidelity — STRICT (AGENTS.md §0)

You are editing Guesvia UI. These rules are mandatory:

- Match the approved mockup in `desginphotos/` **exactly** (1280×853): layout, order, spacing, sizes, colours, typography, icons, images, copy. Measure the image; don't eyeball it. Approximations are defects.
- Nothing may overflow, clip or scroll at smaller sizes (check 1240×698 and 390×844).
- The shared chrome is approved and **locked**: `AppSidebar.vue`, `NavMain.vue`, `AppLogo.vue`, `shell/AppTopbar.vue`, `shell/PageHeader.vue`, `shell/ScriptAccent.vue`, `components/icons/*`. Don't change it unless the user explicitly asks; reuse it for new screens.
- Colours/fonts/radii/shadows only from the `@theme` tokens in `resources/css/app.css`. No hex literals, no Tailwind built-in palettes, never edit an existing token value. A new mockup colour → sample it, add a named token, report it.
- Page titles via `PageHeader`, cards via `common/PanelCard`, icons from `@lucide/vue` or the solid SVGs in `components/icons/`.
- Use the client's images (`desgin/assets/` → `public/brand/`, `public/decor/`); never placeholders, never `public/build/`.
- If you can't match the mockup, ask. Before saying done: compare a screenshot with the mockup, list remaining differences, and say so if you couldn't run a browser.

Full rule, measured values and the mockup index: `AGENTS.md` §0.
