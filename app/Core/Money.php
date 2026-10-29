<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Integer minor-unit money helpers. Authoritative arithmetic never uses floats.
 */
final class Money
{
    /**
     * Supported currencies: code => [name, symbol, minor-unit exponent].
     * The store base currency and customer display currencies are chosen from
     * this list in Admin → Settings → Currency.
     */
    public const CURRENCIES = [
        'PKR' => ['Pakistani Rupee', 'Rs ', 2],
        'USD' => ['US Dollar', '$', 2],
        // Tether (USD-pegged stablecoin, 1 USDT ≈ 1 USD). Not an ISO currency; priced to 2 decimals.
        'USDT' => ['Tether (USDT)', 'USDT ', 2],
        'EUR' => ['Euro', '€', 2],
        'GBP' => ['British Pound', '£', 2],
        'AED' => ['UAE Dirham', 'AED ', 2],
        'SAR' => ['Saudi Riyal', 'SAR ', 2],
        'QAR' => ['Qatari Riyal', 'QAR ', 2],
        'KWD' => ['Kuwaiti Dinar', 'KWD ', 3],
        'BHD' => ['Bahraini Dinar', 'BHD ', 3],
        'OMR' => ['Omani Rial', 'OMR ', 3],
        'CAD' => ['Canadian Dollar', 'CA$', 2],
        'AUD' => ['Australian Dollar', 'A$', 2],
        'NZD' => ['New Zealand Dollar', 'NZ$', 2],
        'INR' => ['Indian Rupee', '₹', 2],
        'BDT' => ['Bangladeshi Taka', '৳', 2],
        'CNY' => ['Chinese Yuan', 'CN¥', 2],
        'JPY' => ['Japanese Yen', '¥', 0],
        'MYR' => ['Malaysian Ringgit', 'RM ', 2],
        'SGD' => ['Singapore Dollar', 'S$', 2],
        'TRY' => ['Turkish Lira', '₺', 2],
        'CHF' => ['Swiss Franc', 'CHF ', 2],
        'SEK' => ['Swedish Krona', 'SEK ', 2],
        'ZAR' => ['South African Rand', 'R ', 2],
    ];

    /** @var (callable():string)|null Resolves the store base currency (set at bootstrap). */
    private static $defaultResolver = null;

    public static function setDefaultResolver(?callable $fn): void
    {
        self::$defaultResolver = $fn;
    }

    /** Store base currency (Admin → Settings → Currency), falling back to DEFAULT_CURRENCY. */
    public static function defaultCurrency(): string
    {
        if (self::$defaultResolver !== null) {
            try {
                $c = (string) (self::$defaultResolver)();
                if (isset(self::CURRENCIES[$c])) {
                    return $c;
                }
            } catch (\Throwable) {
                // Database unavailable (e.g. early errors) — use the environment default.
            }
        }
        $env = (string) Config::get('currency', 'PKR');
        return isset(self::CURRENCIES[$env]) ? $env : 'PKR';
    }

    public static function exponent(?string $currency = null): int
    {
        return self::CURRENCIES[$currency ?? self::defaultCurrency()][2] ?? 2;
    }

    public static function symbol(?string $currency = null): string
    {
        $c = $currency ?? self::defaultCurrency();
        return self::CURRENCIES[$c][1] ?? $c . ' ';
    }

    public static function factor(?string $currency = null): int
    {
        return 10 ** self::exponent($currency);
    }

    /**
     * Parses user input ("2,499", "2499.5", "2499.50") into minor units.
     * Returns null for malformed or negative input. Never goes through floats.
     */
    public static function parse(string $input, ?string $currency = null): ?int
    {
        $s = str_replace([',', ' '], '', trim($input));
        $exp = self::exponent($currency);
        $pattern = $exp > 0 ? '/^(\d{1,13})(?:\.(\d{1,' . $exp . '}))?$/' : '/^(\d{1,13})$/';
        if (!preg_match($pattern, $s, $m)) {
            return null;
        }
        $fraction = $exp > 0 ? str_pad($m[2] ?? '', $exp, '0') : '0';
        return (int) $m[1] * self::factor($currency) + (int) $fraction;
    }

    /** "Rs 2,499" or "$24.99" (decimals shown only when non-zero unless forced). */
    public static function format(int $minor, ?string $currency = null, bool $forceDecimals = false): string
    {
        $currency ??= self::defaultCurrency();
        $exp = self::exponent($currency);
        $factor = self::factor($currency);
        $neg = $minor < 0;
        $abs = abs($minor);
        $out = number_format(intdiv($abs, $factor));
        $rem = $abs % $factor;
        if ($exp > 0 && ($rem !== 0 || $forceDecimals)) {
            $out .= '.' . str_pad((string) $rem, $exp, '0', STR_PAD_LEFT);
        }
        return ($neg ? '−' : '') . self::symbol($currency) . $out;
    }

    /** Plain decimal string for forms/CSV/JSON-LD: 2499.00 (2499 for 0-decimal currencies). */
    public static function decimal(int $minor, ?string $currency = null): string
    {
        $exp = self::exponent($currency);
        $factor = self::factor($currency);
        $neg = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        if ($exp === 0) {
            return $neg . $abs;
        }
        return $neg . intdiv($abs, $factor) . '.' . str_pad((string) ($abs % $factor), $exp, '0', STR_PAD_LEFT);
    }

    /**
     * Converts an amount between currencies using a decimal-string rate
     * ("1 FROM = rate TO"), rounding half-up to the target minor unit.
     * Uses BCMath — no floating point.
     */
    public static function convert(int $minor, string $from, string $to, string $rate): int
    {
        if ($from === $to) {
            return $minor;
        }
        if (!self::validRate($rate)) {
            throw new \InvalidArgumentException('Invalid exchange rate');
        }
        $scale = 14;
        $value = bcdiv((string) $minor, bcpow('10', (string) self::exponent($from)), $scale);
        $value = bcmul(bcmul($value, $rate, $scale), bcpow('10', (string) self::exponent($to)), $scale);
        $neg = str_starts_with($value, '-');
        $rounded = bcadd(ltrim($value, '-'), '0.5', 0);
        return (int) ($neg ? '-' . $rounded : $rounded);
    }

    public static function validRate(string $rate): bool
    {
        return (bool) preg_match('/^\d{1,9}(\.\d{1,10})?$/', $rate) && bccomp($rate, '0', 10) > 0;
    }

    /**
     * Percentage of an amount, rounded half-up to the nearest minor unit.
     * $percent is a whole number 0–100.
     */
    public static function percentOf(int $minor, int $percent): int
    {
        // (minor * percent + 50) / 100 with integer arithmetic = half-up rounding.
        return intdiv($minor * $percent + 50, 100);
    }

    /**
     * Splits $amount across $weights proportionally (largest-remainder), so the
     * parts always sum exactly to $amount. Used to allocate order discounts to lines.
     *
     * @param list<int> $weights
     * @return list<int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0 || $amount === 0) {
            return array_fill(0, count($weights), 0);
        }
        $parts = [];
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $i => $w) {
            $exact = $amount * $w;
            $parts[$i] = intdiv($exact, $total);
            $remainders[$i] = $exact % $total;
            $allocated += $parts[$i];
        }
        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($allocated >= $amount) {
                break;
            }
            $parts[$i]++;
            $allocated++;
        }
        return array_values($parts);
    }
}
