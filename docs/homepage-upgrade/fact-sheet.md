# Fact sheet — what the home page may state

Every figure on the page must trace to a source below. Anything else is removed or flagged.

| Claim on page | Source | Status |
|---|---|---|
| Product count ("N+ styles", "Products") | `Catalog::stats()['products']` — live count of visible products | Verified (computed) |
| Categories count | `Catalog::stats()['categories']` | Verified (computed) |
| Colours count | `Catalog::stats()['colours']` | Verified (computed) |
| Per-category product counts | `Catalog::discoverTiles()` counts | Verified (computed) |
| "States we ship to: 50" | `shipping_zones`: fallback zone "Contiguous United States" + zone "Alaska, Hawaii & territories" | Verified 2026-11-24 |
| Free-shipping threshold | Setting `shipping.free_threshold_minor` | Verified (computed) |
| Sale item price / % off | Live sale product prices | Verified (computed) |
| Payment methods (USDT, BTC, ETH) | Enabled payment methods | Verified |
| Hero banner subtitle "…made in our own facilities" | Admin banner text | **Unverified — owner to confirm or edit (Admin → Content → Banners)** |
| About text "began as a manufacturer and exporter trusted by global labels" | Admin home content (default text) | **Unverified — owner to confirm or edit (Admin → Content → Homepage)** |
| Assurances (shipping days, exchanges, support hours) | Admin home content | Owner-supplied |
