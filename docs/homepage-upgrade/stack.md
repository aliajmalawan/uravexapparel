# Stack — Homepage upgrade

- Server: PHP 8.2, plain MVC (front controller `public/index.php`), MySQL/MariaDB via MySQLi; XAMPP locally, cPanel live.
- View: `app/Views/store/home.php` rendered inside `app/Views/layouts/store.php`.
- Styles: one stylesheet `public/assets/css/app.css` (layered; later rules win). Theme primitives come from `body.theme-*` variables (`--t-*`, `--gold-grad`).
- Scripts: `public/assets/js/app.js` (deferred, external — required by CSP).
- Security headers: strict CSP — `script-src 'self'`, `style-src 'self' fonts.googleapis.com`; no inline `<script>` and no `style=""` attributes in markup. CSSOM writes from the external script are allowed.
- Fonts: Manrope (display) + Roboto (body), Google Fonts, `display=swap`.
- QA tooling: `php bin/console.php check` (lint + unit/integration/HTTP tests); Playwright 1.56 scripts in the session scratchpad for screenshots, overflow, JS-off, reduced-motion, link and contrast checks.
- No secrets are touched by this work.
