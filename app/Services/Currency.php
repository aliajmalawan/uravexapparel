<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\ValidationException;

/**
 * Store currencies.
 *
 *  - Base currency: the currency products are priced in and orders are
 *    charged in (all totals are calculated in it). Changing it converts every
 *    stored price with an exchange rate in one transaction; existing orders
 *    keep the currency they were placed in.
 *  - Display currencies: optional currencies customers can browse prices in
 *    (header switcher). These are approximate conversions for information only;
 *    checkout always charges the base currency and says so.
 */
final class Currency
{
    public const COOKIE = 'uravex_currency';

    private static ?string $current = null;

    public static function base(): string
    {
        $c = (string) Settings::get('currency.base', '');
        return isset(Money::CURRENCIES[$c]) ? $c : self::envBase();
    }

    private static function envBase(): string
    {
        $env = (string) \App\Core\Config::get('currency', 'PKR');
        return isset(Money::CURRENCIES[$env]) ? $env : 'PKR';
    }

    /** @return array<string,string> enabled display currencies: code => rate (1 base = rate) */
    public static function displayRates(): array
    {
        // Enabled currencies are the keys of currency.display. With automatic
        // rates on, the live rate is used; the stored value is the manual fallback.
        $auto = (bool) Settings::get('currency.auto_rates', true);
        $out = [];
        foreach ((array) Settings::get('currency.display', []) as $code => $manual) {
            if (!isset(Money::CURRENCIES[$code]) || $code === self::base()) {
                continue;
            }
            $rate = $auto ? ExchangeRates::rate($code) : null;
            $rate ??= is_string($manual) && Money::validRate($manual) ? $manual : null;
            if ($rate !== null) {
                $out[$code] = $rate;
            }
        }
        return $out;
    }

    /** Currencies a customer can choose (base first). @return list<string> */
    public static function choices(): array
    {
        return array_merge([self::base()], array_keys(self::displayRates()));
    }

    /** Currency the current visitor browses in (cookie), falling back to base. */
    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $choices = self::choices();
        $c = strtoupper((string) ($_COOKIE[self::COOKIE] ?? ''));
        if (in_array($c, $choices, true)) {
            return self::$current = $c;
        }
        // No choice yet: the admin-selected default for new visitors (if enabled), else base.
        $default = strtoupper((string) Settings::get('currency.default_display', ''));
        return self::$current = in_array($default, $choices, true) ? $default : self::base();
    }

    public static function setCurrent(?string $code): void
    {
        self::$current = $code;
    }

    /** Converts a base amount into the visitor's currency. */
    public static function toDisplay(int $baseMinor, ?string $code = null): int
    {
        $code ??= self::current();
        $base = self::base();
        if ($code === $base) {
            return $baseMinor;
        }
        $rate = self::displayRates()[$code] ?? null;
        return $rate === null ? $baseMinor : Money::convert($baseMinor, $base, $code, $rate);
    }

    /** Formats a base amount in the visitor's currency. */
    public static function format(int $baseMinor): string
    {
        $code = self::current();
        return Money::format(self::toDisplay($baseMinor, $code), $code);
    }

    public static function isConverted(): bool
    {
        return self::current() !== self::base();
    }

    /**
     * Changes the base currency, converting every stored price/amount with
     * $rate ("1 old base = rate new base"). Orders, payments and refunds keep
     * their original currency. Display rates are cleared (they were relative
     * to the old base) and must be re-entered.
     *
     * @return array<string,int> rows converted per table
     */
    public static function changeBase(string $new, string $rate, ?int $actorId): array
    {
        $old = self::base();
        if (!isset(Money::CURRENCIES[$new])) {
            throw ValidationException::one('base', 'Choose a supported currency.');
        }
        if ($new === $old) {
            throw ValidationException::one('base', 'That is already the store currency.');
        }
        if (!Money::validRate($rate)) {
            throw ValidationException::one('rate', 'Enter the exchange rate as a positive number, e.g. 0.0036.');
        }
        $conv = static fn (?int $v) => $v === null ? null : Money::convert($v, $old, $new, $rate);

        return Database::get()->transaction(static function (Database $db) use ($new, $old, $rate, $conv, $actorId): array {
            $counts = ['products' => 0, 'variants' => 0, 'shipping_rates' => 0, 'coupons' => 0];
            foreach ($db->all('SELECT id, price_minor, sale_price_minor, cost_minor FROM products FOR UPDATE') as $p) {
                $price = max(1, $conv((int) $p['price_minor']));
                $sale = $p['sale_price_minor'] !== null ? $conv((int) $p['sale_price_minor']) : null;
                if ($sale !== null && $sale >= $price) {
                    $sale = null; // rounding collapsed the discount
                }
                $db->exec('UPDATE products SET price_minor = ?, sale_price_minor = ?, cost_minor = ?, currency = ? WHERE id = ?',
                    [$price, $sale, $p['cost_minor'] !== null ? $conv((int) $p['cost_minor']) : null, $new, $p['id']]);
                $counts['products']++;
            }
            foreach ($db->all('SELECT id, price_minor, sale_price_minor FROM product_variants WHERE price_minor IS NOT NULL OR sale_price_minor IS NOT NULL FOR UPDATE') as $v) {
                $db->exec('UPDATE product_variants SET price_minor = ?, sale_price_minor = ? WHERE id = ?',
                    [$v['price_minor'] !== null ? max(1, $conv((int) $v['price_minor'])) : null, $v['sale_price_minor'] !== null ? $conv((int) $v['sale_price_minor']) : null, $v['id']]);
                $counts['variants']++;
            }
            foreach ($db->all('SELECT id, price_minor, free_over_minor FROM shipping_rates FOR UPDATE') as $r) {
                $db->exec('UPDATE shipping_rates SET price_minor = ?, free_over_minor = ? WHERE id = ?',
                    [$conv((int) $r['price_minor']), $r['free_over_minor'] !== null ? $conv((int) $r['free_over_minor']) : null, $r['id']]);
                $counts['shipping_rates']++;
            }
            foreach ($db->all('SELECT id, type, value, min_subtotal_minor, max_discount_minor FROM coupons FOR UPDATE') as $c) {
                $db->exec('UPDATE coupons SET value = ?, min_subtotal_minor = ?, max_discount_minor = ? WHERE id = ?', [
                    $c['type'] === 'fixed' ? max(1, $conv((int) $c['value'])) : (int) $c['value'],
                    $conv((int) $c['min_subtotal_minor']),
                    $c['max_discount_minor'] !== null ? $conv((int) $c['max_discount_minor']) : null,
                    $c['id'],
                ]);
                $counts['coupons']++;
            }
            // Money values held in settings.
            $shipping = (array) Settings::get('shipping', []);
            $shipping['free_threshold_minor'] = $conv((int) ($shipping['free_threshold_minor'] ?? 0));
            Settings::put('shipping', $shipping, $actorId);
            $payments = (array) Settings::get('payments', []);
            $payments['cod']['max_order_minor'] = $conv((int) ($payments['cod']['max_order_minor'] ?? 0));
            Settings::put('payments', $payments, $actorId);
            // Carts may hold coupons whose thresholds changed — prices are recalculated anyway.
            Settings::put('currency', ['base' => $new, 'display' => [], 'default_display' => '', 'show_switcher' => (bool) Settings::get('currency.show_switcher', true), 'auto_rates' => (bool) Settings::get('currency.auto_rates', true)], $actorId);
            Audit::log('currency.change_base', 'settings', 'currency', ['base' => $old], ['base' => $new, 'rate' => $rate, 'converted' => $counts]);
            self::$current = null;
            return $counts;
        });
    }
}
