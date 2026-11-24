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
