<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Payments\Registry;
use App\Payments\SandboxProvider;
use App\Services\Payments;

/**
 * Simulated hosted payment page for the development sandbox provider. It sends
 * a signed webhook through the same verification pipeline a real provider uses.
 */
final class SandboxController extends Controller
{
    private function provider(): SandboxProvider
    {
        $p = Registry::get('sandbox');
        if (Config::isProduction() || !$p instanceof SandboxProvider || !$p->isConfigured()) {
            throw new HttpException(404);
        }
        $ref = (string) (\App\Core\View::shared('request')?->params['ref'] ?? '');
        if (!preg_match('/^sbx_[a-f0-9]{24}$/', $ref)) {
            throw new HttpException(404);
        }
        return $p;
    }

    public function show(Request $r): Response
    {
        $state = $this->provider()->readState($r->params['ref']);
        if (!$state) {
            throw new HttpException(404);
        }
        return $this->view('store/sandbox', ['ref' => $r->params['ref'], 'state' => $state], ['title' => 'Sandbox payment', 'noindex' => true]);
    }

    public function complete(Request $r): Response
    {
        $provider = $this->provider();
        $outcome = in_array($r->str('outcome'), ['pay', 'fail', 'cancel'], true) ? $r->str('outcome') : 'cancel';
        $signed = $provider->simulate($r->params['ref'], $outcome);

        // Deliver the webhook through the real handler (signature verified, idempotent).
        $webhook = new Request('POST', '/webhooks/sandbox', [], [], [], [
            'HTTP_X_SANDBOX_SIGNATURE' => $signed['signature'],
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ], [], $signed['body']);
        Payments::handleWebhook('sandbox', $webhook);

        $number = Database::get()->value(
            "SELECT o.order_number FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.provider = 'sandbox' AND p.provider_reference = ?",
            [$r->params['ref']]
        );
        return Response::redirect('/checkout/complete/' . $number);
    }
}
