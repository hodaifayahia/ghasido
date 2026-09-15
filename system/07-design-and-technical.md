# 07 — Design Direction & Technical Requirements

## 7.1 Visual and interaction direction

| ID    | Requirement                                                                                                                                                                                                                                                                                                                                       |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| UX-01 | Good, pleasant colours; a clear visual identity for Guesvia.                                                                                                                                                                                                                                                                                      |
| UX-02 | **Animated and lively** — transitions between pages and between exercises, motion feedback on answers. Not dry or static.                                                                                                                                                                                                                         |
| UX-03 | **Not childish.** The learners are adults working in hotels. Professional, warm, modern — the energy of a good mobile app, not a kids' game.                                                                                                                                                                                                      |
| UX-04 | The goal of the motion and colour is to hold attention and prevent boredom, not decoration for its own sake.                                                                                                                                                                                                                                      |
| UX-05 | Micro-feedback on interaction: correct/incorrect states, progress filling, small celebratory moments at lesson completion (badges, progress rings).                                                                                                                                                                                               |
| UX-06 | Simple, predictable navigation for all three roles. Low cognitive load; one clear action per screen.                                                                                                                                                                                                                                              |
| UX-07 | Icons and images carry meaning wherever text can be reduced.                                                                                                                                                                                                                                                                                      |
| UX-08 | The mockups the client provided illustrate structure, organisation and UX — they are a reference, **not a pixel-for-pixel specification**, and charts/details in them need not be copied literally. **Superseded by client decision 2026-09-15: the approved mockups in `desginphotos/` are the pixel-exact specification — see `AGENTS.md` §0.** |

---

## 7.2 Responsiveness — mobile is the priority

| ID      | Requirement                                                                                                                                                                                                            |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RESP-01 | Fully responsive website; **most employees will use a phone**, so the phone layout must feel like a native app: bottom navigation, large tap targets, thumb-reachable controls, no horizontal scrolling, no tiny text. |
| RESP-02 | The desktop layout is used mainly by Admin and Manager; the admin panel and the builder must work well on a larger screen.                                                                                             |
| RESP-03 | Everything the admin builds in the drag & drop builder must render correctly on mobile — layouts collapse predictably.                                                                                                 |
| RESP-04 | Audio and video players are mobile-friendly and work on iOS Safari and Android Chrome.                                                                                                                                 |
| RESP-05 | Voice recording works from a mobile browser (with a clear microphone-permission flow and a fallback message if denied).                                                                                                |
| RESP-06 | _(Suggested)_ Installable as a PWA with an app icon on the home screen — gives the "it's an app" feel without app-store work.                                                                                          |

---

## 7.3 Language and direction

| ID      | Requirement                                                                                                                                                                                                |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| I18N-01 | Learning content is in **English**; Arabic appears only through Show Meaning.                                                                                                                              |
| I18N-02 | The interface language (menus, buttons, instructions) should be switchable — English and Arabic — since some employees have a weak level. RTL layout support is needed if the Arabic interface is enabled. |
| I18N-03 | Arabic text must render correctly (font, shaping, direction) inside Show Meaning panels even when the surrounding interface is LTR.                                                                        |

---

## 7.4 Performance

| ID      | Requirement                                                                                              |
| ------- | -------------------------------------------------------------------------------------------------------- |
| PERF-01 | Pages must load quickly on mobile data. Images compressed, audio pre-generated and cached.               |
| PERF-02 | Lazy-load media; do not download a whole lesson's audio before the first step is usable.                 |
| PERF-03 | The app must handle intermittent connectivity: submitted answers are not lost, and a retry is attempted. |
| PERF-04 | AI responses should show a visible in-progress state, since model calls take a few seconds.              |

---

## 7.5 Security

| ID     | Requirement                                                                                                                               |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| SEC-01 | Authorisation enforced server-side on every request — an employee cannot read another department's or hotel's content by URL or API call. |
| SEC-02 | Passwords hashed; sessions expire; login rate-limited.                                                                                    |
| SEC-03 | API keys server-side only.                                                                                                                |
| SEC-04 | Uploaded files validated by type and size; media served from a controlled path.                                                           |
| SEC-05 | HTTPS everywhere; regular automated backups of the database and media, with a tested restore procedure.                                   |
| SEC-06 | Audit log for admin actions on accounts, content and data exports.                                                                        |

---

## 7.6 Accessibility & usability for weak readers

| ID     | Requirement                                                                    |
| ------ | ------------------------------------------------------------------------------ |
| ACC-01 | Large readable typography; strong colour contrast.                             |
| ACC-02 | Never rely on colour alone to signal correct/incorrect — add an icon and text. |
| ACC-03 | Tap targets at least ~44px on mobile.                                          |
| ACC-04 | Audio available for instructions where possible, not just for content.         |
| ACC-05 | Alt text on instructional images.                                              |

---

## 7.7 Delivery notes

- Environments: development / staging / production, so the client can review before employees see changes.
- Seed/demo data so the client can try the admin panel with realistic content.
- A short **admin user guide** (or screen-recorded walkthrough) for the CMS, builder, test creation, AI scenarios and export — the client will operate this alone.
- Handover: source code, deployment instructions, database schema, and documentation of where each API key is configured.
