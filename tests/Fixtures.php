<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Core\Database;
use App\Services\Inventory;

/** Factories for integration tests. */
final class Fx
{
    public static function user(string $role = 'customer', string $email = null): array
    {
        $db = Database::get();
        $email ??= $role . '-' . bin2hex(random_bytes(4)) . '@example.test';
        $id = $db->insert('INSERT INTO users (name, email, phone, password_hash, role, email_verified_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())', ['Test ' . $role, $email, '03001234567', Auth::hashPassword('Password123'), $role]);
        return $db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Active product with one variant. Prices in minor units. */
    public static function product(int $price = 250000, ?int $sale = null, int $stock = 10, array $opts = []): array
    {
        $db = Database::get();
        $n = bin2hex(random_bytes(3));
        $pid = $db->insert(
            "INSERT INTO products (title, slug, sku, description, price_minor, sale_price_minor, status, published_at, category_id) VALUES (?, ?, ?, '', ?, ?, 'active', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY), ?)",
            ['Product ' . $n, 'product-' . $n, 'P-' . strtoupper($n), $price, $sale, $opts['category_id'] ?? null]
        );
        $vid = $db->insert("INSERT INTO product_variants (product_id, sku, size, color, stock) VALUES (?, ?, 'M', 'Black', 0)", [$pid, 'V-' . strtoupper($n)]);
        if ($stock > 0) {
            Inventory::apply($vid, $stock, 'initial');
        }
        return ['product_id' => $pid, 'variant_id' => $vid];
    }

    public static function shipping(int $price = 25000, bool $cod = true): int
    {
        $db = Database::get();
        $z = $db->insert("INSERT INTO shipping_zones (name, cities, provinces, is_fallback) VALUES ('All', 'lahore', '', 1)");
        return $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available) VALUES (?, ?, ?, 2, 3, ?)', [$z, 'Standard', $price, $cod]);
    }

    public static function coupon(array $over = []): array
    {
        $db = Database::get();
        $c = $over + ['code' => 'TEST' . strtoupper(bin2hex(random_bytes(2))), 'type' => 'percentage', 'value' => 10, 'usage_limit' => null, 'per_customer_limit' => null, 'min_subtotal_minor' => 0, 'scope' => 'all', 'free_shipping' => 0, 'max_discount_minor' => null];
        $id = $db->insert(
            'INSERT INTO coupons (code, type, value, usage_limit, per_customer_limit, min_subtotal_minor, scope, free_shipping, max_discount_minor) VALUES (?,?,?,?,?,?,?,?,?)',
            [$c['code'], $c['type'], $c['value'], $c['usage_limit'], $c['per_customer_limit'], $c['min_subtotal_minor'], $c['scope'], $c['free_shipping'], $c['max_discount_minor']]
        );
        return $db->one('SELECT * FROM coupons WHERE id = ?', [$id]);
    }

    /** Places an order through the real checkout service for the current cart. */
    public static function checkout(int $rateId, string $method = 'cod', array $over = []): array
    {
        $rate =Database::get()->one('SELECT * FROM shipping_rates WHERE id = ?', [$rateId]);
        $summary = App\Services\Cart::summary((int) $rate['price_minor'], App\Services\Shipping::freeOver($rate), $over['email'] ?? 'buyer@example.test');
        return App\Services\Checkout::place($over + [
            'email' => 'buyer@example.test', 'phone' => '03001234567', 'full_name' => 'Test Buyer',
            'line1' => 'House 1', 'line2' => null, 'city' => 'Lahore', 'province' => 'Punjab', 'postal_code' => null,
            'shipping_rate_id' => $rateId, 'payment_method' => $method, 'note' => null,
            'idempotency_key' => bin2hex(random_bytes(32)),
            'fingerprint' => App\Services\Pricing::fingerprint($summary['totals'], $summary['pricing_lines']),
            'billing_same' => true,
        ]);
    }

    public static function newGuest(): void
    {
        Auth::actingAs(null);
        App\Services\Cart::setGuestToken(bin2hex(random_bytes(32)));
    }
}
