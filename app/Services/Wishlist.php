<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Database;

/** Wishlists persist for signed-in customers only. */
final class Wishlist
{
    private static ?array $ids = null;

    /** @return list<int> product ids in the current user's wishlist */
    public static function ids(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }
        $uid = Auth::id();
        if ($uid === null) {
            return self::$ids = [];
        }
        return self::$ids = array_map('intval', Database::get()->column(
            'SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.user_id = ?',
            [$uid]
        ));
    }

    /** Toggles a product; returns true when it is now saved. */
    public static function toggle(int $userId, int $productId): bool
    {
        $db = Database::get();
        $exists = $db->value("SELECT id FROM products WHERE id = ? AND status = 'active'", [$productId]);
        if (!$exists) {
            throw new \App\Core\ValidationException(['product_id' => 'Product not found.']);
        }
        $db->exec('INSERT IGNORE INTO wishlists (user_id) VALUES (?)', [$userId]);
        $wid = (int) $db->value('SELECT id FROM wishlists WHERE user_id = ?', [$userId]);
        $removed = $db->exec('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wid, $productId]);
        self::$ids = null;
        if ($removed) {
            return false;
        }
        $db->exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wid, $productId]);
        return true;
    }

    public static function products(int $userId): array
    {
        $rows = Database::get()->all(
            "SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at, p.status
             FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id JOIN products p ON p.id = wi.product_id
             WHERE w.user_id = ? AND p.status = 'active' ORDER BY wi.created_at DESC",
            [$userId]
        );
        return Catalog::hydrateCards($rows);
    }
}
