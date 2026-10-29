<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Controllers\Controller;
use App\Core\Response;
use App\Core\View;

/**
 * Base for /admin controllers. Route middleware already enforces the staff
 * role and the route's permission; sensitive actions call Auth::authorize()
 * again inside the action (defence in depth).
 */
abstract class AdminController extends Controller
{
    protected function admin(string $template, array $data = [], string $title = 'Dashboard'): Response
    {
        View::share('user', Auth::user());
        try {
            \App\Services\ExchangeRates::refreshIfStale(); // keeps live display rates current (≤ 6 h old)
        } catch (\Throwable) {
        }
        return Response::html(View::render('admin/' . $template, $data + ['title' => $title], 'layouts/admin'));
    }

    /** @return array{page:int, per:int, offset:int} */
    protected function page(array $query, int $per = 25): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        return ['page' => $page, 'per' => $per, 'offset' => ($page - 1) * $per];
    }

    protected function actorId(): int
    {
        return (int) Auth::id();
    }

    /** CSV download with formula-injection protection. */
    protected function csv(string $filename, array $header, iterable $rows): Response
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($fh, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, array_map([self::class, 'cell'], array_values($row)), ',', '"', '');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);
        return Response::download($body, $filename);
    }

    /** Prefixes cells that spreadsheet apps would treat as formulas. */
    public static function cell(mixed $v): string
    {
        $s = (string) ($v ?? '');
        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s) ? "'" . $s : $s;
    }
}
