# URAVEX Apparel — E-commerce Platform

Premium storefront and secure admin for URAVEX Apparel, built with **plain PHP 8.1+ and MySQLi**
(no framework, no Composer dependencies) on **MySQL 8 / MariaDB 10.4+**. Designed to run on XAMPP for
development and on any standard PHP host or VPS in production.

- Storefront: home, shop and collection pages with filters, search, product pages with variants, cart,
  wishlist, checkout (COD, bank transfer, sandbox test gateway), order tracking, customer accounts.
- Admin (`/admin`): analytics dashboard, products and variants, multiple image uploads, CSV import/export,
  categories, collections, inventory ledger, orders and fulfilment, refunds, invoices and packing slips,
  customers, coupons, site content, shipping and payment settings, reports, staff roles, audit log.

See `docs/` for deployment, payments, backups and the launch checklist.

---

## 1. Local setup (XAMPP on Windows)

Requirements: XAMPP with PHP ≥ 8.1 (8.2 tested) and MariaDB/MySQL, Apache `mod_rewrite` enabled and
`AllowOverride All` for `htdocs` (XAMPP's default). PHP extensions: `mysqli`, `gd` (with WebP),
`fileinfo`, `mbstring`, `openssl`, `exif` (optional).

```powershell
cd C:\xampp\htdocs\uravexapparel
C:\xampp\php\php.exe bin\console.php setup          # creates .env with generated secrets (root DB user for local only)
# Create the database if it does not exist (utf8mb4):
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS uravexapparel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\php\php.exe bin\console.php migrate
C:\xampp\php\php.exe bin\console.php seed:base       # shipping zones, couriers, policy page templates, FAQs
C:\xampp\php\php.exe bin\console.php seed:demo       # OPTIONAL demo catalogue/orders (flagged is_demo)
C:\xampp\php\php.exe bin\console.php user:owner you@example.com "Your Name"   # prints a one-time password
```

Open **http://localhost/uravexapparel** (store) and **http://localhost/uravexapparel/admin** (admin).

Emails in development are written to `storage/mail/*.html` (open them in a browser to click
verification/reset links). The sandbox payment gateway is enabled in development only.

> If you serve the app from a different folder or domain, update `APP_URL` in `.env` (no trailing slash).

## 2. Commands

| Command | Purpose |
|---|---|
| `php bin/console.php migrate` | Apply pending SQL migrations (`database/migrations`) |
| `php bin/console.php seed:base` | Production-safe starter data (idempotent) |
| `php bin/console.php seed:demo` | Development demo data (refused in production) |
| `php bin/console.php demo:purge` | Delete all demo rows and generated images — **run before launch** |
| `php bin/console.php user:owner <email> "<name>"` | Create/promote an Owner |
| `php bin/console.php cron` | Expire unpaid reservations, send queued email, clean up (run every 5 min) |
| `php bin/console.php backup` | Compressed `mysqldump` to `storage/backups` (keeps 14) |
| `php bin/console.php check` | Lint every PHP file and run the full test suite |
| `php tests/run.php [unit\|integration\|http]` | Run tests (uses the separate `DB_TEST_DATABASE`, wiped each run) |

## 3. Project structure

```
.htaccess              routes all requests into /public; blocks app/, storage/, .env, etc.
public/                web root: index.php front controller, assets/, brand/, uploads/ (no PHP execution)
app/
  bootstrap.php        autoloader, env + config validation, error handling
  routes.php           every route with its middleware (auth / perm:<permission> / CSRF)
  Core/                Router, Request/Response, Database (MySQLi prepared statements), Session, Csrf,
                       Validator, Money, Sanitizer, Uploads, RateLimiter, Logger (redacting), Security headers
  Auth/                Auth (sessions + session_version), Permissions (role matrix), Tokens (hashed, single-use)
  Services/            Catalog, Cart, Pricing, Coupons, Shipping, Checkout, Orders, OrderState, Inventory,
                       Payments (webhooks), Reports, Products, CsvImport, Settings, Content, Notifications, Audit
  Payments/            PaymentProvider interface, Registry, COD, BankTransfer, Sandbox, Unavailable (future)
  Mail/                Mailer (dev outbox / SMTP, queued order email) and SmtpTransport
  Controllers/         Store/, Auth/, Account/, Admin/
  Views/               layouts/, store/, account/, auth/, admin/, emails/, errors/, partials/
database/migrations/   schema (InnoDB, utf8mb4, CHECK + UNIQUE constraints)
database/seeds/        BaseSeeder (production-safe), DemoSeeder (development only)
bin/console.php        CLI
tests/                 unit, integration (real DB) and in-process HTTP tests
storage/               logs, sessions, dev mail, private receipts, backups (never web-accessible)
resources/brand/       original logo and favicon files
docs/                  deployment, payments, backups, launch checklist
```

## 4. Key design decisions

- **Money** is stored as integer minor units (paisa) with an explicit currency code; percentage
  discounts round half-up; order discounts are allocated to lines with exact sums. No floats.
- **Checkout** recalculates everything on the server inside one transaction: locks variants
  (`SELECT … FOR UPDATE`), re-validates status/stock/prices/coupon/shipping/payment method, compares
  a fingerprint of the totals the customer saw, decrements stock with a conditional `UPDATE … WHERE stock >= n`
  (plus a DB `CHECK (stock >= 0)`), writes immutable order-item and address snapshots, and redeems the
  coupon with a guarded counter. A per-render idempotency key makes double submits return the same order.
- **Order lifecycle**: Pending → Awaiting Payment → Confirmed → Processing → Packed → Shipped → Delivered,
  plus Cancelled and Refunded, enforced by `OrderState`. Every change locks the order row and re-checks state.
- **Payments**: COD (staff record collection), bank transfer (customer submits reference/receipt; staff
  verify), sandbox gateway (signed webhook + server-side status check). A browser reaching a success page
  never marks an order paid. Webhook events are stored with `UNIQUE(provider, event_id)` for idempotency.
- **Security**: Argon2id passwords, hardened sessions with `session_version` revocation, CSRF tokens +
  Origin check, DB-backed rate limits, strict CSP and security headers, allow-list HTML sanitizer, validated
  re-encoded uploads, append-only audit log, permission checks on every admin route and sensitive service.
- **Analytics** exclude sandbox test orders always and demo data in production; metric definitions are
  shown in the admin.

## 5. Configuration

All configuration lives in `.env` (template: `.env.example`, placeholders only). Production refuses to start
with placeholder secrets, a non-HTTPS `APP_URL`, the `root` DB user, the dev mail outbox or a missing
`CRON_SECRET`. See `docs/DEPLOYMENT.md`.

## 6. Tests

`php tests/run.php` creates a fresh `uravexapparel_test` database (name must contain "test") and runs
unit, integration and HTTP suites covering authentication, permissions, CSRF, uploads, pricing, coupons,
checkout, stock consistency, webhook verification/idempotency, order transitions, refunds and CSV import.
