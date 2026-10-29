<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Payments\Registry;
use App\Payments\UnavailableProvider;
use App\Services\Audit;
use App\Services\Settings;
use App\Services\SettingsSchema;

final class SettingsController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('settings/index', ['s' => Settings::all()], 'Store settings');
    }

    public function save(Request $r): Response
    {
        $group = $r->params['group'];
        if ($group === 'payments_credentials') {
            \App\Auth\Auth::authorize('payments.configure');
            $provider = Registry::get($r->str('provider'));
            if (!$provider instanceof UnavailableProvider) {
                throw new HttpException(404);
            }
            $changed = \App\Payments\Credentials::save($r->arr('cred'), $provider->envKeys(), array_map('strval', $r->arr('clear')), $this->actorId());
            // Record which keys changed — never their values.
            Audit::log('payments.credentials', 'settings', $provider->code(), null, ['changed' => $changed]);
            return $this->redirect('/admin/settings/payments#gw-' . $provider->code(), $changed ? $provider->label() . ' credentials saved (' . count($changed) . ' updated).' : 'No changes — leave a field blank to keep its saved value.');
        }
        if ($group === 'currency_display') {
            $auto = $r->bool('auto_rates');
            $rates = [];
            foreach ($r->arr('display') as $code => $row) {
                $code = strtoupper((string) $code);
                if (!isset(\App\Core\Money::CURRENCIES[$code]) || $code === \App\Services\Currency::base() || empty($row['enabled'])) {
                    continue;
                }
                // With automatic rates a manual rate is optional (used only if the live source is unavailable).
                $rate = trim((string) ($row['rate'] ?? ''));
                if ($rate !== '' && !\App\Core\Money::validRate($rate)) {
                    throw \App\Core\ValidationException::one('display', "The rate for {$code} must be a positive number (e.g. 0.0036).");
                }
                if ($rate === '' && (!$auto || \App\Services\ExchangeRates::rate($code) === null)) {
                    throw \App\Core\ValidationException::one('display', "No live rate is available for {$code} yet — enter a rate or click “Refresh rates now”.");
                }
                $rates[$code] = $rate;
            }
            $before = Settings::get('currency', []);
            $default = strtoupper($r->str('default_display'));
            if ($default !== '' && !isset($rates[$default])) {
                $default = ''; // must be an enabled display currency; '' = store currency
            }
            $value = ['base' => (string) ($before['base'] ?? ''), 'display' => $rates, 'default_display' => $default, 'show_switcher' => $r->bool('show_switcher'), 'auto_rates' => $auto];
            Settings::put('currency', $value, $this->actorId());
            Audit::log('settings.currency_display', 'settings', 'currency', ['display' => $before['display'] ?? []], ['display' => $rates, 'default_display' => $default, 'show_switcher' => $value['show_switcher']]);
            return $this->redirect('/admin/settings/currency', $rates ? 'Display currencies saved (' . implode(', ', array_keys($rates)) . ').' : 'Display currencies turned off.');
        }
        if ($group === 'payments_prices_refresh') {
            \App\Auth\Auth::authorize('payments.configure');
            $ok = \App\Services\CryptoPrices::refresh();
            Audit::log('payments.prices_refresh', 'settings', 'crypto_prices', null, ['success' => $ok]);
            return $ok
                ? $this->redirect('/admin/settings/payments', 'Live BTC/ETH prices updated.')
                : $this->redirect('/admin/settings/payments', null, 'Could not reach the price service. BTC/ETH stay hidden at checkout until prices are available.');
        }
        if ($group === 'currency_refresh') {
            $ok = \App\Services\ExchangeRates::refresh();
            Audit::log('settings.currency_refresh', 'settings', 'currency', null, ['success' => $ok]);
            return $ok
                ? $this->redirect('/admin/settings/currency', 'Live exchange rates updated.')
                : $this->redirect('/admin/settings/currency', null, 'Could not reach the exchange-rate service. The previous rates are still in use.');
        }
        if ($group === 'currency_base') {
            // The form asks for the rate the way it is usually quoted: "1 NEW = rate OLD"
            // (e.g. 1 USD = 276.85 PKR). Conversion needs "1 OLD = x NEW", i.e. 1 / rate.
            $d = Validator::make($r->post, ['base' => 'required|regex:/^[A-Z]{3}$/', 'rate' => 'required|string|max:24', 'confirm' => 'bool'])->validated();
            $old = \App\Services\Currency::base();
            $quoted = str_replace([',', ' '], '', $d['rate']);
            if (!isset(\App\Core\Money::CURRENCIES[$d['base']]) || $d['base'] === $old) {
                throw \App\Core\ValidationException::one('base', 'Choose a different supported currency.');
            }
            if (!\App\Core\Money::validRate($quoted)) {
                throw \App\Core\ValidationException::one('rate', "Enter how many {$old} one {$d['base']} is worth, e.g. 276.85.");
            }
            $convRate = rtrim(rtrim(bcdiv('1', $quoted, 10), '0'), '.');
            if (!\App\Core\Money::validRate($convRate)) {
                throw \App\Core\ValidationException::one('rate', 'That exchange rate is too large to convert accurately.');
            }
            if (!$d['confirm']) {
                // Step 1: show real before/after prices and ask for confirmation.
                $samples = [];
                foreach (Database::get()->all('SELECT title, price_minor FROM products ORDER BY price_minor DESC LIMIT 4') as $p) {
                    $samples[] = [$p['title'], money((int) $p['price_minor'], $old), \App\Core\Money::format(\App\Core\Money::convert((int) $p['price_minor'], $old, $d['base'], $convRate), $d['base'])];
                }
                $rate = Database::get()->one('SELECT name, price_minor FROM shipping_rates ORDER BY id LIMIT 1');
                if ($rate) {
                    $samples[] = ['Delivery: ' . $rate['name'], money((int) $rate['price_minor'], $old), \App\Core\Money::format(\App\Core\Money::convert((int) $rate['price_minor'], $old, $d['base'], $convRate), $d['base'])];
                }
                return $this->admin('settings/currency', array_merge($this->currencyData(), [
                    'preview' => ['new' => $d['base'], 'quoted' => $quoted, 'samples' => $samples],
                ]), 'Currency');
            }
            $counts = \App\Services\Currency::changeBase($d['base'], $convRate, $this->actorId());
            return $this->redirect('/admin/settings/currency', "Store currency changed to {$d['base']}. Converted {$counts['products']} products, {$counts['variants']} variant prices, {$counts['shipping_rates']} delivery rates and {$counts['coupons']} coupons. Please review prices.");
        }
        if (!in_array($group, SettingsSchema::OWNER_GROUPS, true)) {
            throw new HttpException(404);
        }
        if (str_starts_with($group, 'payments_')) {
            \App\Auth\Auth::authorize('payments.configure');
        }
        [$key, $value] = SettingsSchema::build($group, $r->post);
        $before = Settings::get($key, []);
        Settings::put($key, $value, $this->actorId());
        [$b, $a] = Audit::diff(is_array($before) ? $before : [], $value);
        Audit::log('settings.' . $group, 'settings', $key, $b, $a);
        $back = str_starts_with($group, 'payments_') ? '/admin/settings/payments' : ($group === 'shipping' ? '/admin/settings/shipping' : ($group === 'marketplace' ? '/admin/sellers' : '/admin/settings'));
        return $this->redirect($back, 'Settings saved.');
    }

    public function currency(Request $r): Response
    {
        return $this->admin('settings/currency', $this->currencyData(), 'Currency');
    }

    private function currencyData(): array
    {
        $db = Database::get();
        return [
            'base' => \App\Services\Currency::base(),
            'rates' => (array) Settings::get('currency.display', []),
            'showSwitcher' => (bool) Settings::get('currency.show_switcher', true),
            'defaultDisplay' => (string) Settings::get('currency.default_display', ''),
            'autoRates' => (bool) Settings::get('currency.auto_rates', true),
            'live' => \App\Services\ExchangeRates::cached(),
            'effective' => \App\Services\Currency::displayRates(),
            'productCount' => (int) $db->value('SELECT COUNT(*) FROM products'),
            'otherCurrencyOrders' => (int) $db->value('SELECT COUNT(*) FROM orders WHERE currency <> ?', [\App\Services\Currency::base()]),
            'preview' => null,
        ];
    }

    public function payments(Request $r): Response
    {
        $rows = [];
        foreach (Registry::all() as $code => $p) {
            $rows[$code] = [
                'p' => $p,
                'implemented' => $p->isImplemented(),
                'configured' => $p->isConfigured(),
                'enabled' => $p->isEnabled(),
                // Report presence/source only — secret values are never displayed.
                'env' => $p instanceof UnavailableProvider ? array_map(static fn ($k) => ['key' => $k, 'source' => \App\Payments\Credentials::source($k)], $p->envKeys()) : [],
            ];
        }
        if (!\App\Services\CryptoPrices::isFresh()) {
            \App\Services\CryptoPrices::refresh(true);
        }
        return $this->admin('settings/payments', [
            'rows' => $rows, 's' => Settings::get('payments'), 'mode' => Config::get('payments.mode'), 'production' => Config::isProduction(),
            'prices' => \App\Services\CryptoPrices::cached(), 'pricesFresh' => \App\Services\CryptoPrices::isFresh(),
            'reservationMinutes' => (int) Settings::get('checkout.reservation_minutes', 30),
        ], 'Payment methods');
    }

    public function shipping(Request $r): Response
    {
        $db = Database::get();
        $zones = $db->all('SELECT * FROM shipping_zones ORDER BY sort_order, id');
        foreach ($zones as &$z) {
            $z['rates'] = $db->all('SELECT * FROM shipping_rates WHERE zone_id = ? ORDER BY sort_order, id', [$z['id']]);
        }
        unset($z);
        return $this->admin('settings/shipping', ['zones' => $zones, 'couriers' => $db->all('SELECT * FROM couriers ORDER BY name'), 's' => Settings::get('shipping')], 'Shipping & delivery');
    }

    public function saveZone(Request $r): Response
    {
        $d = Validator::make($r->post, ['id' => 'int', 'name' => 'required|string|max:120', 'cities' => 'string|max:10000', 'provinces' => 'string|max:2000', 'is_fallback' => 'bool', 'is_active' => 'bool', 'sort_order' => 'int|min:0|max:999', 'delete' => 'bool'])->validated();
        $db = Database::get();
        if ($d['id'] && $d['delete']) {
            $db->exec('DELETE FROM shipping_zones WHERE id = ?', [$d['id']]);
            Audit::log('shipping.zone_delete', 'shipping_zone', $d['id']);
            return $this->redirect('/admin/settings/shipping', 'Zone deleted.');
        }
        $norm = static fn (?string $s) => implode("\n", array_unique(array_filter(array_map(static fn ($x) => mb_strtolower(trim($x)), preg_split('/[\r\n,]+/', (string) $s) ?: []))));
        $vals = [$d['name'], $norm($d['cities']), $norm($d['provinces']), $d['is_fallback'], $d['is_active'], $d['sort_order'] ?? 0];
        $db->transaction(static function (Database $db) use ($d, $vals): void {
            if ($d['is_fallback']) {
                $db->exec('UPDATE shipping_zones SET is_fallback = 0');
            }
            if ($d['id']) {
                $db->exec('UPDATE shipping_zones SET name=?, cities=?, provinces=?, is_fallback=?, is_active=?, sort_order=? WHERE id = ?', [...$vals, $d['id']]);
            } else {
                $db->insert('INSERT INTO shipping_zones (name, cities, provinces, is_fallback, is_active, sort_order) VALUES (?,?,?,?,?,?)', $vals);
            }
        });
        Audit::log('shipping.zone_save', 'shipping_zone', $d['id'], null, ['name' => $d['name']]);
        return $this->redirect('/admin/settings/shipping', 'Zone saved.');
    }

    public function saveRate(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int', 'zone_id' => 'required|int|min:1', 'name' => 'required|string|max:120', 'price' => 'required|money', 'free_over' => 'money',
            'min_days' => 'required|int|min:0|max:60', 'max_days' => 'required|int|min:0|max:60', 'cod_available' => 'bool', 'is_active' => 'bool', 'sort_order' => 'int|min:0|max:999', 'delete' => 'bool',
        ])->validated();
        $db = Database::get();
        if ($d['id'] && $d['delete']) {
            $db->exec('DELETE FROM shipping_rates WHERE id = ?', [$d['id']]);
            Audit::log('shipping.rate_delete', 'shipping_rate', $d['id']);
            return $this->redirect('/admin/settings/shipping', 'Rate deleted.');
        }
        if ($d['max_days'] < $d['min_days']) {
            throw \App\Core\ValidationException::one('max_days', 'Maximum days must be at least the minimum days.');
        }
        $vals = [$d['zone_id'], $d['name'], $d['price'], $d['free_over'], $d['min_days'], $d['max_days'], $d['cod_available'], $d['is_active'], $d['sort_order'] ?? 0];
        if ($d['id']) {
            $db->exec('UPDATE shipping_rates SET zone_id=?, name=?, price_minor=?, free_over_minor=?, min_days=?, max_days=?, cod_available=?, is_active=?, sort_order=? WHERE id = ?', [...$vals, $d['id']]);
        } else {
            $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, free_over_minor, min_days, max_days, cod_available, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?)', $vals);
        }
        Audit::log('shipping.rate_save', 'shipping_rate', $d['id'], null, ['name' => $d['name'], 'price_minor' => $d['price']]);
        return $this->redirect('/admin/settings/shipping', 'Rate saved.');
    }

    public function saveCourier(Request $r): Response
    {
        $d = Validator::make($r->post, ['id' => 'int', 'name' => 'required|string|max:80', 'tracking_url_template' => 'string|max:255', 'is_active' => 'bool'])->validated();
        $tpl = $d['tracking_url_template'];
        if ($tpl && (!str_contains($tpl, '{tracking}') || !preg_match('#^https://#i', $tpl))) {
            throw \App\Core\ValidationException::one('tracking_url_template', 'Use an https:// URL containing {tracking}, e.g. https://courier.example/track?cn={tracking}');
        }
        $db = Database::get();
        if ($d['id']) {
            $db->exec('UPDATE couriers SET name=?, tracking_url_template=?, is_active=? WHERE id = ?', [$d['name'], $tpl, $d['is_active'], $d['id']]);
        } else {
            $db->exec('INSERT INTO couriers (name, tracking_url_template, is_active) VALUES (?,?,?) ON DUPLICATE KEY UPDATE tracking_url_template = VALUES(tracking_url_template), is_active = VALUES(is_active)', [$d['name'], $tpl, $d['is_active']]);
        }
        Audit::log('shipping.courier_save', 'courier', $d['id'], null, ['name' => $d['name']]);
        return $this->redirect('/admin/settings/shipping', 'Courier saved.');
    }
}
