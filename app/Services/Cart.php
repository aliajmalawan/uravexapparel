<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;

/**
 * Database-backed carts. Guests are identified by a random cookie token (only
 * its SHA-256 is stored); signed-in customers by user id. Prices are never
 * stored in the cart — they are recalculated from the catalog every time.
 */
final class Cart
{
    public const COOKIE = 'uravex_cart';
    public const MAX_QTY = 99;

    private static ?string $guestToken = null;

    public static function setGuestToken(?string $token): void
    {
        self::$guestToken = $token;
    }

    private static function guestToken(): ?string
    {
        if (self::$guestToken !== null) {
            return self::$guestToken;
        }
        $t = $_COOKIE[self::COOKIE] ?? null;
        return is_string($t) && preg_match('/^[a-f0-9]{64}$/', $t) ? $t : null;
    }

    public static function current(bool $create = false): ?array
    {
        $db = Database::get();
        $uid = Auth::id();
        if ($uid !== null) {
            $cart = $db->one('SELECT * FROM carts WHERE user_id = ?', [$uid]);
            if (!$cart && $create) {
                $db->exec('INSERT IGNORE INTO carts (user_id) VALUES (?)', [$uid]);
                $cart = $db->one('SELECT * FROM carts WHERE user_id = ?', [$uid]);
            }
            return $cart;
        }
        $token = self::guestToken();
        if ($token !== null) {
            $cart = $db->one('SELECT * FROM carts WHERE guest_token_hash = ?', [hash('sha256', $token)]);
            if ($cart || !$create) {
                return $cart;
            }
            // Valid cookie whose cart was purged: reuse the token.
            $db->exec('INSERT IGNORE INTO carts (guest_token_hash) VALUES (?)', [hash('sha256', $token)]);
            return $db->one('SELECT * FROM carts WHERE guest_token_hash = ?', [hash('sha256', $token)]);
        }
        if (!$create) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        self::$guestToken = $token;
        self::setCookie($token, time() + 60 * 60 * 24 * 30);
        $id = $db->insert('INSERT INTO carts (guest_token_hash) VALUES (?)', [hash('sha256', $token)]);
        return $db->one('SELECT * FROM carts WHERE id = ?', [$id]);
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => (Config::get('base_path') ?: '') . '/',
            'secure' => (bool) Config::get('https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Raw lines with live catalog data and availability flags. */
    public static function lines(int $cartId): array
    {
        $rows = Database::get()->all(
            'SELECT ci.id AS item_id, ci.quantity, v.id AS variant_id, v.sku, v.size, v.color, v.stock, v.is_active AS variant_active,
                    v.price_minor AS v_price, v.sale_price_minor AS v_sale,
                    p.id AS product_id, p.title, p.slug, p.status, p.published_at, p.price_minor, p.sale_price_minor, p.category_id, p.currency,
                    (SELECT path FROM product_images pi WHERE pi.product_id = p.id ORDER BY (pi.color <=> v.color) DESC, pi.position, pi.id LIMIT 1) AS image
             FROM cart_items ci
             JOIN product_variants v ON v.id = ci.variant_id
             JOIN products p ON p.id = v.product_id
             WHERE ci.cart_id = ? ORDER BY ci.id',
            [$cartId]
        );
        $collections = [];
        if ($rows) {
            $pids = array_values(array_unique(array_map(static fn ($r) => (int) $r['product_id'], $rows)));
            foreach (Database::get()->all('SELECT product_id, collection_id FROM product_collections WHERE product_id IN ' . Database::in($pids), $pids) as $pc) {
                $collections[$pc['product_id']][] = (int) $pc['collection_id'];
            }
        }
        foreach ($rows as &$r) {
            $pricing = Pricing::unitPrice(
                ['price_minor' => $r['price_minor'], 'sale_price_minor' => $r['sale_price_minor']],
                ['price_minor' => $r['v_price'], 'sale_price_minor' => $r['v_sale']]
            );
            $r['unit'] = $pricing['price'];
            $r['regular'] = $pricing['regular'];
            $r['on_sale'] = $pricing['on_sale'];
            $r['variant_label'] = self::variantLabel($r['size'], $r['color']);
            $r['collection_ids'] = $collections[$r['product_id']] ?? [];
            $visible = $r['status'] === 'active' && $r['published_at'] !== null && $r['published_at'] <= now_utc();
            $r['issue'] = match (true) {
                !$visible || !$r['variant_active'] => 'unavailable',
                (int) $r['stock'] <= 0 => 'out_of_stock',
                (int) $r['quantity'] > (int) $r['stock'] => 'insufficient',
                default => null,
            };
        }
        return $rows;
    }

    public static function variantLabel(string $size, string $color): string
    {
        return implode(' / ', array_filter([$color, $size], static fn ($s) => $s !== '')) ?: 'Standard';
    }

    public static function add(int $variantId, int $qty): array
    {
        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw ValidationException::one('quantity', 'Choose a quantity between 1 and ' . self::MAX_QTY . '.');
        }
        $db = Database::get();
        $v = $db->one(
            "SELECT v.*, p.title, p.status, p.published_at FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?",
            [$variantId]
        );
        if (!$v || !$v['is_active'] || $v['status'] !== 'active' || $v['published_at'] === null) {
            throw ValidationException::one('variant_id', 'This item is no longer available.');
        }
        if ((int) $v['stock'] <= 0) {
            throw ValidationException::one('variant_id', 'Sorry, this size is out of stock.');
        }
        $cart = self::current(true);
        $existing = (int) $db->value('SELECT quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?', [$cart['id'], $variantId]);
        $wanted = $existing + $qty;
        $allowed = min($wanted, (int) $v['stock'], self::MAX_QTY);
        $db->exec(
            'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
            [$cart['id'], $variantId, $allowed]
        );
        $db->exec('UPDATE carts SET updated_at = UTC_TIMESTAMP() WHERE id = ?', [$cart['id']]);
        return [
            'title' => $v['title'],
            'quantity' => $allowed,
            'limited' => $allowed < $wanted,
            'stock' => (int) $v['stock'],
        ];
    }

    public static function update(int $itemId, int $qty): ?string
    {
        $cart = self::current();
        if (!$cart) {
            return null;
        }
        $db = Database::get();
        $item = $db->one('SELECT ci.*, v.stock FROM cart_items ci JOIN product_variants v ON v.id = ci.variant_id WHERE ci.id = ? AND ci.cart_id = ?', [$itemId, $cart['id']]);
        if (!$item) {
            return null;
        }
        if ($qty <= 0) {
            $db->exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cart['id']]);
            return 'Item removed.';
        }
        $allowed = max(1, min($qty, (int) $item['stock'], self::MAX_QTY));
        $db->exec('UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?', [$allowed, $itemId, $cart['id']]);
        return $allowed < $qty ? "Only {$allowed} available — quantity adjusted." : 'Bag updated.';
    }

    public static function remove(int $itemId): void
    {
        $cart = self::current();
        if ($cart) {
            Database::get()->exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cart['id']]);
        }
    }

    public static function count(): int
    {
        $cart = self::current();
        return $cart ? (int) Database::get()->value('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?', [$cart['id']]) : 0;
    }

    public static function setCoupon(?string $code): void
    {
        $cart = self::current(true);
        Database::get()->exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$code, $cart['id']]);
    }

    public static function clear(int $cartId): void
    {
        $db = Database::get();
        $db->exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        $db->exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cartId]);
    }

    /**
     * Moves a guest cart into the signed-in user's cart. Quantities for the
     * same variant are summed and capped at current stock. Returns notices.
     *
     * @return list<string>
     */
    public static function mergeGuestInto(int $userId): array
    {
        $token = self::guestToken();
        if ($token === null) {
            return [];
        }
        $db = Database::get();
        $notices = [];
        $db->transaction(static function (Database $db) use ($token, $userId, &$notices): void {
            $guest = $db->one('SELECT * FROM carts WHERE guest_token_hash = ? FOR UPDATE', [hash('sha256', $token)]);
            if (!$guest) {
                return;
            }
            $db->exec('INSERT IGNORE INTO carts (user_id) VALUES (?)', [$userId]);
            $userCart = $db->one('SELECT * FROM carts WHERE user_id = ? FOR UPDATE', [$userId]);
            $items = $db->all(
                'SELECT ci.variant_id, ci.quantity, v.stock, p.title FROM cart_items ci JOIN product_variants v ON v.id = ci.variant_id JOIN products p ON p.id = v.product_id WHERE ci.cart_id = ?',
                [$guest['id']]
            );
            foreach ($items as $it) {
                $existing = (int) $db->value('SELECT quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?', [$userCart['id'], $it['variant_id']]);
                $wanted = $existing + (int) $it['quantity'];
                $allowed = min($wanted, (int) $it['stock'], self::MAX_QTY);
                if ($allowed <= 0) {
                    $notices[] = "{$it['title']} is out of stock and was not added to your bag.";
                    continue;
                }
                if ($allowed < $wanted) {
                    $notices[] = "Quantity of {$it['title']} was adjusted to {$allowed} (available stock).";
                }
                $db->exec(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                    [$userCart['id'], $it['variant_id'], $allowed]
                );
            }
            if ($guest['coupon_code'] && !$userCart['coupon_code']) {
                $db->exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$guest['coupon_code'], $userCart['id']]);
            }
            $db->exec('DELETE FROM carts WHERE id = ?', [$guest['id']]);
        });
        self::$guestToken = null;
        self::setCookie('', time() - 3600);
        return $notices;
    }

    /**
     * Full priced summary for cart/checkout pages. Invalid coupons are dropped
     * with a message rather than failing the page.
     *
     * @return array{cart:?array, lines:list<array>, totals:array, coupon:?array, coupon_error:?string, issues:bool, count:int}
     */
    public static function summary(?int $shippingMinor = null, ?int $freeOver = null, ?string $email = null): array
    {
        $cart = self::current();
        $lines = $cart ? self::lines((int) $cart['id']) : [];
        $coupon = null;
        $couponError = null;
        $priceable = array_values(array_filter($lines, static fn ($l) => $l['issue'] !== 'unavailable' && $l['issue'] !== 'out_of_stock'));
        $pricingLines = array_map(static fn ($l) => [
            // Keyed by variant id — must match Checkout::place() so the
            // fingerprint shown to the customer can be verified at submit.
            'key' => (int) $l['variant_id'],
            'unit' => $l['unit'],
            'qty' => min((int) $l['quantity'], max(0, (int) $l['stock'])),
            'product_id' => (int) $l['product_id'],
            'category_id' => $l['category_id'] !== null ? (int) $l['category_id'] : null,
            'collection_ids' => $l['collection_ids'],
        ], $priceable);

        if ($cart && $cart['coupon_code'] && $pricingLines) {
            $c = Coupons::find($cart['coupon_code']);
            if (!$c) {
                $couponError = 'That code is no longer valid and was removed.';
            } else {
                try {
                    $coupon = Coupons::evaluate($c, $pricingLines, Auth::id(), $email ?? (Auth::user()['email'] ?? null));
                } catch (ValidationException $e) {
                    $couponError = $e->errors['coupon'] ?? $e->getMessage();
                }
            }
            if ($couponError) {
                Database::get()->exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cart['id']]);
            }
        }
        if ($coupon) {
            foreach ($pricingLines as &$pl) {
                $pl['eligible'] = in_array($pl['key'], $coupon['eligible_keys'], true);
            }
            unset($pl);
        }
        $taxPercent = (int) Settings::get('checkout.tax_percent', 0);
        $totals = Pricing::totals($pricingLines, $coupon, $shippingMinor, $freeOver, $taxPercent);

        return [
            'cart' => $cart,
            'lines' => $lines,
            'pricing_lines' => $pricingLines,
            'totals' => $totals,
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'issues' => (bool) array_filter($lines, static fn ($l) => $l['issue'] !== null),
            'count' => array_sum(array_map(static fn ($l) => (int) $l['quantity'], $lines)),
        ];
    }

    /**
     * Removes unavailable/sold-out lines and caps quantities at stock so the
     * checkout always prices what can actually be bought. Returns notices.
     *
     * @return list<string>
     */
    public static function normalize(): array
    {
        $cart = self::current();
        if (!$cart) {
            return [];
        }
        $db = Database::get();
        $notices = [];
        foreach (self::lines((int) $cart['id']) as $l) {
            if ($l['issue'] === 'unavailable' || $l['issue'] === 'out_of_stock') {
                $db->exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$l['item_id'], $cart['id']]);
                $notices[] = "{$l['title']} ({$l['variant_label']}) is no longer available and was removed from your bag.";
            } elseif ($l['issue'] === 'insufficient') {
                $db->exec('UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?', [(int) $l['stock'], $l['item_id'], $cart['id']]);
                $notices[] = "Only {$l['stock']} of {$l['title']} ({$l['variant_label']}) available — quantity updated.";
            }
        }
        return $notices;
    }

    /** Deletes abandoned guest carts (cron). */
    public static function purgeStale(int $days = 45): int
    {
        return Database::get()->exec('DELETE FROM carts WHERE user_id IS NULL AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
    }
}
