<?php

declare(strict_types=1);

/*
 * Test bootstrap: points the app at the dedicated test database
 * (DB_TEST_DATABASE, default "uravexapparel_test"), recreates the schema and
 * provides a tiny assertion framework (no Composer required).
 */

putenv('APP_ENV=testing');
putenv('MAIL_DRIVER=array');
putenv('APP_URL=http://localhost/uravexapparel');

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$testDb = (string) (Env::get('DB_TEST_DATABASE') ?: 'uravexapparel_test');
if ($testDb === Config::get('db.name') || !preg_match('/test/i', $testDb)) {
    fwrite(STDERR, "Refusing to run: DB_TEST_DATABASE must be a separate database whose name contains 'test'.\n");
    exit(2);
}
Config::set('payments.sandbox_secret', 'test-sandbox-secret-0123456789abcdef');
Config::set('cron_secret', str_repeat('c', 32));
// Never call the live exchange-rate API from tests: serve a fixed response.
App\Services\ExchangeRates::$fetcher = static fn (string $base): ?string => $base === 'PKR'
    ? '{"result":"success","base_code":"PKR","time_last_update_unix":1792972951,"rates":{"PKR":1,"USD":0.003611,"EUR":0.003221,"GBP":0.002755,"AED":0.01326,"JPY":0.5664}}'
    : null;
// Crypto prices: fixed fixture (Binance format), never the live API.
App\Services\CryptoPrices::$fetcher = static fn (string $source): ?string => $source === "binance"
    ? '[{"symbol":"BTCUSDT","price":"60000.00000000"},{"symbol":"ETHUSDT","price":"2500.50000000"}]'
    : null;
// The suite also covers the former COD / bank transfer / sandbox methods (kept for historical orders).
App\Payments\Registry::$legacy = true;

// Create a fresh schema.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$cfg = Config::get('db');
$root = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], '', $cfg['port']);
$root->query('DROP DATABASE IF EXISTS `' . $root->real_escape_string($testDb) . '`');
$root->query('CREATE DATABASE `' . $root->real_escape_string($testDb) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$root->close();
Config::set('db.name', $testDb);
Database::swap(null);
$m = Database::get()->raw();
foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
    $m->multi_query((string) file_get_contents($file));
    do {
        if ($r = $m->store_result()) {
            $r->free();
        }
    } while ($m->more_results() && $m->next_result());
}

final class T
{
    public static int $pass = 0;
    public static array $failures = [];
    public static string $current = '';

    public static function ok(bool $cond, string $msg): void
    {
        if ($cond) {
            self::$pass++;
        } else {
            self::$failures[] = self::$current . ': ' . $msg;
        }
    }

    public static function eq(mixed $expected, mixed $actual, string $msg): void
    {
        self::ok($expected === $actual, $msg . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }

    /** Asserts that $fn throws $class (optionally containing $contains). */
    public static function throws(string $class, callable $fn, string $msg, ?string $contains = null): void
    {
        try {
            $fn();
            self::ok(false, $msg . ' (no exception)');
        } catch (\Throwable $e) {
            $okClass = $e instanceof $class;
            $text = $e instanceof \App\Core\ValidationException ? $e->getMessage() . ' ' . implode(' ', $e->errors) : $e->getMessage();
            self::ok($okClass && ($contains === null || str_contains($text, $contains)), $msg . ($okClass ? '' : ' (got ' . $e::class . ': ' . $e->getMessage() . ')') . ($contains !== null && $okClass && !str_contains($text, $contains) ? " (message: {$text})" : ''));
        }
    }
}

/** Wipes all data tables between test groups. */
function reset_db(): void
{
    $db = Database::get();
    $db->raw()->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($db->column("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $t) {
        $db->raw()->query('TRUNCATE TABLE `' . $t . '`');
    }
    $db->raw()->query('SET FOREIGN_KEY_CHECKS = 1');
    App\Services\Settings::flush();
    App\Services\ExchangeRates::forget();
    App\Services\CryptoPrices::forget();
    App\Services\Currency::setCurrent(null);
    App\Payments\Registry::reset();
    App\Auth\Auth::actingAs(null);
    App\Core\Session::resetMemory();
    App\Services\Cart::setGuestToken(null);
    App\Mail\Mailer::$sent = [];
}
