# Launch Checklist

## Verified working (local XAMPP, PHP 8.2.12, MariaDB 10.4.32)

Automated: `php bin/console.php check` — 182 PHP files lint clean; **249 tests pass** (unit, integration on a
real test database, in-process HTTP). Browser QA with Playwright at 360/390/768/1024/1440 px: no horizontal
overflow, touch targets ≥ 24 px, no console/CSP errors across 24 storefront and admin pages; full guest
sandbox purchase → webhook confirmation → admin fulfilment → customer tracking completed in a real browser.

- [x] Storefront: home (admin-editable hero/promo/story/assurances), shop, new arrivals, sale, search,
      collections, categories, filters (size, colour, price, availability), sorting, pagination, empty states
- [x] Product pages: gallery, variants with unavailable-size handling, stock messaging, sale pricing,
      care/shipping accordions, related products, Product/Offer/Breadcrumb structured data
- [x] Cart (guest + account, merge on login capped at stock), coupons, free-delivery meter, wishlist
- [x] Checkout with server-side recalculation, change detection, idempotency, stock locking, saved addresses
- [x] COD, bank transfer (reference + private receipt upload + staff verification), sandbox online payment
- [x] Accounts: register, login (rate limited), email verification, forgot/reset (hashed single-use 60-min
      tokens), profile, password change (revokes other sessions), addresses, orders, tracking, wishlist
- [x] Admin: dashboard analytics, products/variants/images, CSV import (preview, row errors, all-or-nothing)
      and export, categories, collections, inventory ledger + adjustments, orders (status machine, shipping
      with tracking, COD/bank verification, refunds with restock, invoices, packing slips, CSV export),
      customers (lifetime spend, masked export), coupons, content (announcement, homepage, banners, pages,
      FAQs, footer, social, SEO), settings (checkout, payments, shipping zones/rates, couriers), staff roles,
      reports with CSV exports, audit log, contact messages, newsletter export
- [x] Security: Argon2id, CSRF + Origin check, CSP/HSTS/frame headers, role permissions on every route,
      upload re-encoding + no PHP execution in uploads, log redaction, audit trail, private paths blocked
- [x] SEO: titles/descriptions, canonical URLs, Open Graph, sitemap.xml, robots.txt (blocks all in non-production), 404/500 pages

## Before launch — your actions

- [ ] **Run `php bin/console.php demo:purge`** to remove demo products, customers, orders, banners, coupons.
- [ ] Add real products with real photography (the demo images are generated illustrations).
- [ ] Review and edit the policy templates (Shipping, Returns, Privacy, Terms) with legal advice — they are
      marked "Template — review before launch".
- [ ] Set real shipping prices/zones (Admin → Settings → Shipping) and courier tracking URL templates.
- [ ] Enter bank details and enable bank transfer if you accept it; set COD limits.
- [ ] Update store contact details, social links, announcement and SEO defaults (Admin → Content).
- [ ] Production `.env` (see DEPLOYMENT.md): HTTPS URL, strong secrets, least-privilege DB user, SMTP.
- [ ] Configure SPF/DKIM/DMARC; send a test order email to Gmail and Outlook.
- [ ] Schedule `cron` (every 5 minutes) and `backup` (daily) and copy backups off-server; test a restore.
- [ ] Create staff accounts with the least privilege needed; change the owner's temporary password.
- [ ] Verify `/.env`, `/app/bootstrap.php`, `/storage/` return 403/404 on the live domain.
- [ ] Submit `https://<domain>/sitemap.xml` to Google Search Console.

## Requires external credentials or approval (not built)

- [ ] Safepay / PayFast / JazzCash / Easypaisa / international card payments — need merchant accounts and
      official API documentation; see `docs/PAYMENTS.md`. Until then only COD and bank transfer are live.
- [ ] Courier API integrations (automatic booking/tracking) — currently manual tracking numbers with
      optional tracking-link templates.
- [ ] SMTP provider account for transactional email.
- [ ] Off-site object storage for backups/images (optional; local persistent disk is supported).

## Known limitations

- Product listing price filters/sorting use product-level prices (variant price overrides apply on the
  product page, cart and checkout).
- Business timezone conversion for daily charts uses the zone's current offset (correct for Pakistan,
  which has no DST).
- Product reviews are not implemented (optional in the specification).
