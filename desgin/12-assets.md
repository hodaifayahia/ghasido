# 12 — Asset Inventory & Sourcing

## 12.1 What I can and can't hand over

**Included as real, usable files** (in `assets/`):

| File               | What it is                                                                                                                                                                                                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `logo-guesvia.svg` | The palm/sun/wave badge mark, rebuilt as vector. Pair it with the "Guesvia" wordmark in Poppins 700 `#1249C9` and the sub-line "English for Hotel Staff" in Inter 11px `#64748B`. **This is a close reconstruction — if the client has the original logo file, use theirs.** |
| `palm-island.svg`  | The line-drawn palm island at the bottom of the sidebar                                                                                                                                                                                                                      |
| `wave-divider.svg` | Soft wave divider for section breaks and the certificate footer                                                                                                                                                                                                              |

**Not included:** the photographs. They're baked into flattened 1280×853 JPEGs with UI drawn on top, so cropping produces low-resolution images with buttons and text stuck in them. They also appear to be AI-generated stock, so there's no original to recover. §12.3 lists every photo the design needs with a generation prompt, so you can produce clean, high-resolution versions.

**Icons:** don't create these by hand — install `lucide-vue-next`. The mapping is in `11-components.md` §11.9.

---

## 12.2 Folder structure

```
public/
├─ brand/
│  ├─ logo-guesvia.svg
│  ├─ logo-guesvia-wordmark.svg
│  ├─ favicon.svg  favicon-32.png  apple-touch-icon.png
│  └─ og-image.png                    (1200×630 social card)
├─ decor/
│  ├─ palm-island.svg
│  ├─ wave-divider.svg
│  ├─ header-palms.webp               (header strip, right side)
│  ├─ header-facade.webp              (alternate header strip)
│  ├─ desk-sign.webp                  (bottom-right employee decoration)
│  └─ blurred-lobby.webp              (background wash on test screens)
├─ illustrations/
│  ├─ trophy.svg  certificate.svg  empty-state.svg  no-results.svg
│  └─ confetti.json                   (Lottie, optional)
└─ content/                           (CMS uploads — see 12.4)
   ├─ lessons/  vocabulary/  scenarios/  tests/  covers/
```

Ship a `.webp` plus a `.jpg` fallback for every photo. Serve `@1x` and `@2x`.

---

## 12.3 Photo shot list

Every photo visible in the mockups. Style rules for all of them: warm natural light, shallow depth of field, real hotel interiors, modern and professional, **adults in hotel uniform, never staged clip-art**, no visible brand logos, no text in the image. Target a consistent blue-warm grade so the set looks like one library.

### Chrome / decoration

| File                     | Size     | Description                                                                                                                |
| ------------------------ | -------- | -------------------------------------------------------------------------------------------------------------------------- |
| `header-palms.webp`      | 600×160  | Palm fronds against a bright sky, right-weighted composition — fades into white behind the topbar                          |
| `header-facade.webp`     | 600×160  | Moorish/Andalusian hotel façade with arches and palms, sunlit                                                              |
| `blurred-lobby.webp`     | 1600×900 | Reception desk with a gold service bell, heavily blurred, used at ~25% opacity behind employee test screens                |
| `desk-sign.webp`         | 400×260  | A small black desk sign on a reception counter — the "Small Steps Brighter Careers" plaque                                 |
| `hero-receptionist.webp` | 900×1100 | Smiling female receptionist in a dark uniform, arm extended in welcome, "Welcome" signage behind — the Pre-test intro hero |
| `lesson-complete.webp`   | 800×1000 | Receptionist giving a thumbs-up, warm hotel background — the Lesson Complete card                                          |

### Lesson / course covers

| File                          | Description                                              |
| ----------------------------- | -------------------------------------------------------- |
| `covers/greeting-guests.webp` | Welcome note card and a gold bell on a reception counter |
| `covers/check-in.webp`        | Guest handing a passport across the reception desk       |
| `covers/restaurant.webp`      | Waiter serving at a set table in a hotel restaurant      |
| `covers/housekeeping.webp`    | Housekeeper with a linen trolley in a corridor           |
| `covers/spa.webp`             | Rolled towels and candles in a spa treatment room        |
| `covers/kitchen.webp`         | Chef plating food in a professional kitchen              |
| `covers/telephone.webp`       | Receptionist on the phone at the desk                    |

### Test / scenario question images

| File                            | Description                                                                                                      |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `tests/guest-luggage.webp`      | Male guest with a large suitcase at the reception desk, receptionist smiling                                     |
| `tests/check-out.webp`          | Guest handing a card over at check-out, "Enjoy your stay" signage                                                |
| `tests/unhappy-guest-room.webp` | Female guest in a doorway (room 418) speaking to housekeeping, unhappy expression                                |
| `tests/no-smoking-sign.webp`    | A "No Smoking" notice on a hotel wall                                                                            |
| `tests/spa-notice.webp`         | A printed "Spa & Wellness — closed for maintenance" notice                                                       |
| `tests/reception-sign.webp`     | A "Reception" desk sign with flowers and a bell                                                                  |
| `tests/dialogue-1..4.webp`      | Four frames of one guest↔receptionist exchange (asking, answering, thanking, replying) for the ordering activity |

### AI scenario thumbnails

`scenarios/guest-check-in.webp`, `room-service.webp`, `spa-information.webp`, `handling-complaint.webp`, `giving-directions.webp`, `lost-item.webp`, `special-requests.webp` — 1200×628, one clear situation each.

### Vocabulary images (the biggest set)

One image per vocabulary item, 800×450, subject centred, uncluttered background. Start with roughly 60–100 covering: reception desk, key card, passport, luggage, lobby, lift, corridor, towel, bed, pillow, minibar, breakfast buffet, menu, bill/invoice, receipt, wake-up call, safe, air-conditioning, shuttle, reservation, late check-out, room service tray, laundry bag, spa robe, swimming pool, gym, Wi-Fi router, umbrella, taxi, city map.

### Generation prompt template

If you generate these with an image model, reuse this so the whole library stays consistent:

> Professional hospitality photography, {subject}, modern four-star hotel interior, warm natural window light, shallow depth of field, 35mm, realistic adult staff in dark hotel uniform with a gold name badge, friendly professional expression, clean uncluttered composition with copy space on the {left/right}, soft blue-and-warm colour grade, no text, no logos, no watermark, photorealistic, 4k.

If you licence instead of generate: Unsplash and Pexels cover hotel/reception/housekeeping well and allow commercial use; keep a `credits.md` noting the source and licence of every file.

---

## 12.4 Image rules in the CMS

- Accepted: JPG, PNG, WebP; max 5MB (this limit is already shown in the mockups)
- On upload, generate: thumb 320w, card 800w, cover 1600w — all WebP, served with `srcset`
- Recommended sizes shown to the admin in the UI: cover/banner **1200×628**, vocabulary **800×450**, scenario **1200×628**, question image **800×600**
- Every upload requires alt text
- Store under `storage/app/public/content/{type}/{yyyy}/{mm}/` with a UUID filename; never trust the original filename
- The Image Library's three tabs map to: **My Images** (this admin's uploads), **Guesvia Library** (a curated shared set the client builds once and reuses across hotels), **Icons & Stickers** (SVG decorations, badges, arrows, highlight marks)

---

## 12.5 Certificate assets

`certificate-bg.svg` (A4 landscape, light blue border with the wave motif), `seal.svg` (gold circular seal), `signature-line.svg`. Build the PDF with the same tokens — heading in Poppins `#1249C9`, the recipient name in Caveat 44px, wave divider at the foot.
