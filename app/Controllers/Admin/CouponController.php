<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\Coupons;

final class CouponController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('coupons/index', [
            'rows' => Database::get()->all('SELECT c.*, (SELECT COALESCE(SUM(discount_minor),0) FROM coupon_redemptions WHERE coupon_id = c.id AND voided_at IS NULL) AS discounted FROM coupons c ORDER BY c.is_active DESC, c.created_at DESC'),
        ], 'Coupons');
    }

    public function edit(Request $r): Response
    {
        $db = Database::get();
        $c = isset($r->params['id']) ? $db->one('SELECT * FROM coupons WHERE id = ?', [(int) $r->params['id']]) : null;
        if (isset($r->params['id']) && !$c) {
            throw new HttpException(404);
        }
        return $this->admin('coupons/form', [
            'c' => $c,
            'targets' => $c ? Coupons::targets((int) $c['id']) : ['product' => [], 'collection' => [], 'category' => []],
            'products' => $db->all("SELECT id, title FROM products WHERE status <> 'archived' ORDER BY title"),
            'collections' => $db->all('SELECT id, name FROM collections ORDER BY name'),
            'categories' => $db->all('SELECT id, name FROM categories ORDER BY name'),
        ], $c ? 'Coupon ' . $c['code'] : 'New coupon');
    }

    /** Deletes an unused coupon; used coupons are deactivated to keep redemption history. */
    public function destroy(Request $r): Response
    {
        $db = Database::get();
        $c = $db->one('SELECT id, code FROM coupons WHERE id = ?', [(int) $r->params['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        if ($db->value('SELECT 1 FROM coupon_redemptions WHERE coupon_id = ? LIMIT 1', [$c['id']])) {
            $db->exec('UPDATE coupons SET is_active = 0 WHERE id = ?', [$c['id']]);
            Audit::log('coupon.deactivate', 'coupon', $c['id'], null, ['code' => $c['code']]);
            return $this->redirect('/admin/coupons', "{$c['code']} has been used, so it was deactivated instead of deleted.");
        }
        $db->exec('DELETE FROM coupons WHERE id = ?', [$c['id']]);
        Audit::log('coupon.delete', 'coupon', $c['id'], ['code' => $c['code']], null);
        return $this->redirect('/admin/coupons', "Coupon {$c['code']} deleted.");
    }

    public function save(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int',
            'code' => 'required|string|min:3|max:40|regex:/^[A-Za-z0-9_-]+$/',
            'description' => 'string|max:255',
            'type' => 'required|in:percentage,fixed,free_shipping',
            'percent' => 'int|min:1|max:100',
            'amount' => 'money',
            'free_shipping' => 'bool',
            'min_subtotal' => 'money',
            'max_discount' => 'money',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'int|min:1|max:1000000',
            'per_customer_limit' => 'int|min:1|max:1000',
            'scope' => 'required|in:all,products,collections,categories',
            'is_active' => 'bool',
        ])->validated();
        $value = match ($d['type']) {
            'percentage' => $d['percent'] ?? throw ValidationException::one('percent', 'Enter a percentage between 1 and 100.'),
            'fixed' => $d['amount'] ?? throw ValidationException::one('amount', 'Enter the discount amount.'),
            default => 0,
        };
        if ($d['type'] === 'fixed' && $value <= 0) {
            throw ValidationException::one('amount', 'Enter an amount greater than zero.');
        }
        $starts = local_to_utc($d['starts_at']);
        $ends = local_to_utc($d['ends_at']);
        if ($starts && $ends && $ends <= $starts) {
            throw ValidationException::one('ends_at', 'End date must be after the start date.');
        }
        $code = Coupons::normalize($d['code']);
        $db = Database::get();
        if ($db->value('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $d['id'] ?? 0])) {
            throw ValidationException::one('code', 'This code already exists.');
        }
        $targetMap = ['products' => ['product', $r->arr('product_ids')], 'collections' => ['collection', $r->arr('collection_ids')], 'categories' => ['category', $r->arr('category_ids')]];
        if ($d['scope'] !== 'all' && !array_filter($targetMap[$d['scope']][1])) {
            throw ValidationException::one('scope', 'Choose at least one ' . rtrim($d['scope'], 's') . ' for this coupon.');
        }
        $vals = [$code, $d['description'], $d['type'], $value, $d['free_shipping'] || $d['type'] === 'free_shipping', $d['min_subtotal'] ?? 0, $d['max_discount'], $starts, $ends, $d['usage_limit'], $d['per_customer_limit'], $d['scope'], $d['is_active']];

        $id = $db->transaction(static function (Database $db) use ($d, $vals, $targetMap): int {
            if ($d['id']) {
                $existing = $db->one('SELECT usage_count FROM coupons WHERE id = ? FOR UPDATE', [$d['id']]);
                if (!$existing) {
                    throw new HttpException(404);
                }
                if ($d['usage_limit'] !== null && $d['usage_limit'] < (int) $existing['usage_count']) {
                    throw ValidationException::one('usage_limit', 'Usage limit cannot be below the ' . $existing['usage_count'] . ' uses already recorded.');
                }
                $db->exec('UPDATE coupons SET code=?, description=?, type=?, value=?, free_shipping=?, min_subtotal_minor=?, max_discount_minor=?, starts_at=?, ends_at=?, usage_limit=?, per_customer_limit=?, scope=?, is_active=? WHERE id = ?', [...$vals, $d['id']]);
                $id = (int) $d['id'];
            } else {
                $id = $db->insert('INSERT INTO coupons (code, description, type, value, free_shipping, min_subtotal_minor, max_discount_minor, starts_at, ends_at, usage_limit, per_customer_limit, scope, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
            }
            $db->exec('DELETE FROM coupon_targets WHERE coupon_id = ?', [$id]);
            if ($d['scope'] !== 'all') {
                [$type, $ids] = $targetMap[$d['scope']];
                foreach (array_unique(array_map('intval', $ids)) as $tid) {
                    if ($tid > 0) {
                        $db->exec('INSERT IGNORE INTO coupon_targets (coupon_id, target_type, target_id) VALUES (?, ?, ?)', [$id, $type, $tid]);
                    }
                }
            }
            return $id;
        });
        Audit::log($d['id'] ? 'coupon.update' : 'coupon.create', 'coupon', $id, null, ['code' => $code, 'type' => $d['type'], 'value' => $value, 'active' => $d['is_active']]);
        return $this->redirect('/admin/coupons', "Coupon {$code} saved.");
    }
}
