# Decisions — Homepage upgrade

## D-01 (2026-11-24) — Scope is the home page only

The user asked to improve **only** the home page. Header, footer, shop, product, checkout and admin stay as they are. The `.hp-products` card component is shared with the shop listing, so its styles are kept compatible and the shop page is re-checked after each pass.

## D-02 (2026-11-24) — Identity: keep and deepen (ui-ux-upscaler §2, path A)

URAVEX already has a real identity: the black emblem with a metallic-gold ring and "V", a silver "U", and the black/gold/silver wordmark used across header, footer and emails. The upgrade deepens it — one token source, a modular type scale, a home motion and scroll system, signature elements built from the store's own content — instead of importing a library theme on top.

- Material language: the gold **seam** (stitching / thread) of apparel and the emblem's gold ring.
- **Rejected alternative:** theme #92 *Museum After Dark* (`skills/frontend/theme-library/expanded/theme-092.md`) — the nearest luxurious after-dark family. Rejected because its own spec says "Not for bright, high-volume retail (a museum shop bestseller grid wants day light and speed)" (§1, lines 100–102), and its darkness overlay would hide product colour, which shoppers need to judge.
- Conflict logged: the session protocol says "pick one theme from the library", while `ui-ux-upscaler` §2 says do not import a library theme on top of a live identity. Both are D3; the more specific skill for an existing app (`ui-ux-upscaler`) wins (BOOTSTRAP precedence tie rule).

## D-03 (2026-11-24) — Decision record (design-intelligence §1, G-FE-161)

| Decision | Choice |
|---|---|
| Brand personality / audience / content | Luxurious-leaning retail; US shoppers on phones; catalogue + conversion |
| Layout family | Catalogue grid with editorial blocks; product is the focal point of every product section |
| Motion dial | **3 (Alive)** — commerce caps at 3 (§6); nothing animates between the visitor and a buy/CTA |
| Colour posture | Deep onyx ground for hero and closing bands, metallic gold accent, silver/ivory on dark; clean white and soft neutral for catalogue sections |
| Typography posture | Manrope display + Roboto body (the two families already loaded); modular scale 1.333; tabular numerals for prices and figures |
| Rejected | #92 Museum After Dark (D-02); dial 4 pinned/cinematic hero (spectacle in a shopping flow costs trust, FE-328) |

## D-04 (2026-11-24) — Motion components not used, with reasons

- **Pinned sections:** no moment on a shop home page earns a pinned viewport; pinning would slow browsing (FE-113, FE-328).
- **Page transitions:** pages are server-rendered; a site-wide transition would change every page, which is out of scope (D-01).
- **Custom cursor:** system cursor only (FE-093).

## D-05 (2026-11-24) — Category tiles become a scrollable strip

Thirteen categories in a fixed grid left one orphan tile on its own row at desktop. The strip holds any number of categories without orphans, keeps every tile reachable (scroll, buttons, keyboard), and its scroll position is drawn by the gold seam beneath it.

## D-06 (2026-11-24) — Unverified copy is flagged, not rewritten

Two owner-editable claims cannot be verified (see fact-sheet.md). They are left as the owner wrote them and reported to the user, because rewriting a business's story is the owner's decision.

## D-07 (2026-11-24) — Upgrade docs live in `docs/homepage-upgrade/`

The playbook places `UI-UPGRADE-AUDIT.md` at the project root. Here all upgrade docs sit in one folder so they do not mix with the app's own root files; nothing else changes.

## D-08 (2026-11-24) — Performance budget: cut order applied

Measured with Chromium at 4× CPU throttle (scroll through the whole page): first build p50 ≈ 150–480 ms per frame. Profiling isolated the cause (custom property written to the header every frame, which re-styled the header and its menu). Fixes, in the motion skill's cut order: (1) the per-card "bob" loops were removed and the remaining ambient loops (hero glow/ring, ticker) run only while on screen; (2) the header progress moved to its own element written with transform only; (3) all loop writes are change-guarded and every frame reads before it writes. Result: p50 33 ms, p95 33–83 ms (baseline page with no motion: 16.7 ms). Recorded as the accepted cost of a dial-3 motion system.

## D-09 (2026-11-24) — Display size floor

The display step's minimum is 40 px (2.5 rem) instead of the modular 37.9 px so the hero headline stays ≥ 2.5× body on phones (typography FE-142).

## D-10 (2026-11-24) — Shared product card

The whole product card is now its product link (title link stretched over the card; wishlist button kept above it). Card tags moved onto the type scale (12 px). Both changes apply to the shop listing as well, which shares the component; the shop page was re-checked (54 page×width checks, filters, sort, pagination). The old rule hiding cards 5+ on phones is now scoped to the home page — the shop listing on phones shows all 24 products per page again.

## D-11 (2026-11-25) — Category progress rings removed (owner request)

The owner asked to remove the progress ring and its "% of the store" text from the category tiles. Removed from `home.php` and `app.css`; tiles now show the category name and product count only. Signature elements drop from 8 to 7 (still within 5–20); the offer ring keeps the shared arc styles. Re-checked: 0 overflow at 320–1440 px, 0 hidden content (JS off and reduced motion), 29/29 links, suite 540 passed.
