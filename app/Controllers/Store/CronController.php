<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Mail\Mailer;
use App\Services\Cart;
use App\Services\Orders;

/**
 * HTTP cron trigger for hosts without CLI cron:
 *   curl -X POST -H "Authorization: Bearer $CRON_SECRET" https://shop/cron/run
 */
final class CronController extends Controller
{
    public function run(Request $r): Response
    {
        $secret = (string) Config::get('cron_secret');
        $given = (string) preg_replace('/^Bearer\s+/i', '', (string) $r->header('Authorization'));
        if (strlen($secret) < 24 || !hash_equals($secret, $given)) {
            throw new HttpException(404);
        }
        \App\Services\ExchangeRates::refreshIfStale();
        if (!\App\Services\CryptoPrices::isFresh()) {
            \App\Services\CryptoPrices::refresh();
        }
        return Response::json([
            'expired_orders' => Orders::expireStale(),
            'emails_sent' => Mailer::flush(50),
            'stale_carts' => Cart::purgeStale(),
        ]);
    }
}
