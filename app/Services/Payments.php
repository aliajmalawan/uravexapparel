<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Payments\Registry;

/**
 * Webhook processing and provider reconciliation.
 *
 * Pipeline: verify signature → store event (UNIQUE provider+event id, so
 * replays are no-ops) → lock payment + order → confirm status with the
 * provider API → check amount/currency → apply state change.
 */
final class Payments
{
    /** @return array{status:int, outcome:string} */
    public static function handleWebhook(string $providerCode, Request $request): array
    {
        $provider = Registry::get($providerCode);
        if (!$provider || !$provider->isImplemented()) {
            throw new HttpException(404);
        }
        $event = $provider->parseWebhook($request);
        if ($event === null) {
            Logger::warning('Webhook rejected: invalid signature', ['provider' => $providerCode, 'ip' => $request->ip()]);
            return ['status' => 400, 'outcome' => 'invalid_signature'];
        }

        $db = Database::get();
        $inserted = $db->exec(
            'INSERT IGNORE INTO payment_events (provider, provider_event_id, type, signature_valid, payload) VALUES (?, ?, ?, 1, ?)',
            [$providerCode, $event['id'], $event['type'], json_encode(\App\Core\Logger::redact($event['payload']), JSON_UNESCAPED_SLASHES)]
        );
        if ($inserted === 0) {
            return ['status' => 200, 'outcome' => 'duplicate'];
        }

        try {
            $outcome = self::process($db, $providerCode, $provider, $event);
        } catch (\Throwable $e) {
            // Remove the event so the provider's retry is processed, not dropped as a duplicate.
            $db->exec('DELETE FROM payment_events WHERE provider = ? AND provider_event_id = ? AND processed_at IS NULL', [$providerCode, $event['id']]);
            throw $e;
        }

        $db->exec(
            'UPDATE payment_events SET outcome = ?, processed_at = UTC_TIMESTAMP() WHERE provider = ? AND provider_event_id = ?',
            [$outcome, $providerCode, $event['id']]
        );
        \App\Mail\Mailer::flush();
        // Unknown/mismatch events are acknowledged (no provider retry storm) but flagged.
        return ['status' => 200, 'outcome' => $outcome];
    }

    private static function process(Database $db, string $providerCode, \App\Payments\PaymentProvider $provider, array $event): string
    {
        return $db->transaction(static function (Database $db) use ($providerCode, $provider, $event): string {
            $payment = $db->one('SELECT * FROM payments WHERE provider = ? AND provider_reference = ? FOR UPDATE', [$providerCode, $event['reference']]);
            if (!$payment) {
                return 'unknown_payment';
            }
            $db->exec('UPDATE payment_events SET payment_id = ? WHERE provider = ? AND provider_event_id = ?', [$payment['id'], $providerCode, $event['id']]);
            $order = $db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$payment['order_id']]);

            if ((int) $event['amount_minor'] !== (int) $payment['amount_minor'] || strtoupper($event['currency']) !== $payment['currency']) {
                Logger::warning('Webhook amount mismatch', ['order' => $order['order_number']]);
                return 'amount_mismatch';
            }
            // Never trust the webhook body alone: confirm with the provider API.
            $remote = $provider->fetchStatus($payment);
            if ($remote === null) {
                return 'status_unavailable';
            }
            if ($remote !== $event['status']) {
                return 'status_mismatch';
            }
            return self::apply($db, $order, $payment, $remote, 'webhook');
        });
    }

    /** Applies an authoritative provider status. Caller holds locks. */
    private static function apply(Database $db, array $order, array $payment, string $status, string $source): string
    {
        if ($payment['status'] === 'paid' && $status === 'paid') {
            return 'already_paid';
        }
        switch ($status) {
            case 'paid':
                Orders::applyPaid($db, $order, $payment, null, 'Confirmed by provider (' . $source . ')');
                return 'paid';
            case 'failed':
                if ($payment['status'] === 'pending') {
                    $db->exec("UPDATE payments SET status = 'failed', failure_reason = 'Declined by provider' WHERE id = ?", [$payment['id']]);
                    $db->exec("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status = 'unpaid'", [$order['id']]);
                    Orders::history((int) $order['id'], $order['status'], $order['status'], 'Payment failed', null);
                }
                return 'failed';
            case 'cancelled':
            case 'expired':
                if ($payment['status'] === 'pending') {
                    $db->exec('UPDATE payments SET status = ? WHERE id = ?', [$status, $payment['id']]);
                    Orders::history((int) $order['id'], $order['status'], $order['status'], 'Payment ' . $status . ' by customer/provider', null);
                }
                return $status;
            default:
                return 'ignored';
        }
    }

    /** Cron/return-page reconciliation against the provider API. */
    public static function confirmFromProvider(array $payment, string $source): string
    {
        $provider = Registry::get($payment['provider']);
        if (!$provider) {
            return 'unknown_provider';
        }
        $remote = $provider->fetchStatus($payment);
        if ($remote === null || $remote === 'pending') {
            return 'pending';
        }
        return Database::get()->transaction(static function (Database $db) use ($payment, $remote, $source): string {
            $p = $db->one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$payment['id']]);
            $order = $db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$p['order_id']]);
            return self::apply($db, $order, $p, $remote, $source);
        });
    }

    public static function newReference(string $provider): string
    {
        return $provider === 'sandbox' ? 'sbx_' . bin2hex(random_bytes(12)) : $provider . '_' . bin2hex(random_bytes(12));
    }
}
