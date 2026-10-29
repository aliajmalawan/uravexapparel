<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Config;
use App\Core\Request;
use App\Services\Settings;

/**
 * Development-only simulated online payment gateway. It reproduces the real
 * integration shape — hosted payment page, HMAC-signed webhook with a
 * timestamp, server-side status lookup — so the checkout/webhook pipeline can
 * be exercised end-to-end without a merchant account. It can never be enabled
 * when APP_ENV=production and its orders are flagged is_test.
 *
 * "Provider-side" state is kept in storage/sandbox/<reference>.json to mimic a
 * remote system that our database does not control.
 */
final class SandboxProvider extends BaseProvider
{
    public const SIGNATURE_HEADER = 'X-Sandbox-Signature';
    private const TOLERANCE = 300;

    public function code(): string
    {
        return 'sandbox';
    }

    public function defaultLabel(): string
    {
        return 'Test card payment (sandbox)';
    }

    public function defaultDescription(): string
    {
        return 'Development only — simulates an online card payment. No real money moves.';
    }

    public function isConfigured(): bool
    {
        return !Config::isProduction() && strlen((string) Config::get('payments.sandbox_secret')) >= 16;
    }

    public function isEnabled(): bool
    {
        return !Config::isProduction() && (bool) Settings::get('payments.sandbox.enabled', false);
    }

    public function initialOrderStatus(): string
    {
        return 'awaiting_payment';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }

    public function reservationExpires(): bool
    {
        return true;
    }

    public function start(array $order, array $payment): array
    {
        $this->writeState($payment['provider_reference'], [
            'status' => 'pending',
            'amount_minor' => (int) $payment['amount_minor'],
            'currency' => $payment['currency'],
            'order_number' => $order['order_number'],
            'created' => time(),
        ]);
        return ['type' => 'redirect', 'url' => url('/payments/sandbox/' . $payment['provider_reference'])];
    }

    /** Simulates the customer completing/failing payment on the hosted page. */
    public function simulate(string $reference, string $outcome): array
    {
        $state = $this->readState($reference);
        if (!$state) {
            throw new \RuntimeException('Unknown sandbox session');
        }
        $status = match ($outcome) {
            'pay' => 'paid',
            'fail' => 'failed',
            default => 'cancelled',
        };
        if ($state['status'] === 'pending') {
            $state['status'] = $status;
            $this->writeState($reference, $state);
        }
        $body = json_encode([
            'id' => 'evt_' . bin2hex(random_bytes(10)),
            'type' => 'payment.' . $state['status'],
            'data' => [
                'reference' => $reference,
                'status' => $state['status'],
                'amount_minor' => $state['amount_minor'],
                'currency' => $state['currency'],
            ],
        ], JSON_THROW_ON_ERROR);
        return ['body' => $body, 'signature' => self::sign($body, time())];
    }

    public static function sign(string $body, int $timestamp): string
    {
        $secret = (string) Config::get('payments.sandbox_secret');
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function parseWebhook(Request $request): ?array
    {
        $header = (string) $request->header(self::SIGNATURE_HEADER);
        if (!preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $header, $m)) {
            return null;
        }
        $ts = (int) $m[1];
        if (abs(time() - $ts) > self::TOLERANCE) {
            return null; // replay protection
        }
        $secret = (string) Config::get('payments.sandbox_secret');
        if ($secret === '') {
            return null;
        }
        $expected = hash_hmac('sha256', $ts . '.' . $request->body(), $secret);
        if (!hash_equals($expected, $m[2])) {
            return null;
        }
        $data = json_decode($request->body(), true);
        if (!is_array($data) || !isset($data['id'], $data['type'], $data['data']['reference'])) {
            return null;
        }
        return [
            'id' => (string) $data['id'],
            'type' => (string) $data['type'],
            'reference' => (string) $data['data']['reference'],
            'status' => (string) ($data['data']['status'] ?? ''),
            'amount_minor' => (int) ($data['data']['amount_minor'] ?? 0),
            'currency' => (string) ($data['data']['currency'] ?? ''),
            'payload' => $data,
        ];
    }

    public function fetchStatus(array $payment): ?string
    {
        $state = $this->readState((string) $payment['provider_reference']);
        return $state['status'] ?? null;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function refund(array $payment, int $amountMinor, string $reason): string
    {
        $state = $this->readState((string) $payment['provider_reference']);
        if (!$state || !in_array($state['status'], ['paid', 'partially_refunded'], true)) {
            throw new \RuntimeException('Sandbox payment is not refundable');
        }
        $state['refunded_minor'] = (int) ($state['refunded_minor'] ?? 0) + $amountMinor;
        $state['status'] = $state['refunded_minor'] >= $state['amount_minor'] ? 'refunded' : 'partially_refunded';
        $this->writeState((string) $payment['provider_reference'], $state);
        return 'sbx_re_' . bin2hex(random_bytes(6));
    }

    public function readState(string $reference): ?array
    {
        $f = $this->file($reference);
        if (!is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    private function writeState(string $reference, array $state): void
    {
        $f = $this->file($reference);
        if (!is_dir(dirname($f))) {
            mkdir(dirname($f), 0700, true);
        }
        file_put_contents($f, json_encode($state), LOCK_EX);
    }

    private function file(string $reference): string
    {
        if (!preg_match('/^sbx_[a-f0-9]{24}$/', $reference)) {
            throw new \InvalidArgumentException('Bad sandbox reference');
        }
        return STORAGE_PATH . '/sandbox/' . $reference . '.json';
    }
}
