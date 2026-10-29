<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Services\Audit;
use App\Services\OrderState;
use App\Services\Reports;

final class ReportController extends AdminController
{
    public function index(Request $r): Response
    {
        $range = Reports::range($r->query['from'] ?? null, $r->query['to'] ?? null);
        return $this->admin('reports/index', [
            'range' => $range,
            'summary' => Reports::summary($range),
            'daily' => Reports::daily($range),
            'statuses' => Reports::byStatus($range),
            'payments' => Reports::byPayment($range),
            'products' => Reports::topProducts($range, 20),
            'coupons' => Reports::coupons($range),
            'refunds' => Reports::refundsList($range),
            'customers' => Reports::customers($range, 10),
            'inventory' => array_slice(Reports::inventory(), 0, 30),
        ], 'Reports');
    }

    public function export(Request $r): Response
    {
        $range = Reports::range($r->query['from'] ?? null, $r->query['to'] ?? null);
        $type = $r->params['type'];
        $d = static fn ($m) => Money::decimal((int) $m);
        [$header, $rows] = match ($type) {
            'sales' => [['date', 'orders', 'net_sales'], array_map(static fn ($x) => [$x['date'], $x['orders'], $d($x['sales'])], Reports::daily($range))],
            'status' => [['status', 'orders', 'value'], array_map(static fn ($x) => [OrderState::label($x['status']), $x['n'], $d($x['total'])], Reports::byStatus($range))],
            'payments' => [['method', 'payment_status', 'orders', 'value'], array_map(static fn ($x) => [$x['payment_method'], $x['payment_status'], $x['n'], $d($x['total'])], Reports::byPayment($range))],
            'products' => [['product', 'units', 'sales', 'refunded_units'], array_map(static fn ($x) => [$x['product_title'], $x['units'], $d($x['sales']), $x['refunded_units']], Reports::topProducts($range, 1000))],
            'coupons' => [['code', 'uses', 'discount', 'order_value'], array_map(static fn ($x) => [$x['code'], $x['uses'], $d($x['discount']), $d($x['order_value'])], Reports::coupons($range))],
            'refunds' => [['date', 'order', 'amount', 'method', 'reason', 'by'], array_map(static fn ($x) => [dt($x['created_at'], 'Y-m-d H:i'), $x['order_number'], $d($x['amount_minor']), $x['method'], $x['reason'], $x['actor_name']], Reports::refundsList($range))],
            'customers' => [['customer', 'orders', 'spend'], array_map(static fn ($x) => [$x['name'], $x['orders'], $d($x['spend'])], Reports::customers($range, 1000))],
            'inventory' => [['product', 'sku', 'size', 'color', 'stock', 'low_threshold', 'level'], array_map(static fn ($x) => [$x['title'], $x['sku'], $x['size'], $x['color'], $x['stock'], $x['low_stock_threshold'], $x['level']], Reports::inventory())],
            default => throw new HttpException(404),
        };
        Audit::log('export.report', 'export', $type, null, ['from' => $range['from'], 'to' => $range['to'], 'rows' => count($rows)]);
        return $this->csv("report-{$type}-{$range['from']}-to-{$range['to']}.csv", $header, $rows);
    }
}
