# Baseline — frozen before any change (2026-11-24)

Upgrade base: commit `01d7533`. Captured with Playwright 1.56 (Chromium) against `http://localhost/uravexapparel/`.

## Test suite

`php bin/console.php check` → **540 passed, 0 failed** (lint 0 errors).

## Rendering matrix (home page)

| Viewport | Overflow px | Page height | JS errors | Hidden text/media | Targets < 24 px | Transfer |
|---|---|---|---|---|---|---|
| 1440×900 | 0 | 6268 | 0 | 0 | 3 | 659 KB |
| 1024×768 | 0 | 7695 | 0 | 0 | 3 | 483 KB |
| 768×1024 | 0 | 8604 | 0 | 0 | 3 | 398 KB |
| 390×844 | 0 | 8571 | 0 | 0 | 2 | 297 KB |
| 320×640 | 0 | 8619 | 0 | 0 | 1 | 310 KB |

- JavaScript disabled: 0 hidden elements, main text length 3099 characters.
- Reduced motion: 0 hidden elements, main text length 3099 characters.
- Links in `<main>`: 29 unique, 0 broken.
- Undersized targets at 390 px: the two floating hero product links (18 px tall).

Evidence: `evidence/before-*.jpg` (full-page captures at each width, plus JS-off and reduced-motion first viewports).

## Critical flows (keep-working list, see requirements.md)

1. Home → hero "Shop now" → `/shop` (200).
2. Home → category tile → `/category/<slug>` (200).
3. Home → product card → product page (200).
4. Home → "View all" (new arrivals) → `/new-arrivals` (200).
5. Guest membership block shows Sign up / Sign in.
6. Shop page product cards share the `.hp-products` card styles — re-checked after every pass.
