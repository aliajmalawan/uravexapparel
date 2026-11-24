# progress.md — Homepage upgrade

Repo protocol: OmniForge **v1.4.0** (pin v1.4.0, equal) — CHANGELOG top entry `[1.4.0] — 2026-11-24` (ui-ux-upscaler skill, architecture-leak rule, repo-use enforcement). Upgrade base: commit `01d7533`.

## Skills read before generating (FE-381)

- playbooks/ui-ux-upscaler.md
- BOOTSTRAP.md
- skills/frontend/ui-ux-upscaler/SKILL.md
- skills/frontend/design-intelligence/SKILL.md
- skills/frontend/motion/SKILL.md
- skills/frontend/scroll-experiences/SKILL.md
- skills/frontend/theme-library/SKILL.md (+ expanded/theme-092.md §1, rejected alternative)
- skills/frontend/typography/SKILL.md
- skills/frontend/layout-responsive/SKILL.md
- skills/frontend/design-tokens/SKILL.md
- skills/frontend/design-system/SKILL.md
- skills/frontend/component-library/SKILL.md
- skills/frontend/ui-states/SKILL.md
- skills/project-memory/project-templates/SKILL.md, progress-tracking/SKILL.md
- skills/security/secrets-management/SKILL.md
- skills/qa-testing/qa-gates/SKILL.md

Not read (not applicable): webgl-3d (no 3D at dial 3 on a shop home page — D-03); ai-retrofit, prototype-hardening (production app, presentation-only change).

## Done

- 2026-11-24 — Docs scaffolded: requirements.md, stack.md, fact-sheet.md, BASELINE.md, DECISIONS.md, UI-UPGRADE-AUDIT.md, ROLLOUT.md, TOKEN_MAP.md (this folder). Manifest note: the full project manifest (G-PM-001) is not generated — this is an upgrade inside an existing repo, not a new project; recorded not-applicable.
- 2026-11-24 — Baseline frozen (BASELINE.md, `evidence/before-*.jpg`); suite 540 passed.
- 2026-11-24 — G-FE-181 audit completeness: PASS — page scored (57/80), four defect classes listed, leak audit 0, baseline captured.
- 2026-11-24 — G-FE-161 decision record: PASS — DECISIONS.md D-02/D-03 (identity, layout, dial 3, both postures, rejected #92).
- 2026-11-24 — G-DO-151 rollback rehearsal: PASS — pre-upgrade copies byte-identical to `HEAD` (cmp).
- 2026-11-24 — G-SE-042: not affected (no secrets touched; `.env` remains git-ignored).

- 2026-11-24 — Pass 1 tokens: PASS — G-FE-022 literal lint 0 hits outside the token block (TOKEN_MAP.md).
- 2026-11-24 — Pass 2 typography: PASS — G-FE-071 computed sizes 12/14/16/18/21.3/28.4-37.9/40-67 px, 2 families (Manrope, Roboto); h1 4.21× body at 1440, 2.50× at 390.
- 2026-11-24 — Pass 3 layout: PASS — G-FE-061 overflow 0 at 320/390/768/1024/1440; strip replaces orphan grid (D-05).
- 2026-11-24 — Pass 4 components & states: PASS — G-EXP-05 axe-core 4.10.2 WCAG 2.2 AA 0 violations (1440, 390); G-FE-063 targets: hero links fixed, card hit area = whole card (hit-test).
- 2026-11-24 — Pass 5 motion: PASS — G-FE-041 reduced motion: 0 running animations, 0 hidden, counters final; G-FE-042 reduce block present; G-FE-046 one loop, idle 0 rAF/s, system cursor.
- 2026-11-24 — Pass 6 scroll: PASS — G-FE-056 observer removed → 0 hidden after watchdog; instant jump → 0 hidden; G-FE-051 JS off → 0 hidden (text 3482 chars); G-FE-053 scroll listeners passive and write-free.
- 2026-11-24 — Pass 7 polish: PASS — G-FE-184 8 signature elements, merge tests in UI-UPGRADE-AUDIT.md.
- 2026-11-24 — G-AN-101 performance: PASS with recorded cost — 4× throttle p50 33 ms, p95 33–83 ms (baseline 16.7 ms); D-08.
- 2026-11-24 — G-FE-185 regression: PASS — suite 540 passed; 29/29 links; keep-working flows; shop listing 54 checks, filters/sort/pagination; shop phones 24/24 cards.
- 2026-11-24 — G-FE-186 leak audit: PASS — AFTER leak score 0.
- 2026-11-24 — G-FE-187 / G-FE-162 final score: PASS — 68/80, all categories at threshold, hard categories 10.
- 2026-11-24 — Evidence: `evidence/after-*.jpg`.

## Next

- Owner review on localhost (owner: user).
- Owner decision on the two unverified claims (fact-sheet.md) (owner: user).
- Go-live: commit and push with GitHub Desktop, then "Update from Remote" in cPanel — needs the owner's explicit go-ahead (owner: user). Rollback: revert that commit and update from remote.

## OmniForge attestation (v1.4.0)

- Repo version used: 1.4.0 (pin v1.4.0) — cloned 2026-11-24 from github.com/sisuppreme/ai-all-in-one-company
- Skills read before generating: listed at the top of this file.
- Rules applied (rule — file:line — evidence):
  - FE-361 — skills/frontend/ui-ux-upscaler/SKILL.md:22 — audit + BEFORE 57/80 written before any UI change (UI-UPGRADE-AUDIT.md)
  - FE-363 — skills/frontend/ui-ux-upscaler/SKILL.md:24 — one identity, keep-and-deepen (DECISIONS.md D-02)
  - FE-370 — skills/frontend/ui-ux-upscaler/SKILL.md:31 — leak scan BEFORE 0 / AFTER 0
  - FE-372 — skills/frontend/ui-ux-upscaler/SKILL.md:33 — system cursor; extra perf pass (D-08)
  - FE-321 — skills/frontend/design-intelligence/SKILL.md:19 — decision record D-03 with rejected #92
  - FE-327 — skills/frontend/design-intelligence/SKILL.md:25 — 8 merged elements, merge table in the audit
  - FE-329 — skills/frontend/design-intelligence/SKILL.md:27 — AFTER 68/80 scored with evidence
  - FE-043 — skills/frontend/design-tokens/SKILL.md:21 — app.css:1735 token block; lint 0 hits
  - FE-142 — skills/frontend/typography/SKILL.md:20 — h1 4.21× (1440) / 2.50× (390) body
  - FE-123 — skills/frontend/layout-responsive/SKILL.md:21 — phones read story copy before frames
  - FE-062 — skills/frontend/component-library/SKILL.md:20 — button rest/hover/focus/active/disabled states
  - FE-091 — skills/frontend/motion/SKILL.md:33 — reveals, scrub, parallax, counters, line-draw, marquee, scale-in-frame, tilt, ambient (absences D-04)
  - FE-095 — skills/frontend/motion/SKILL.md:37 — app.js:492 single loop; app.js:422 change-guarded writes
  - FE-096 — skills/frontend/motion/SKILL.md:38 — app.css:2092 JS-gated armed state; app.js:477 watchdog
  - FE-097 — skills/frontend/motion/SKILL.md:39 — app.css:2133 reduced-motion static page
  - FE-111 — skills/frontend/scroll-experiences/SKILL.md:33 — lerped scroll target in the loop
  - FE-112 — skills/frontend/scroll-experiences/SKILL.md:34 — observer-off and instant-jump tests: 0 hidden
- UI: identity kept and deepened (no library theme adopted; rejected alternative skills/frontend/theme-library/expanded/theme-092.md); design-intelligence gate 68/80; AFTER leak score 0.
