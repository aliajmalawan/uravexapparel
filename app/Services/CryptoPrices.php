<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Live BTC/ETH prices in USDT, used to quote crypto amounts at checkout.
 *
 * Primary: Binance public ticker (no key; BTCUSDT / ETHUSDT).
 * Backup:  CoinGecko simple price (no key; quoted in USD ≈ USDT).
 * Cached in site_settings("crypto_prices"). Quotes are only issued from
 * prices younger than MAX_AGE; checkout refreshes them when needed.
 */
final class CryptoPrices
{
    public const ASSETS = ['BTC', 'ETH'];
    public const MAX_AGE = 20 * 60;          // prices older than this are refreshed before quoting
    private const CACHE_KEY = 'crypto_prices';

    /** @var (callable(string):?string)|null Test hook replacing HTTP (receives 'binance'|'coingecko'). */
    public static $fetcher = null;
    private static ?array $memo = null;

    public static function cached(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        $v = Database::get()->value('SELECT value FROM site_settings WHERE `key` = ?', [self::CACHE_KEY]);
        $d = $v ? json_decode((string) $v, true) : [];
        return self::$memo = is_array($d) ? $d : [];
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    public static function isFresh(): bool
    {
        $c = self::cached();
        return !empty($c['fetched_at']) && strtotime($c['fetched_at'] . ' UTC') > time() - self::MAX_AGE;
    }

    /** Price of 1 asset in USDT (decimal string), fresh only (null if unavailable). */
    public static function price(string $asset, bool $refreshIfStale = true): ?string
    {
        if ($refreshIfStale && !self::isFresh()) {
            self::refresh(true);
        }
        if (!self::isFresh()) {
            return null;
        }
        $p = self::cached()['prices'][$asset] ?? null;
        return is_string($p) && preg_match('/^\d+(\.\d+)?$/', $p) && bccomp($p, '0', 8) > 0 ? $p : null;
    }

    public static function refresh(bool $quick = false): bool
    {
        $timeout = $quick ? 4 : 10;
        $prices = null;
        $source = 'binance';
        $body = self::$fetcher ? (self::$fetcher)('binance') : self::download('https://api.binance.com/api/v3/ticker/price?symbols=' . rawurlencode('["BTCUSDT","ETHUSDT"]'), $timeout);
        if ($body !== null) {
            $prices = [];
            foreach (self::ASSETS as $a) {
                if (preg_match('/"symbol"\s*:\s*"' . $a . 'USDT"\s*,\s*"price"\s*:\s*"([0-9]+(?:\.[0-9]+)?)"/', $body, $m)) {
                    $prices[$a] = rtrim(rtrim($m[1], '0'), '.');
                }
            }
            $prices = count($prices) === count(self::ASSETS) ? $prices : null;
        }
        if ($prices === null) {
            $source = 'coingecko';
            $body = self::$fetcher ? (self::$fetcher)('coingecko') : self::download('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum&vs_currencies=usd', $timeout);
            if ($body !== null) {
                $map = ['BTC' => 'bitcoin', 'ETH' => 'ethereum'];
                $prices = [];
                foreach ($map as $a => $id) {
                    if (preg_match('/"' . $id . '"\s*:\s*\{\s*"usd"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $body, $m)) {
                        $prices[$a] = $m[1];
                    }
                }
                $prices = count($prices) === count($map) ? $prices : null;
            }
        }
        if ($prices === null) {
            Logger::warning('Crypto price refresh failed');
            return false;
        }
        Database::get()->exec(
            'INSERT INTO site_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::CACHE_KEY, json_encode(['prices' => $prices, 'source' => $source, 'fetched_at' => gmdate('Y-m-d H:i:s')], JSON_UNESCAPED_SLASHES)]
        );
        self::$memo = null;
        Settings::flush();
        return true;
    }

    private static function download(string $url, int $timeout): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_USERAGENT => 'URAVEX-Store/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return is_string($body) && $code === 200 && strlen($body) < 100_000 ? $body : null;
    }
}
