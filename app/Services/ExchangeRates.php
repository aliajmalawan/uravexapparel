<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Money;

/**
 * Live exchange rates for customer display currencies.
 *
 * Source: ExchangeRate-API open access endpoint (https://open.er-api.com, no
 * API key; rates update once a day; attribution required — shown in the store
 * footer). Rates are cached in site_settings and refreshed by cron, by the
 * "Refresh now" button, or lazily (at most once an hour) when a page finds
 * them older than STALE_AFTER. If the source is unreachable the last good
 * rates are kept.
 *
 * Only display rates are automatic. Product prices are stored in the base
 * currency and never change on their own.
 */
final class ExchangeRates
{
    public const SOURCE_NAME = 'ExchangeRate-API';
    public const SOURCE_URL = 'https://www.exchangerate-api.com';
    private const ENDPOINT = 'https://open.er-api.com/v6/latest/';
    private const BACKUP_ENDPOINT = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/';
    private const STALE_AFTER = 6 * 3600;      // refresh rates older than 6 hours
    private const RETRY_AFTER = 3600;          // don't retry a failed fetch more than hourly
    private const CACHE_KEY = 'currency_rates';

    /** @var (callable(string):?string)|null Test hook replacing the HTTP fetch. */
    public static $fetcher = null;

    private static ?array $memo = null;

    /** Cached data: base, rates (code => decimal string), fetched_at, source_updated_at, last_attempt. */
    public static function cached(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        $v = \App\Core\Database::get()->value('SELECT value FROM site_settings WHERE `key` = ?', [self::CACHE_KEY]);
        $d = $v ? json_decode((string) $v, true) : [];
        return self::$memo = is_array($d) ? $d : [];
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    /** Live rate for 1 base → code, or null if unknown. */
    public static function rate(string $code, ?string $base = null): ?string
    {
        $c = self::cached();
        if (($c['base'] ?? '') !== ($base ?? Currency::base())) {
            return null;
        }
        $r = $c['rates'][$code] ?? null;
        return is_string($r) && Money::validRate($r) ? $r : null;
    }

    /**
     * Downloads fresh rates for the current base currency. Returns true on success.
     */
    public static function refresh(bool $quick = false): bool
    {
        $base = Currency::base();
        $cache = self::cached();
        $cache['last_attempt'] = time();
        $timeout = $quick ? 3 : 10; // page-triggered refreshes must never hold a shopper up for long

        // Primary: ExchangeRate-API (attribution shown in the footer).
        $source = 'primary';
        $body = self::$fetcher ? (self::$fetcher)($base) : self::download(self::ENDPOINT . rawurlencode($base), $timeout);
        $rates = $body !== null ? self::parse($body, $base) : null;
        $updated = $rates !== null && preg_match('/"time_last_update_unix"\s*:\s*(\d+)/', (string) $body, $m) ? gmdate('Y-m-d H:i:s', (int) $m[1]) : null;

        // Backup: fawazahmed0 currency-api on jsDelivr (free, no key).
        if ($rates === null && self::$fetcher === null) {
            $source = 'backup';
            $body = self::download(self::BACKUP_ENDPOINT . strtolower($base) . '.json', $timeout);
            $rates = $body !== null ? self::parseBackup($body, $base) : null;
            $updated = $rates !== null && preg_match('/"date"\s*:\s*"(\d{4}-\d{2}-\d{2})"/', (string) $body, $m) ? $m[1] . ' 00:00:00' : null;
        }
        if ($rates === null) {
            self::store($cache);
            Logger::warning('Exchange-rate refresh failed', ['base' => $base]);
            return false;
        }
        self::store(['base' => $base, 'rates' => $rates, 'source' => $source, 'fetched_at' => gmdate('Y-m-d H:i:s'), 'source_updated_at' => $updated, 'last_attempt' => time()]);
        return true;
    }

    /** Parses the backup feed: {"date": "...", "usd": {"pkr": 276.9, ...}} (lower-case codes). */
    public static function parseBackup(string $json, string $base): ?array
    {
        $lower = strtolower($base);
        if (!preg_match('/"' . $lower . '"\s*:\s*\{(.*)\}/s', $json, $block)) {
            return null;
        }
        $out = [];
        foreach (array_keys(Money::CURRENCIES) as $code) {
            if ($code !== $base && preg_match('/"' . strtolower($code) . '"\s*:\s*([0-9]+(?:\.[0-9]+)?(?:[eE][-+]?[0-9]+)?)/', $block[1], $m)) {
                $rate = self::normalise($m[1]);
                if ($rate !== null) {
                    $out[$code] = $rate;
                }
            }
        }
        return count($out) >= 3 ? $out : null;
    }

    /**
     * Refreshes when rates are missing/old/for another base, rate-limited after
     * failures. With $onlyIfMissing (shopper page loads) it fetches only when
     * there are no usable rates at all, so customers never wait on the network
     * for a routine update — those run from cron and admin page visits.
     */
    public static function refreshIfStale(bool $onlyIfMissing = false): void
    {
        // Nothing to convert (no display currencies) — don't call the rate service at all.
        if (!Settings::get('currency.auto_rates', true) || !array_filter((array) Settings::get('currency.display', []), static fn ($v) => $v !== null)) {
            return;
        }
        $c = self::cached();
        $fresh = ($c['base'] ?? '') === Currency::base() && !empty($c['fetched_at'])
            && strtotime($c['fetched_at'] . ' UTC') > time() - self::STALE_AFTER;
        $recentlyTried = (int) ($c['last_attempt'] ?? 0) > time() - self::RETRY_AFTER;
        $missing = ($c['base'] ?? '') !== Currency::base() || empty($c['rates']);
        if ($onlyIfMissing && !$missing) {
            return;
        }
        if (!$fresh && !$recentlyTried) {
            self::refresh(true);
        }
    }

    /**
     * Extracts rates for supported currencies straight from the JSON text so the
     * decimal values never pass through floating point.
     *
     * @return array<string,string>|null
     */
    public static function parse(string $json, string $base): ?array
    {
        $d = json_decode($json, true);
        if (!is_array($d) || ($d['result'] ?? '') !== 'success' || ($d['base_code'] ?? '') !== $base) {
            return null;
        }
        $out = [];
        foreach (array_keys(Money::CURRENCIES) as $code) {
            if ($code === $base) {
                continue;
            }
            if (preg_match('/"' . $code . '"\s*:\s*([0-9]+(?:\.[0-9]+)?(?:[eE][-+]?[0-9]+)?)/', $json, $m)) {
                $rate = self::normalise($m[1]);
                if ($rate !== null) {
                    $out[$code] = $rate;
                }
            }
        }
        return count($out) >= 3 ? $out : null;
    }

    /** "2.7694019E2" / "276.940193" → plain decimal string with ≤ 10 decimals. */
    private static function normalise(string $n): ?string
    {
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)[eE]([-+]?[0-9]+)$/', $n, $m)) {
            $exp = (int) $m[2];
            $n = $exp >= 0 ? bcmul($m[1], bcpow('10', (string) $exp), 10) : bcdiv($m[1], bcpow('10', (string) -$exp), 10);
        }
        $n = bcadd($n, '0', 10);
        $n = rtrim(rtrim($n, '0'), '.');
        return Money::validRate($n) ? $n : null;
    }

    private static function download(string $url, int $timeout = 10): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 6),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'URAVEX-Store/1.0',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // avoids slow IPv6 fallbacks on some hosts
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return is_string($body) && $code === 200 && strlen($body) < 500_000 ? $body : null;
    }

    private static function store(array $data): void
    {
        \App\Core\Database::get()->exec(
            'INSERT INTO site_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::CACHE_KEY, json_encode($data, JSON_UNESCAPED_SLASHES)]
        );
        self::$memo = null;
        Settings::flush();
    }
}
