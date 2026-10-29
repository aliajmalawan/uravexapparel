# Backup & Recovery

## What to back up

| Data | Location | Frequency |
|---|---|---|
| Database (orders, customers, catalogue, settings, audit log) | MySQL `uravexapparel` | Daily full + hourly if order volume is high (or managed point-in-time recovery) |
| Product/banner images | `public/uploads/` | Daily (incremental sync) |
| Bank-transfer receipts | `storage/private/receipts/` | Daily — contains personal data; encrypt at rest |
| Configuration | `.env` | On every change, stored in a password manager/secret store — never in git |

## Database backups

`php bin/console.php backup` writes a consistent (`--single-transaction`) gzip dump to `storage/backups/`
and keeps the latest 14. Copy those files **off the server** (e.g. `rclone` to S3/Backblaze/Google Drive)
— a backup on the same disk does not survive disk loss:

```cron
15 2 * * * cd /var/www/uravex && php bin/console.php backup && rclone copy storage/backups remote:uravex-backups --max-age 2d
30 2 * * * rclone sync /var/www/uravex/public/uploads remote:uravex-uploads
```

Set `MYSQLDUMP_PATH` if `mysqldump` is not on the PATH (XAMPP default: `C:\xampp\mysql\bin\mysqldump.exe`).

## Restore — full

```bash
# 1. Put the site in maintenance (stop PHP or point the vhost at a static page).
# 2. Restore into a NEW database first and verify it.
mysql -u uravex_migrate -p -e "CREATE DATABASE uravex_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c storage/backups/uravexapparel-YYYYMMDD-HHMMSS.sql.gz | mysql -u uravex_migrate -p uravex_restore
mysql -u uravex_migrate -p uravex_restore -e "SELECT COUNT(*) FROM orders; SELECT MAX(placed_at) FROM orders;"
# 3. Switch DB_DATABASE in .env to uravex_restore (or rename databases), restore uploads, bring the site back.
php bin/console.php migrate   # applies any migrations newer than the backup
```

## Restore — a single order

Restore the dump into `uravex_restore` (as above), then copy the rows for one order:

```sql
SET @n = 'UVX-100245';
INSERT INTO uravexapparel.orders SELECT * FROM uravex_restore.orders WHERE order_number = @n;
SET @id = (SELECT id FROM uravex_restore.orders WHERE order_number = @n);
INSERT INTO uravexapparel.order_items          SELECT * FROM uravex_restore.order_items          WHERE order_id = @id;
INSERT INTO uravexapparel.payments             SELECT * FROM uravex_restore.payments             WHERE order_id = @id;
INSERT INTO uravexapparel.order_status_history SELECT * FROM uravex_restore.order_status_history WHERE order_id = @id;
INSERT INTO uravexapparel.shipments            SELECT * FROM uravex_restore.shipments            WHERE order_id = @id;
INSERT INTO uravexapparel.refunds              SELECT * FROM uravex_restore.refunds              WHERE order_id = @id;
```

Do **not** restore stock levels from an old backup on a live store — reconcile with
`inventory_movements` instead (every stock change is in that ledger with reason, order and staff member).

## Test the restore

At least monthly, restore the latest backup into a scratch database and check row counts and the newest
order date. A backup that has never been restored is not a backup.
