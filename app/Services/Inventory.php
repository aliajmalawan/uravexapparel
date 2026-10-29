<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Stock changes. Every change updates product_variants.stock AND appends an
 * inventory_movements row in the same transaction. Decrements use a
 * conditional UPDATE (stock >= qty) so concurrent checkouts can never oversell.
 */
final class Inventory
{
    public const REASONS = ['initial', 'restock', 'adjustment', 'damage', 'sale', 'cancellation', 'return', 'import'];

    /**
     * Applies a signed delta. Returns the new stock. Throws when the result
     * would be negative.
     */
    public static function apply(int $variantId, int $delta, string $reason, ?string $note = null, ?int $orderId = null, ?int $actorId = null): int
    {
        if (!in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException('Invalid stock reason');
        }
        $db = Database::get();
        return $db->transaction(static function (Database $db) use ($variantId, $delta, $reason, $note, $orderId, $actorId): int {
            if ($delta < 0) {
                $n = $db->exec('UPDATE product_variants SET stock = stock + ? WHERE id = ? AND stock >= ?', [$delta, $variantId, -$delta]);
                if ($n !== 1) {
                    throw ValidationException::one('stock', 'Not enough stock for this change.');
                }
            } elseif ($delta > 0) {
                $db->exec('UPDATE product_variants SET stock = stock + ? WHERE id = ?', [$delta, $variantId]);
            }
            $after = (int) $db->value('SELECT stock FROM product_variants WHERE id = ?', [$variantId]);
            $db->insert(
                'INSERT INTO inventory_movements (variant_id, delta, stock_after, reason, note, order_id, actor_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$variantId, $delta, $after, $reason, $note, $orderId, $actorId]
            );
            return $after;
        });
    }

    /** Sets absolute stock (admin count), recording the difference. */
    public static function set(int $variantId, int $newStock, string $reason, ?string $note, ?int $actorId): int
    {
        if ($newStock < 0) {
            throw ValidationException::one('stock', 'Stock cannot be negative.');
        }
        return Database::get()->transaction(static function (Database $db) use ($variantId, $newStock, $reason, $note, $actorId): int {
            $current = $db->value('SELECT stock FROM product_variants WHERE id = ? FOR UPDATE', [$variantId]);
            if ($current === null) {
                throw ValidationException::one('variant', 'Variant not found.');
            }
            $delta = $newStock - (int) $current;
            if ($delta === 0) {
                return $newStock;
            }
            return self::apply($variantId, $delta, $reason, $note, null, $actorId);
        });
    }

    public static function lowStockCount(): array
    {
        $db = Database::get();
        return [
            'low' => (int) $db->value("SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status <> 'archived' AND v.is_active = 1 AND v.stock > 0 AND v.stock <= v.low_stock_threshold"),
            'out' => (int) $db->value("SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status <> 'archived' AND v.is_active = 1 AND v.stock = 0"),
        ];
    }
}
