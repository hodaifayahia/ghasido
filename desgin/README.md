# Guesvia — Design System & Front-end Kit

> **STRICT RULE — read `AGENTS.md` §0 first.** The approved mockups in `desginphotos/` are the pixel-exact specification, and the implemented tokens (`resources/css/app.css`) and approved shared chrome override anything in this folder. Where these notes disagree with the mockups or the implementation — heading colours, `lucide-vue-next`, the reconstructed SVG logo, "mockups are only a reference" — the notes are outdated. The client's real image originals are in `desgin/assets/`.

Everything in this folder is derived from the 20 mockup screens (admin dashboard, manager dashboard, employee pre-test flow, lesson builder, AI scenarios, reports). Colours were sampled directly from the image pixels; type, spacing and component rules were reconstructed by measuring the screens.

**Stack assumed:** Laravel 11 + Inertia/Vue 3 + Tailwind CSS + Vite.

## Files

| File                                               | What it is                                                                                                             |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| [10-design-system.md](10-design-system.md)         | Colours, typography, spacing, radii, shadows, motion — the token reference                                             |
| [11-components.md](11-components.md)               | Every component in the mockups, measured and specified (sidebar, stat card, pill, table, question card, step tracker…) |
| [12-assets.md](12-assets.md)                       | Full asset inventory: what images exist in the mockups, how to get/recreate them, naming and folder structure          |
| [13-design-prompts.md](13-design-prompts.md)       | Copy-paste prompts to keep any AI/dev output identical to this design                                                  |
| [14-laravel-vue-setup.md](14-laravel-vue-setup.md) | Project structure, install steps, how tokens plug into Tailwind, example components                                    |
| `tokens.css`                                       | CSS custom properties — drop into `resources/css/`                                                                     |
| `tailwind.config.js`                               | Tailwind theme wired to the tokens                                                                                     |
| `assets/logo-guesvia.svg`                          | Rebuilt logo mark (approximation — replace with the real file if the client has it)                                    |
| `assets/palm-island.svg`                           | The sidebar-footer palm/island line drawing                                                                            |
| `assets/wave-divider.svg`                          | Decorative wave used under headers                                                                                     |

## One thing to be clear about

I can't extract the photographs out of the mockup JPEGs as clean, reusable assets — they're baked into flattened renders at screenshot resolution, they're AI-generated stock, and cropping them would give you blurry images with UI overlapping. `12-assets.md` lists every photo the design needs, with a generation/sourcing prompt for each, so you can produce the real versions at proper resolution.

The vector pieces (logo, palm drawing, wave, icons) I've rebuilt as SVG — those are usable directly.

## The design in one line

Clean SaaS dashboard on a very light blue background, white cards with soft borders and generous radii, one vivid royal blue for every action, pastel-tinted icon chips for colour variety, a handwritten script accent for warmth, and a hospitality photo in every header.
