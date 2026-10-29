<?php

declare(strict_types=1);

/*
 * URAVEX console.
 *
 *   php bin/console.php setup                  Create .env with generated secrets (local XAMPP defaults)
 *   php bin/console.php migrate                Apply pending database migrations
 *   php bin/console.php seed:base              Production-safe base data (pages, shipping zones, couriers)
 *   php bin/console.php seed:demo              Development demo catalog/customers/orders (flagged is_demo)
 *   php bin/console.php demo:purge             Delete all is_demo data (run before launch)
 *   php bin/console.php user:owner <email> <name>   Create or promote an Owner account
 *   php bin/console.php cron                   Expire unpaid orders, send queued email, cleanup
 *   php bin/console.php backup                 mysqldump to storage/backups (keeps 14)
 *   php bin/console.php check                  Lint all PHP files and run the test suite
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$cmd = $argv[1] ?? 'help';

if ($cmd === 'setup') {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) {
        fwrite(STDOUT, ".env already exists — leaving it untouched.\n");
        exit(0);
    }
    $env = (string) file_get_contents($root . '/.env.example');
    $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . bin2hex(random_bytes(32)), $env);
    $env = preg_replace('/^SANDBOX_WEBHOOK_SECRET=.*$/m', 'SANDBOX_WEBHOOK_SECRET=' . bin2hex(random_bytes(24)), $env);
    $env = preg_replace('/^CRON_SECRET=.*$/m', 'CRON_SECRET=' . bin2hex(random_bytes(24)), $env);
    // Local XAMPP defaults (development only; production validation rejects root).
    $env = preg_replace('/^DB_USERNAME=.*$/m', 'DB_USERNAME=root', $env);
    $env = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD=', $env);
    file_put_contents($root . '/.env', $env);
    fwrite(STDOUT, "Created .env with generated secrets.\n");
    exit(0);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

function out(string $s): void
{
    fwrite(STDOUT, $s . PHP_EOL);
}

function migrate(Database $db): void
{
    $m = $db->raw();
    $m->query('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = array_flip($db->column('SELECT version FROM schema_migrations'));
    $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
    sort($files);
    $applied = 0;
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (isset($done[$version])) {
            continue;
        }
        $sql = (string) file_get_contents($file);
        // DDL auto-commits in MySQL, so migrations are written to be applied once, in order.
        $m->multi_query($sql);
        do {
            if ($r = $m->store_result()) {
                $r->free();
            }
        } while ($m->more_results() && $m->next_result());
        $db->exec('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
        out("Migrated: {$version}");
        $applied++;
    }
    out($applied ? "Done ({$applied} applied)." : 'Nothing to migrate.');
}

$db = Database::get();

switch ($cmd) {
    case 'migrate':
        migrate($db);
        break;

    case 'seed:base':
        require BASE_PATH . '/database/seeds/BaseSeeder.php';
        (new BaseSeeder())->run($db);
        out('Base data seeded.');
        break;

    case 'seed:demo':
        if (Config::isProduction()) {
            out('Refusing to seed demo data in production.');
            exit(1);
        }
        require BASE_PATH . '/database/seeds/BaseSeeder.php';
        require BASE_PATH . '/database/seeds/DemoSeeder.php';
        (new BaseSeeder())->run($db);
        (new DemoSeeder())->run($db);
        out('Demo data seeded (all rows flagged is_demo = 1).');
        break;

    case 'demo:purge':
        require BASE_PATH . '/database/seeds/DemoSeeder.php';
        DemoSeeder::purge($db);
        out('All demo data removed.');
        break;

    case 'user:owner':
        $email = strtolower(trim($argv[2] ?? ''));
        $name = trim($argv[3] ?? 'Store Owner');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            out('Usage: php bin/console.php user:owner <email> "<name>"');
            exit(1);
        }
        $existing = $db->one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $db->exec("UPDATE users SET role = 'owner', session_version = session_version + 1 WHERE id = ?", [$existing['id']]);
            out("Promoted {$email} to Owner (existing password unchanged).");
            break;
        }
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Ab'), '=') . '7';
        $db->insert(
            "INSERT INTO users (name, email, password_hash, role, email_verified_at) VALUES (?, ?, ?, 'owner', UTC_TIMESTAMP())",
            [$name, $email, App\Auth\Auth::hashPassword($password)]
        );
        out("Owner created: {$email}");
        out("Temporary password (shown once — change it after first login): {$password}");
        break;

    case 'cron':
        $expired = App\Services\Orders::expireStale();
        App\Services\ExchangeRates::refreshIfStale();
        if (!App\Services\CryptoPrices::isFresh()) {
            App\Services\CryptoPrices::refresh();
        }
        $sent = App\Mail\Mailer::flush(50);
        $carts = App\Services\Cart::purgeStale();
        $db->exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 172800]);
        $db->exec('DELETE FROM user_tokens WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
        out("Cron: {$expired} expired orders cancelled, {$sent} emails sent, {$carts} stale carts removed.");
        break;

    case 'backup':
        $dump = getenv('MYSQLDUMP_PATH') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump');
        $cfg = Config::get('db');
        $dir = STORAGE_PATH . '/backups';
        $file = $dir . '/' . $cfg['name'] . '-' . gmdate('Ymd-His') . '.sql';
        $cnf = tempnam(sys_get_temp_dir(), 'uvx');
        file_put_contents($cnf, "[client]\nuser={$cfg['user']}\npassword=\"{$cfg['pass']}\"\nhost={$cfg['host']}\nport={$cfg['port']}\n");
        $cmdline = escapeshellarg($dump) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --single-transaction --routines --triggers --default-character-set=utf8mb4 ' . escapeshellarg($cfg['name'])
            . ' > ' . escapeshellarg($file);
        exec($cmdline, $o, $code);
        unlink($cnf);
        if ($code !== 0 || !is_file($file) || filesize($file) === 0) {
            out('Backup failed (exit ' . $code . '). Set MYSQLDUMP_PATH if mysqldump is not on PATH.');
            exit(1);
        }
        if (function_exists('gzencode')) {
            file_put_contents($file . '.gz', gzencode((string) file_get_contents($file), 9));
            unlink($file);
            $file .= '.gz';
        }
        $all = glob($dir . '/*.sql*') ?: [];
        rsort($all);
        foreach (array_slice($all, 14) as $old) {
            unlink($old);
        }
        out('Backup written: ' . $file);
        break;

    case 'check':
        // Lint every PHP file with the running interpreter, then run the test suite.
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
        $bad = 0;
        $count = 0;
        foreach ($it as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || preg_match('#[\\\\/](storage|node_modules|\.git)[\\\\/]#', $path)) {
                continue;
            }
            $count++;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $code);
            if ($code !== 0) {
                $bad++;
                out(implode("\n", $o));
            }
            $o = [];
        }
        out("Lint: {$count} files, {$bad} with errors.");
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/tests/run.php'), $testCode);
        exit($bad || $testCode ? 1 : 0);

    default:
        out((string) preg_replace('/^\s*\* ?/m', '', explode('*/', explode('/*', (string) file_get_contents(__FILE__), 2)[1])[0]));
}
