# CLAUDE.md — Guesvia

`AGENTS.md` is this repo's rulebook for every AI agent (Claude Code, Codex, Copilot…). It is imported below — follow it exactly.

## STRICT — design fidelity (AGENTS.md §0)

- **The approved mockups in `desginphotos/` are the specification.** Every screen matches its mockup exactly — layout, spacing, sizes, colours, typography, icons, images, copy — at 1280×853, and never overflows, clips or scrolls at smaller sizes (check 1240×698 and 390×844). "Close enough" is a defect.
- **The shared chrome is approved and locked** (`AppSidebar`, `NavMain`, `AppLogo`, `shell/AppTopbar`, `shell/PageHeader`, `shell/ScriptAccent`, `components/icons/*`). Don't restyle it unless the user explicitly asks; new screens reuse it.
- **Tokens only** (`@theme` in `resources/css/app.css`): no hex literals, no Tailwind built-in palette classes, never change an existing token's value. A mockup colour without a token → sample it, add a named token, report it.
- **Use the client's images** (`desgin/assets/` originals → processed copies in `public/brand/`, `public/decor/`), never placeholders. Never keep source assets in `public/build/`.
- **Verify before claiming done:** screenshot the running app at 1280×853, compare with the mockup region by region, fix every difference, report what remains. If you couldn't compare in a browser, say so.
- If the mockup can't be matched or seems wrong, **ask the user** — never redesign silently.

@AGENTS.md
