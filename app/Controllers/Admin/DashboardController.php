<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Reports;

final class DashboardController extends AdminController
{
    public function index(Request $r): Response
    {
        $range = Reports::range($r->query['from'] ?? null, $r->query['to'] ?? null);
        $canAnalytics = Auth::can('analytics.view');
        return $this->admin('dashboard', [
            'range' => $range,
            'canAnalytics' => $canAnalytics,
            'summary' => $canAnalytics ? Reports::summary($range) : null,
            'daily' => $canAnalytics ? Reports::daily($range) : [],
            'statuses' => Reports::byStatus($range),
            'top' => $canAnalytics ? Reports::topProducts($range, 5) : [],
            'counts' => Reports::counts(),
            'recent' => Database::get()->all('SELECT id, order_number, customer_name, status, payment_status, payment_method, total_minor, currency, placed_at, is_test, is_demo FROM orders ORDER BY placed_at DESC LIMIT 8'),
        ], 'Dashboard');
    }
}
