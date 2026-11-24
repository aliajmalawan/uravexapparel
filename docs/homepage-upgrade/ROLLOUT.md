# Rollout — Homepage upgrade

The site deploys from GitHub `master` to cPanel ("Update from Remote"). There is no feature-flag system, so each pass is a reversible unit of three files.

## Per-pass rollback

Before pass 1 the three touched files are copied to the session scratchpad (`home-rollback/`):
`app/Views/store/home.php`, `public/assets/css/app.css`, `public/assets/js/app.js`.

- Roll back a pass: restore those three files from the copy taken before that pass (or `git checkout -- <files>` for the whole upgrade, since nothing is committed by this work).
- Rehearsal: restore the pre-upgrade copy into a scratch directory and diff it byte-for-byte against `git show HEAD:<file>` — recorded in progress.md.

## Stages

1. **Local** (this session): passes applied and gated on XAMPP.
2. **Owner review**: the user checks the local home page.
3. **Go-live (owner action, needs explicit approval)**: user commits/pushes with GitHub Desktop and runs "Update from Remote" in cPanel. Rollback after go-live: revert the commit in GitHub Desktop and update from remote again.

Stop metric: any keep-working flow failing, console error, overflow, or the test suite going red.
