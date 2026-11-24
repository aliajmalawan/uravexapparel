# Token map — Home page (pass 1)

Source of truth: the `body { --hm-* }` block at the top of the "Home page v6 (hm-)" section of `public/assets/css/app.css`. Theme primitives (`--t-*`, `--gold-grad`) come from `body.theme-*` and are only referenced inside that block.

## Before → after

| Literal kind (home CSS block) | Before (old hx2/hp block) | After (component rules, outside the token block) |
|---|---|---|
| Hex colours | 122 | 0 |
| rgb/rgba colours | 40 | 0 |
| px values | 382 | 0 (media-query breakpoints excepted — CSS cannot read custom properties in `@media`) |
| Durations (ms/s) | 37 | 0 |
| Distinct font sizes | 26 | 7 rendered (12, 14, 16, 18, 21.3, 28.4/37.9, 40→67 px) |

Lint command (G-FE-022), run 2026-11-24 — zero hits:

```
sed -n '<components start>,<components end>p' public/assets/css/app.css | grep -v '@media' \
  | grep -noE '#[0-9A-Fa-f]{3,8}\b|[^-a-z(][0-9.]+px|[0-9]+ms|rgba?\('
```

## Token groups

| Group | Tokens | Role |
|---|---|---|
| Colour roles | `--hm-ink`, `--hm-body`, `--hm-muted`, `--hm-page`, `--hm-surface`, `--hm-soft`, `--hm-line`, `--hm-dark(-2,-3)`, `--hm-on-dark(-muted)`, `--hm-accent(-hi,-text,-ink)`, `--hm-sale` | text, surfaces, dark ground, gold accent (text-safe `--hm-accent-text` 6.3:1 on white) |
| Named gradients | `--hm-gold-grad` (emblem ring), `--hm-silver-grad` (emblem "U"), `--hm-ground`, `--hm-scrim`, `--hm-glow` | brand basis stated in the block |
| Spacing (8 pt) | `--hm-s-1…10` = 4/8/12/16/24/32/48/64/96/128, `--hm-hair`, `--hm-rule` | all gaps and paddings |
| Radius by role | `--hm-r-control/media/card/panel/pill` | controls, images, cards, panels, pills |
| Elevation by role | `--hm-e-card/raised/overlay/glow` | flat card, raised bar, overlays, gold CTA glow |
| Type scale (1.333) | `--hm-fs-cap/sm/base/lead/h3/h2/num/display`, leading and tracking tokens | every font size |
| Motion | `--hm-d-fast/base/slow/reveal/draw/ticker/ambient`, `--hm-ease-out/inout/pop`, `--hm-rise/shift/lift`, `--hm-step` | every duration, easing and travel |
| Component sizes | `--hm-target(-sm)`, `--hm-ico(-sm)`, `--hm-badge(-sm)`, `--hm-off`, `--hm-cat-w`, `--hm-thumb`, … | fixed component dimensions |
