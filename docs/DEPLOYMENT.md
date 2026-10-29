# Deployment

The app is plain PHP + MySQL and runs on shared hosting (cPanel), a VPS (Apache or Nginx + PHP-FPM) or any
PHP 8.1+ platform with a persistent disk.

## 1. Server requirements

- PHP 8.1+ (8.2/8.3 recommended) with `mysqli`, `gd` (WebP), `fileinfo`, `mbstring`, `openssl`, `exif`
- MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4). Server time zone is irrelevant — the app uses UTC on its connection.
- HTTPS certificate (Let's Encrypt is fine)
- Persistent disk for `public/uploads` (product images) and `storage/` (receipts, sessions, logs, backups).
  If your platform's disk is ephemeral (containers), mount a persistent volume for both paths.

## 2. Web root

**Recommended:** point the domain's document root at the `public/` directory. Then only `public/` is
reachable regardless of `.htaccess` support.

If you must deploy into a folder whose root is web-served (as on XAMPP), keep the root `.htaccess` — it
routes everything into `public/` and denies `app/`, `storage/`, `database/`, `bin/`, `tests/`, `docs/`,
`resources/` and dotfiles. **Verify** after deploy that `https://<domain>/.env` and `/app/bootstrap.php` return 403/404.

Nginx equivalent (document root = `public/`):

```nginx
root /var/www/uravex/public;
index index.php;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ~ /\. { deny all; }
client_max_body_size 20m;
```

## 3. Database (least privilege)

```sql
CREATE DATABASE uravexapparel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uravex_app'@'localhost' IDENTIFIED BY '<long random password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON uravexapparel.* TO 'uravex_app'@'localhost';
-- A separate migration user (used only when running migrations):
CREATE USER 'uravex_migrate'@'localhost' IDENTIFIED BY '<another random password>';
GRANT ALL PRIVILEGES ON uravexapparel.* TO 'uravex_migrate'@'localhost';
```

Run migrations with the migrate user (temporarily set `DB_USERNAME`/`DB_PASSWORD` in the shell environment;
real environment variables override `.env`), and run the site with the app user.

## 4. `.env` for production

Start from `.env.example`. Required changes:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.your-domain.com
APP_KEY=<64 random hex chars>
DB_USERNAME=uravex_app
DB_PASSWORD=<secret>
MAIL_DRIVER=smtp
MAIL_FROM_ADDRESS=orders@your-domain.com
MAIL_ADMIN_NOTIFY=orders@your-domain.com
SMTP_HOST=… SMTP_PORT=587 SMTP_ENCRYPTION=tls SMTP_USERNAME=… SMTP_PASSWORD=…
PAYMENT_MODE=live
CRON_SECRET=<48 random hex chars>
TRUSTED_PROXY_HOPS=1        # only if behind exactly one proxy/CDN (e.g. Cloudflare)
```

The app **refuses to boot** in production if `APP_KEY`/`CRON_SECRET` are weak, `APP_URL` isn't HTTPS, the DB
user is `root`, or mail is still the dev outbox. Set file permissions so `.env` is readable only by the PHP
user (`chmod 600 .env`).

Configure SMTP SPF/DKIM/DMARC for your sending domain so order emails reach inboxes.

## 5. Deploy steps

```bash
git pull   # or upload files (exclude .env, storage/*, public/uploads/*)
php bin/console.php migrate          # with the migrate user
php bin/console.php seed:base        # first deploy only (idempotent)
php bin/console.php user:owner owner@your-domain.com "Owner Name"
php bin/console.php check            # optional on staging: lint + tests (needs a *_test database)
```

Writable by the PHP user: `storage/` (all subfolders) and `public/uploads/`.

## 6. Scheduled jobs

Every 5 minutes (expires unpaid online reservations, sends queued emails with retry, cleans up):

```cron
*/5 * * * * cd /var/www/uravex && php bin/console.php cron >> storage/logs/cron.log 2>&1
15 2 * * *  cd /var/www/uravex && php bin/console.php backup >> storage/logs/backup.log 2>&1
```

Hosts without CLI cron can call the HTTP trigger:
`curl -fsS -X POST -H "Authorization: Bearer $CRON_SECRET" https://your-domain.com/cron/run`

## 7. PHP settings

`upload_max_filesize = 10M`, `post_max_size = 40M` (12 images per upload), `expose_php = Off`,
`display_errors = Off`, `session.cookie_secure` is set by the app under HTTPS, `memory_limit = 256M`
(GD resizing of large photos).
