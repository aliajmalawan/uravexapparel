# UI upgrade audit — Home page

- App: URAVEX Store storefront · Page: `/` · Upgrade base: commit `01d7533`
- Audit date: 2026-11-24 · Auditor: Claude (OmniForge v1.4.0, ui-ux-upscaler playbook)
- Evidence: `evidence/before-*.jpg`, BASELINE.md

## BEFORE score (design-intelligence §5, worst width)

| # | Category | Score | Evidence |
|---|---|---|---|
| 1 | Hierarchy & focal order | 7 | Hero passes the 3-second test; below it most sections carry equal weight; category grid leaves an orphan tile on its own row at 1440 px |
| 2 | Element integration | 5 | Hero arch + floating product cards carry real products; no other element carries content; no signature system |
| 3 | Readability & contrast | 8 | Body text AA; small white counts on category photos sit on a light overlay start (risk on pale photos) |
| 4 | Motion smoothness | 6 | Only the hero has motion (CSS); no scroll-linked system, nothing to judge as smooth below the fold |
| 5 | Performance | 7 | 659 KB at 1440 px, all CSS animations transform/opacity; no rAF loop |
| 6 | Never-invisible safety | 10 | JS off and reduced motion: 0 hidden elements, full text (3099 chars) |
| 7 | Reduced motion | 10 | Hero keyframes live inside `prefers-reduced-motion: no-preference`; static page complete |
| 8 | Aliveness | 4 | Seven of nine sections are fully static (dead stretches) |
| | **Total** | **57 / 80** | **FAIL** (< 64; categories 1, 2, 4, 8 below threshold) |

## Defects

### Slop instances (anti-slop table rows)

- S-1 (row 6) Dead sections: promises, categories, new arrivals, about, offer, best sellers, delivery/sell, membership have no motion or ambient life.
- S-2 (row 2) Repeated block rhythm: new arrivals / best sellers / delivery / membership share the same padding and centred weight.
- S-3 Composition: category grid of 13 leaves one orphan tile on row 3 at 1440 px.
- S-4 (row 9 — check) Figures: all figures trace to live data (fact-sheet); no invented counters found. OK.

### Missing systems

- M-1 No token source for the home page: 122 hex, 40 rgb/rgba, 382 px and 37 duration literals in the home CSS block (TOKEN_MAP.md).
- M-2 No type scale: 26 distinct font sizes in the home CSS block, many off any scale (.68, .7, .72, .76, .78, .82, .86, .88, .92 rem…).
- M-3 No motion system beyond the hero (no reveals, counters, line-draw, parallax, marquee, scale-in-frame).
- M-4 No scroll system (no progress, no scroll-linked values, no smoothing loop).
- M-5 Incomplete states: no visible focus ring style specific to home buttons on dark bands; image loading has no placeholder.
- M-6 Signature elements: 0 merged.

### Hard-rule violations

- H-1 Two hero floating product links are 18 px tall (< 24 px target floor).
- No heavy cursor, no stranded reveals, no layout-property animation found.

### Content truth (Real Content Rule)

- C-1 Hero banner claims "made in our own facilities" — unverified (owner).
- C-2 About text claims "began as a manufacturer and exporter trusted by global labels" — unverified (owner).

## Leak audit (G-FE-186)

Walked: all visible text, titles, aria-labels, placeholders and alt text on the home page at 390 px and 1440 px, including the JS-off render.

| Class | BEFORE count |
|---|---|
| B-1 DB names/types | 0 |
| B-2 Table/collection names, storage terms | 0 |
| B-3 Page/route counts or structures | 0 |
| B-4 API endpoints | 0 |
| B-5 Internal module/service names | 0 |
| B-6 Tech-stack labels | 0 |
| B-7 Schema/field names | 0 |
| B-8 Status codes / technical errors | 0 |
| **BEFORE leak score** | **0** |

## Structural changes (justified before touching)

- Category grid → horizontal strip with seam progress (D-05): removes the orphan row for any category count; no content removed, every tile reachable.

## Deliberately unchanged

Header, footer, shop/product/checkout/admin pages (D-01); owner-written copy (D-06); data and imported listings.

## Signature elements — merge test (G-FE-184)

| # | Element | Carries content (delete-test) | Brand material | Lives in layout | Page physics |
|---|---|---|---|---|---|
| E1 | Hero product arch + "N+ styles in store" counter | Featured product, its category, price; the live product count | Gold emblem ring as frame, silver headline | Own grid column; tag inside frame | Tilt on the shared loop; counter real (data-count) |
| E2 | Header seam = reading progress | Position in the page | The gold line under the logo | Header's own bottom edge | Lerped scroll progress, one loop |
| E3 | Category share arcs | Each category's share of the store (count / total) | Gold ring | Tile corner, beside the text it measures | Draws on reveal |
| E4 | Category strip seam | How much of the category list is in view | Gold seam | Under the strip | Bound to the strip's scroll |
| E5 | Store figures counters | Products, categories, colours, states (all sourced) | Gold display numerals | Story column | Count once on reveal; final value without JS |
| E6 | Offer ring | The deal's real % off | Ring in sale red | Offer media corner | Draws on reveal |
| E7 | Category ticker | Every category with its count, as links | Gold stitch dots, display type | Its own band | One marquee; pausable; on-screen only |
| E8 | Hero floating product cards | Two more real products with prices | White cards on the gold arch | Edges of the arch column | Parallax on the shared loop |

8 elements (5–20 required). All four tests hold for each; with the script off, E1/E3/E5/E6 show their final values and E7 becomes a static scrollable list.

## Closing summary (2026-11-24)

| # | Category | BEFORE | AFTER | Evidence |
|---|---|---|---|---|
| 1 | Hierarchy & focal order | 7 | 8 | 3-second hero test passes at 1440/390; strip removes the orphan row; phones read story copy first; duplicate hero assurances removed on phones |
| 2 | Element integration | 5 | 8 | 8 merged elements above; floats overlap the arch photo's edge by design (only deduction) |
| 3 | Readability & contrast | 8 | 9 | axe-core WCAG 2.2 AA: 0 violations at 1440 and 390; scrim strengthened under category text |
| 4 | Motion smoothness | 6 | 8 | One rAF loop, lerped scroll, transform/opacity only, change-guarded writes, idles at 0 calls |
| 5 | Performance | 7 | 7 | 4× throttle p50 33 ms / p95 33–83 ms vs 16.7 ms baseline (D-08); transfer 455 KB at 1440 (was 659 KB) |
| 6 | Never-invisible safety | 10 | 10 | JS off: 0 hidden; observer removed: 0 hidden after watchdog; instant jump: 0 hidden |
| 7 | Reduced motion | 10 | 10 | 0 running animations, counters final, 0 hidden, header seam off |
| 8 | Aliveness | 4 | 8 | Every section has choreographed entrance + scroll-aware life (seam, parallax, counters, arcs, ticker) |
| | **Total** | **57 / 80 FAIL** | **68 / 80 PASS** | all thresholds met; hard categories 10 |

**Leak score:** BEFORE 0 → AFTER 0 (new copy, labels, aria-labels, placeholders and alt text re-scanned).

### Defect status

- S-1 dead sections — fixed (pass 5–7).
- S-2 repeated rhythm — fixed (head rows with actions, varied section composition; soft/dark/white pacing).
- S-3 orphan category tile — fixed (D-05 strip).
- M-1 tokens — fixed (TOKEN_MAP.md, lint 0).
- M-2 type scale — fixed (7 rendered sizes, 2 families, h1 ≥ 2.5× body).
- M-3 motion system — fixed; not used with reasons: pinned sections, page transitions, custom cursor (D-04); per-card bob removed for budget (D-08); skeleton: product images get a soft placeholder surface while loading.
- M-4 scroll system — fixed (one loop, progress seam, parallax, reveals with watchdogs).
- M-5 states — fixed (focus rings on every control, active/disabled button states, strip buttons disabled at the ends, ticker pause state announced).
- M-6 signature elements — fixed (8).
- H-1 small hero links — fixed (floats ≥ 68 px tall). The product-title anchors measure 19 px on their own, but their hit area is the whole card (hit-test verified, D-10).
- C-1, C-2 unverified copy — **left for the owner** (D-06).
- Extra fixes found on the way: shop listing on phones hid 20 of 24 products (fixed, D-10); hero count badge was hidden from screen readers (now readable).

### Left unchanged

Header/footer markup, other pages, owner copy, data (D-01, D-06).
