<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Payments;

/**
 * Provider webhooks. CSRF is skipped for this route only; authenticity comes
 * from the provider signature verified inside Payments::handleWebhook().
 */
final class WebhookController extends Controller
{
    public function handle(Request $r): Response
    {
        RateLimiter::hit('webhook:' . $r->params['provider'] . ':' . $r->ip(), 300, 60);
        if (strlen($r->body()) > 256 * 1024) {
            return Response::json(['ok' => false], 413);
        }
        $result = Payments::handleWebhook($r->params['provider'], $r);
        return Response::json(['ok' => $result['status'] === 200, 'outcome' => $result['outcome']], $result['status']);
    }
}
