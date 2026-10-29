<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * Coupon eligibility (server-side only) and race-safe redemption.
 */
final class Coupons
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public static function find(string $code, bool $forUpdate = false): ?array
    {
        $code = self::normalize($code);
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            return null;
        }
        return Database::get()->one('SELECT * FROM coupons WHERE code = ?' . ($forUpdate ? ' FOR UPDATE' : ''), [$code]);
    }

    /** @return array{product:list<int>,collection:list<int>,category:list<int>} */
    public static function targets(int $couponId): array
    {
        $out = ['product' => [], 'collection' => [], 'category' => []];
        foreach (Database::get()->all('SELECT target_type, target_id FROM coupon_targets WHERE coupon_id = ?', [$couponId]) as $r) {
            $out[$r['target_type']][] = (int) $r['target_id'];
        }
        return $out;
    }

    /**
     * Validates a coupon against cart lines and customer. Returns the coupon
     * with an `eligible_keys` list, or throws ValidationException('coupon').
     *
     * @param list<array{key:string|int, product_id:int, category_id:?int, collection_ids:list<int>, unit:int, qty:int}> $lines
     */
    public static function evaluate(array $coupon, array $lines, ?int $userId, ?string $email, ?string $nowUtc = null): array
    {
        $now = $nowUtc ?? now_utc();
        $fail = static fn (string $m) => throw ValidationException::one('coupon', $m);

        if (!$coupon['is_active']) {
            $fail('This code is not active.');
        }
        if ($coupon['starts_at'] && $coupon['starts_at'] > $now) {
            $fail('This code is not active yet.');
        }
        if ($coupon['ends_at'] && $coupon['ends_at'] < $now) {
            $fail('This code has expired.');
        }
        if ($coupon['usage_limit'] !== null && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit']) {
            $fail('This code has reached its usage limit.');
        }

        $targets = $coupon['scope'] === 'all' ? null : self::targets((int) $coupon['id']);
        $eligibleKeys = [];
        $eligibleSubtotal = 0;
        $subtotal = 0;
        foreach ($lines as $l) {
            $lt = (int) $l['unit'] * (int) $l['qty'];
            $subtotal += $lt;
            $ok = match ($coupon['scope']) {
                'all' => true,
                'products' => in_array((int) $l['product_id'], $targets['product'], true),
                'categories' => $l['category_id'] !== null && in_array((int) $l['category_id'], $targets['category'], true),
                'collections' => (bool) array_intersect(array_map('intval', $l['collection_ids']), $targets['collection']),
                default => false,
            };
            if ($ok) {
                $eligibleKeys[] = $l['key'];
                $eligibleSubtotal += $lt;
            }
        }
        if ($eligibleSubtotal <= 0) {
            $fail('This code does not apply to the items in your bag.');
        }
        if ((int) $coupon['min_subtotal_minor'] > 0 && $subtotal < (int) $coupon['min_subtotal_minor']) {
            $fail('Spend at least ' . money((int) $coupon['min_subtotal_minor']) . ' to use this code.');
        }

        if ($coupon['per_customer_limit'] !== null) {
            if ($userId === null && ($email === null || $email === '')) {
                // Checked again at placement once the email is known.
            } else {
                $used = self::customerUses((int) $coupon['id'], $userId, $email);
                if ($used >= (int) $coupon['per_customer_limit']) {
                    $fail('You have already used this code the maximum number of times.');
                }
            }
        }

        $coupon['eligible_keys'] = $eligibleKeys;
        return $coupon;
    }

    public static function customerUses(int $couponId, ?int $userId, ?string $email): int
    {
        $email = $email !== null ? mb_strtolower(trim($email)) : '';
        return (int) Database::get()->value(
            'SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ? AND voided_at IS NULL AND ((? IS NOT NULL AND user_id = ?) OR (? <> \'\' AND email = ?))',
            [$couponId, $userId, $userId, $email, $email]
        );
    }

    /**
     * Records a redemption inside the checkout transaction. The coupon row is
     * locked (FOR UPDATE) by the caller; the conditional UPDATE is the final
     * guard against exceeding the global usage limit under concurrency.
     */
    public static function redeem(array $coupon, int $orderId, ?int $userId, string $email, int $discountMinor): void
    {
        $db = Database::get();
        $n = $db->exec(
            'UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ? AND is_active = 1 AND (usage_limit IS NULL OR usage_count < usage_limit)',
            [$coupon['id']]
        );
        if ($n !== 1) {
            throw ValidationException::one('coupon', 'This code has just reached its usage limit.');
        }
        $db->insert(
            'INSERT INTO coupon_redemptions (coupon_id, order_id, user_id, email, discount_minor) VALUES (?, ?, ?, ?, ?)',
            [$coupon['id'], $orderId, $userId, mb_strtolower(trim($email)), $discountMinor]
        );
    }

    /** Releases a redemption when an order is cancelled before fulfilment. */
    public static function release(int $orderId): void
    {
        $db = Database::get();
        $r = $db->one('SELECT * FROM coupon_redemptions WHERE order_id = ? AND voided_at IS NULL FOR UPDATE', [$orderId]);
        if (!$r) {
            return;
        }
        $db->exec('UPDATE coupon_redemptions SET voided_at = UTC_TIMESTAMP() WHERE id = ?', [$r['id']]);
        $db->exec('UPDATE coupons SET usage_count = usage_count - 1 WHERE id = ? AND usage_count > 0', [$r['coupon_id']]);
    }
}
