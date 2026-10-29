<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Analytics with explicit definitions (shown in the admin UI):
 *
 *  Gross sales  = Σ item price × qty (before discounts) for orders placed in the
 *                 period that are not cancelled/pending/awaiting payment.
 *  Discounts    = Σ order discounts on those orders.
 *  Refunds      = Σ refunds recorded in the period (by refund date).
 *  Net sales    = Gross sales − Discounts − Refunds.
 *  Revenue      = Σ (total − refunded) of orders whose payment was COLLECTED
 *                 (paid / partially refunded), by order date. Includes shipping.
 *
 * Always excludes sandbox test orders (is_test). In production also excludes
 * development demo rows (is_demo).
 */
final class Reports
{
    public const DEFINITIONS = [
        'gross' => 'Item value before discounts for orders placed in the period (excludes cancelled and unpaid online orders).',
        'net' => 'Gross sales minus discounts and refunds issued in the period.',
        'revenue' => 'Money actually collected (paid orders, net of refunds), including delivery charges.',
        'refunds' => 'Refunds recorded during the period.',
    ];

    /** Base WHERE for orders included in analytics. */
    public static function scope(string $alias = 'o'): string
    {
        // Only orders in the current base currency are summed (amounts in different currencies are not added together).
        $w = "{$alias}.is_test = 0 AND {$alias}.currency = '" . Currency::base() . "'";
        if (Config::isProduction()) {
            $w .= " AND {$alias}.is_demo = 0";
        }
        return $w;
    }

    /** UTC range for business-local Y-m-d dates (inclusive). */
    public static function range(?string $from, ?string $to): array
    {
        $tz = new \DateTimeZone((string) Config::get('timezone'));
        $today = new \DateTimeImmutable('today', $tz);
        $f = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : $today->modify('-29 days')->format('Y-m-d');
        $t = $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today->format('Y-m-d');
        if ($f > $t) {
            [$f, $t] = [$t, $f];
        }
        return ['from' => $f, 'to' => $t, 'from_utc' => local_to_utc($f), 'to_utc' => local_to_utc($t, true)];
    }

    private static function saleWhere(): string
    {
        return self::scope() . " AND o.status IN ('" . implode("','", OrderState::SALE_STATUSES) . "') AND o.placed_at BETWEEN ? AND ?";
    }

    public static function summary(array $r): array
    {
        $db = Database::get();
        $p = [$r['from_utc'], $r['to_utc']];
        $s = $db->one(
            'SELECT COUNT(*) AS orders, COALESCE(SUM(o.subtotal_minor),0) AS gross, COALESCE(SUM(o.discount_minor),0) AS discounts, COALESCE(SUM(o.shipping_minor),0) AS shipping
             FROM orders o WHERE ' . self::saleWhere(),
            $p
        );
        $refunds = (int) $db->value(
            'SELECT COALESCE(SUM(rf.amount_minor),0) FROM refunds rf JOIN orders o ON o.id = rf.order_id WHERE ' . self::scope() . ' AND rf.created_at BETWEEN ? AND ?',
            $p
        );
        $revenue = (int) $db->value(
            "SELECT COALESCE(SUM(o.total_minor - o.refunded_minor),0) FROM orders o WHERE " . self::scope() . " AND o.payment_status IN ('paid','partially_refunded') AND o.placed_at BETWEEN ? AND ?",
            $p
        );
        $all = $db->one(
            "SELECT COUNT(*) AS placed,
                    SUM(o.status IN ('pending','awaiting_payment','confirmed','processing','packed')) AS open_orders,
                    SUM(o.status = 'delivered') AS completed,
                    SUM(o.status = 'cancelled') AS cancelled
             FROM orders o WHERE " . self::scope() . ' AND o.placed_at BETWEEN ? AND ?',
            $p
        );
        $gross = (int) $s['gross'];
        $net = $gross - (int) $s['discounts'] - $refunds;
        return [
            'orders' => (int) $s['orders'],
            'placed' => (int) $all['placed'],
            'open' => (int) $all['open_orders'],
            'completed' => (int) $all['completed'],
            'cancelled' => (int) $all['cancelled'],
            'gross' => $gross,
            'discounts' => (int) $s['discounts'],
            'refunds' => $refunds,
            'net' => $net,
            'revenue' => $revenue,
            'aov' => $s['orders'] ? intdiv($gross - (int) $s['discounts'], (int) $s['orders'] * 100) * 100 : 0, // whole rupees
        ];
    }

    /** Daily gross sales in the business timezone (gap-filled). */
    public static function daily(array $r): array
    {
        $tz = new \DateTimeZone((string) Config::get('timezone'));
        $offset = $tz->getOffset(new \DateTimeImmutable($r['from'], $tz));
        $rows = Database::get()->all(
            'SELECT DATE(DATE_ADD(o.placed_at, INTERVAL ? SECOND)) AS d, COUNT(*) AS orders, SUM(o.subtotal_minor - o.discount_minor) AS sales
             FROM orders o WHERE ' . self::saleWhere() . ' GROUP BY d ORDER BY d',
            [$offset, $r['from_utc'], $r['to_utc']]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['d']] = $row;
        }
        $out = [];
        $d = new \DateTimeImmutable($r['from']);
        $end = new \DateTimeImmutable($r['to']);
        while ($d <= $end) {
            $k = $d->format('Y-m-d');
            $out[] = ['date' => $k, 'orders' => (int) ($map[$k]['orders'] ?? 0), 'sales' => (int) ($map[$k]['sales'] ?? 0)];
            $d = $d->modify('+1 day');
        }
        return $out;
    }

    public static function byStatus(array $r): array
    {
        return Database::get()->all(
            'SELECT o.status, COUNT(*) AS n, SUM(o.total_minor) AS total FROM orders o WHERE ' . self::scope() . ' AND o.placed_at BETWEEN ? AND ? GROUP BY o.status ORDER BY n DESC',
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function byPayment(array $r): array
    {
        return Database::get()->all(
            'SELECT o.payment_method, o.payment_status, COUNT(*) AS n, SUM(o.total_minor) AS total FROM orders o WHERE ' . self::scope() . ' AND o.placed_at BETWEEN ? AND ? GROUP BY o.payment_method, o.payment_status ORDER BY o.payment_method, n DESC',
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function topProducts(array $r, int $limit = 10): array
    {
        return Database::get()->all(
            'SELECT oi.product_id, oi.product_title, SUM(oi.quantity) AS units, SUM(oi.line_total_minor) AS sales, SUM(oi.refunded_quantity) AS refunded_units
             FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE ' . self::saleWhere() . '
             GROUP BY oi.product_id, oi.product_title ORDER BY units DESC LIMIT ' . max(1, $limit),
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function coupons(array $r): array
    {
        return Database::get()->all(
            'SELECT c.code, COUNT(cr.id) AS uses, SUM(cr.discount_minor) AS discount, SUM(o.total_minor) AS order_value
             FROM coupon_redemptions cr JOIN coupons c ON c.id = cr.coupon_id JOIN orders o ON o.id = cr.order_id
             WHERE cr.voided_at IS NULL AND ' . self::scope() . ' AND o.placed_at BETWEEN ? AND ? GROUP BY c.code ORDER BY uses DESC',
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function refundsList(array $r): array
    {
        return Database::get()->all(
            'SELECT rf.*, o.order_number, u.name AS actor_name FROM refunds rf JOIN orders o ON o.id = rf.order_id LEFT JOIN users u ON u.id = rf.actor_id
             WHERE ' . self::scope() . ' AND rf.created_at BETWEEN ? AND ? ORDER BY rf.created_at DESC',
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function customers(array $r, int $limit = 10): array
    {
        return Database::get()->all(
            "SELECT o.email, MAX(o.customer_name) AS name, COUNT(*) AS orders, SUM(o.total_minor - o.refunded_minor) AS spend
             FROM orders o WHERE " . self::saleWhere() . ' GROUP BY o.email ORDER BY spend DESC LIMIT ' . max(1, $limit),
            [$r['from_utc'], $r['to_utc']]
        );
    }

    public static function inventory(): array
    {
        return Database::get()->all(
            "SELECT p.title, p.sku AS product_sku, v.sku, v.size, v.color, v.stock, v.low_stock_threshold,
                    CASE WHEN v.stock = 0 THEN 'out' WHEN v.stock <= v.low_stock_threshold THEN 'low' ELSE 'ok' END AS level
             FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status <> 'archived' AND v.is_active = 1
             ORDER BY (v.stock = 0) DESC, (v.stock <= v.low_stock_threshold) DESC, v.stock, p.title"
        );
    }

    public static function counts(): array
    {
        $db = Database::get();
        $demo = Config::isProduction() ? ' AND is_demo = 0' : '';
        return [
            'customers' => (int) $db->value("SELECT COUNT(*) FROM users WHERE role = 'customer'" . $demo),
            'products' => (int) $db->value("SELECT COUNT(*) FROM products WHERE status = 'active'" . $demo),
            'drafts' => (int) $db->value("SELECT COUNT(*) FROM products WHERE status = 'draft'" . $demo),
            'awaiting_verification' => (int) $db->value("SELECT COUNT(*) FROM payments WHERE status = 'awaiting_verification'"),
            'to_fulfil' => (int) $db->value("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing','packed') AND is_test = 0" . $demo),
            'messages' => (int) $db->value('SELECT COUNT(*) FROM contact_messages WHERE handled = 0'),
        ] + Inventory::lowStockCount();
    }
}
