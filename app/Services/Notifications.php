<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Mail\Mailer;

/**
 * Queues order emails (inside the calling transaction, so a rolled-back
 * change never emails the customer). Delivery happens via Mailer::flush().
 */
final class Notifications
{
    private static function payload(array $order): array
    {
        $items = Database::get()->all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$order['id']]);
        $fresh = Database::get()->one('SELECT * FROM orders WHERE id = ?', [$order['id']]) ?? $order;
        return [
            'order' => $fresh,
            'items' => $items,
            'shipping' => json_decode((string) $fresh['shipping_address'], true) ?: [],
            'orderUrl' => $fresh['user_id'] ? url('/account/orders/' . $fresh['order_number']) : url('/track-order'),
        ];
    }

    public static function orderPlaced(array $order): void
    {
        $data = self::payload($order);
        if ($order['payment_method'] === 'bank_transfer') {
            $data['bank'] = Settings::get('payments.bank_transfer', []);
        }
        $provider = \App\Payments\Registry::get($order['payment_method']);
        if ($provider instanceof \App\Payments\CryptoTransferProvider) {
            // Same quote that is snapshotted on the payment right after the order commits.
            $q = $provider->quote((int) $order['total_minor'], (string) $order['currency']);
            $data['crypto'] = ['amount' => $q['amount'] ?? null, 'asset' => $provider->asset(), 'network' => $provider->network(), 'address' => $provider->address()];
        }
        Mailer::queue($order['email'], 'order_confirmation', $data);
        self::staffAlert($order, 'New order placed');
    }

    public static function paymentConfirmed(array $order): void
    {
        Mailer::queue($order['email'], 'payment_confirmed', self::payload($order));
    }

    public static function paymentRejected(array $order, ?string $note): void
    {
        Mailer::queue($order['email'], 'payment_rejected', self::payload($order) + ['note' => $note]);
    }

    public static function statusChanged(array $order, string $from, string $to, ?string $note): void
    {
        if (!in_array($to, ['confirmed', 'processing', 'delivered', 'cancelled'], true)) {
            return;
        }
        Mailer::queue($order['email'], 'order_status', self::payload($order) + ['status' => $to, 'note' => $note]);
    }

    public static function shipped(array $order, string $courier, string $tracking, ?string $trackingUrl): void
    {
        Mailer::queue($order['email'], 'order_shipped', self::payload($order) + compact('courier', 'tracking', 'trackingUrl'));
    }

    public static function refunded(array $order, int $amountMinor, bool $full): void
    {
        Mailer::queue($order['email'], 'order_refunded', self::payload($order) + ['amount' => $amountMinor, 'full' => $full]);
    }

    /** Plain staff alert not tied to one order (e.g. seller withdrawals). */
    public static function staffAlertText(string $headline, string $body): void
    {
        $to = (string) Config::get('mail.admin_notify');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Mailer::queue($to, 'admin_alert', ['headline' => $headline, 'body' => $body, 'adminUrl' => url('/admin/withdrawals')]);
        }
    }

    public static function staffAlert(array $order, string $headline): void
    {
        $to = (string) Config::get('mail.admin_notify');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Mailer::queue($to, 'admin_order_alert', self::payload($order) + ['headline' => $headline, 'adminUrl' => url('/admin/orders/' . $order['id'])]);
        }
    }
}
