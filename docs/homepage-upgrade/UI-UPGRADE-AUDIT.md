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

## Closing summary

(Filled at the end of the upgrade.)
