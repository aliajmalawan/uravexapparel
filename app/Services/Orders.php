<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Payments\ManualPayment;
use App\Payments\Registry;

/**
 * Order lifecycle operations. Every mutation locks the order row
 * (SELECT … FOR UPDATE) and re-checks its current state inside the
 * transaction, which prevents double-processing from double clicks, retries
 * or two staff members acting at once.
 */
final class Orders
{
    public static function find(int $id): ?array
    {
        return Database::get()->one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function findByNumber(string $number): ?array
    {
        return Database::get()->one('SELECT * FROM orders WHERE order_number = ?', [$number]);
    }

    /** Full order with items, payments, shipments, refunds and history. */
    public static function detail(int $id): ?array
    {
        $db = Database::get();
        $o = self::find($id);
        if (!$o) {
            return null;
        }
        $o['items'] = $db->all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
        $o['payments'] = $db->all('SELECT p.*, u.name AS verified_by_name FROM payments p LEFT JOIN users u ON u.id = p.verified_by WHERE p.order_id = ? ORDER BY p.id', [$id]);
        $o['shipments'] = $db->all('SELECT * FROM shipments WHERE order_id = ? ORDER BY id', [$id]);
        $o['seller_shipments'] = $db->all('SELECT ss.*, s.shop_name FROM seller_shipments ss JOIN sellers s ON s.id = ss.seller_id WHERE ss.order_id = ? ORDER BY ss.id', [$id]);
        $o['refunds'] = $db->all('SELECT r.*, u.name AS actor_name FROM refunds r LEFT JOIN users u ON u.id = r.actor_id WHERE r.order_id = ? ORDER BY r.id', [$id]);
        $o['history'] = $db->all('SELECT h.*, u.name AS actor_name FROM order_status_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.order_id = ? ORDER BY h.id', [$id]);
        $o['shipping'] = json_decode((string) $o['shipping_address'], true) ?: [];
        $o['billing'] = json_decode((string) $o['billing_address'], true) ?: [];
        return $o;
    }

    private static function lock(Database $db, int $id): array
    {
        $o = $db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
        if (!$o) {
            throw ValidationException::one('order', 'Order not found.');
        }
        return $o;
    }

    public static function history(int $orderId, ?string $from, string $to, ?string $note, ?int $actorId, bool $visible = true): void
    {
        Database::get()->insert(
            'INSERT INTO order_status_history (order_id, from_status, to_status, note, visible, actor_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$orderId, $from, $to, $note, $visible, $actorId]
        );
    }

    /**
     * Manual status change by staff (or system). Refunds and shipping have
     * their own methods because they need extra data.
     */
    public static function transition(int $orderId, string $to, ?string $note, ?int $actorId, bool $system = false): array
    {
        $db = Database::get();
        $result = $db->transaction(static function (Database $db) use ($orderId, $to, $note, $actorId, $system): array {
            $o = self::lock($db, $orderId);
            $from = $o['status'];
            if ($from === $to) {
                throw ValidationException::one('status', 'The order is already ' . OrderState::label($to) . '.');
            }
            if (!OrderState::canTransition($from, $to)) {
                throw ValidationException::one('status', 'Cannot move an order from ' . OrderState::label($from) . ' to ' . OrderState::label($to) . '.');
            }
            if ($to === 'refunded') {
                throw ValidationException::one('status', 'Use “Record refund” to refund an order.');
            }
            if ($to === 'shipped') {
                throw ValidationException::one('status', 'Add courier and tracking details to mark the order shipped.');
            }
            if (!$system && $to === 'confirmed' && $from === 'awaiting_payment') {
                throw ValidationException::one('status', 'Verify the payment first; the order confirms automatically.');
            }
            if ($to === 'cancelled' && in_array($o['payment_status'], ['paid', 'partially_refunded'], true)) {
                throw ValidationException::one('status', 'This order has been paid. Record a refund instead of cancelling.');
            }

            $extra = '';
            $params = [$to];
            if ($to === 'cancelled') {
                self::releaseStock($db, $o, $actorId);
                Coupons::release($orderId);
                $db->exec("UPDATE payments SET status = 'cancelled' WHERE order_id = ? AND status IN ('pending','awaiting_verification')", [$orderId]);
                $extra = ", cancelled_at = UTC_TIMESTAMP(), reservation_expires_at = NULL, payment_status = IF(payment_status IN ('unpaid','pending_verification','failed'), 'voided', payment_status)";
            } elseif ($to === 'delivered') {
                $extra = ', delivered_at = UTC_TIMESTAMP()';
                $db->exec('UPDATE shipments SET delivered_at = UTC_TIMESTAMP() WHERE order_id = ? AND delivered_at IS NULL', [$orderId]);
                Marketplace::orderDelivered($db, $orderId);
            } elseif ($to === 'confirmed') {
                $extra = ', reservation_expires_at = NULL';
            }
            $params[] = $orderId;
            $params[] = $from;
            $n = $db->exec("UPDATE orders SET status = ?{$extra} WHERE id = ? AND status = ?", $params);
            if ($n !== 1) {
                throw ValidationException::one('status', 'The order changed while you were working. Refresh and try again.');
            }
            self::history($orderId, $from, $to, $note, $actorId);
            $o['status'] = $to;
            $o['previous_status'] = $from;
            Notifications::statusChanged($o, $from, $to, $note);
            return $o;
        });
        Audit::log('order.status', 'order', $orderId, ['status' => $result['previous_status']], ['status' => $to, 'note' => $note]);
        return $result;
    }

    /** Returns all reserved stock to inventory once (guarded by stock_released). */
    private static function releaseStock(Database $db, array $o, ?int $actorId): void
    {
        if ($o['stock_released']) {
            return;
        }
        $items = $db->all('SELECT * FROM order_items WHERE order_id = ? AND variant_id IS NOT NULL', [$o['id']]);
        foreach ($items as $it) {
            $qty = (int) $it['quantity'] - (int) $it['refunded_quantity'];
            if ($qty > 0) {
                Inventory::apply((int) $it['variant_id'], $qty, 'cancellation', 'Order ' . $o['order_number'] . ' cancelled', (int) $o['id'], $actorId);
            }
        }
        $db->exec('UPDATE orders SET stock_released = 1 WHERE id = ?', [$o['id']]);
    }

    public static function ship(int $orderId, string $courier, string $tracking, ?string $trackingUrl, ?string $note, ?int $actorId): void
    {
        Database::get()->transaction(static function (Database $db) use ($orderId, $courier, $tracking, $trackingUrl, $note, $actorId): void {
            $o = self::lock($db, $orderId);
            if (!OrderState::canTransition($o['status'], 'shipped')) {
                throw ValidationException::one('status', 'Only packed orders can be shipped (current: ' . OrderState::label($o['status']) . ').');
            }
            $trackingUrl = $trackingUrl ?: Shipping::trackingUrl($courier, $tracking);
            $db->insert(
                'INSERT INTO shipments (order_id, courier, tracking_number, tracking_url, note, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$orderId, $courier, $tracking, $trackingUrl, $note, $actorId]
            );
            $db->exec("UPDATE orders SET status = 'shipped', shipped_at = UTC_TIMESTAMP() WHERE id = ? AND status = ?", [$orderId, $o['status']]);
            self::history($orderId, $o['status'], 'shipped', "Shipped with {$courier} — tracking {$tracking}", $actorId);
            $o['status'] = 'shipped';
            Notifications::shipped($o, $courier, $tracking, $trackingUrl);
        });
        Audit::log('order.ship', 'order', $orderId, null, ['courier' => $courier, 'tracking' => $tracking]);
    }

    /**
     * Marks a payment paid and confirms the order if it was waiting. Must be
     * called inside a transaction holding the order lock.
     */
    public static function applyPaid(Database $db, array $order, array $payment, ?int $actorId, ?string $note): void
    {
        $db->exec(
            "UPDATE payments SET status = 'paid', verified_by = ?, verified_at = UTC_TIMESTAMP(), verification_note = ? WHERE id = ? AND status IN ('pending','awaiting_verification','failed')",
            [$actorId, $note, $payment['id']]
        );
        $db->exec("UPDATE orders SET payment_status = 'paid', paid_at = COALESCE(paid_at, UTC_TIMESTAMP()) WHERE id = ?", [$order['id']]);
        if (in_array($order['status'], ['pending', 'awaiting_payment'], true)) {
            $db->exec("UPDATE orders SET status = 'confirmed', reservation_expires_at = NULL WHERE id = ?", [$order['id']]);
            self::history((int) $order['id'], $order['status'], 'confirmed', 'Payment received', $actorId);
            $order['status'] = 'confirmed';
            Notifications::paymentConfirmed($order);
        } elseif ($order['status'] === 'cancelled') {
            // Payment arrived after the reservation expired. Money must be returned.
            self::history((int) $order['id'], 'cancelled', 'cancelled', 'Payment received after cancellation — refund required', $actorId, false);
            Logger::warning('Payment received for cancelled order', ['order' => $order['order_number']]);
        }
        // Credit marketplace sellers their share (pending until delivered + hold period).
        Marketplace::creditOrder($db, $order);
    }

    public static function recordCodCollected(int $orderId, ?string $note, int $actorId): void
    {
        Database::get()->transaction(static function (Database $db) use ($orderId, $note, $actorId): void {
            $o = self::lock($db, $orderId);
            if ($o['payment_method'] !== 'cod') {
                throw ValidationException::one('payment', 'This order is not cash on delivery.');
            }
            if ($o['payment_status'] === 'paid') {
                throw ValidationException::one('payment', 'Cash collection has already been recorded.');
            }
            if (in_array($o['status'], ['cancelled', 'refunded'], true)) {
                throw ValidationException::one('payment', 'Cannot record payment on a ' . OrderState::label($o['status']) . ' order.');
            }
            $p = $db->one("SELECT * FROM payments WHERE order_id = ? AND provider = 'cod' ORDER BY id DESC LIMIT 1 FOR UPDATE", [$orderId]);
            if (!$p) {
                throw ValidationException::one('payment', 'No COD payment record found.');
            }
            self::applyPaid($db, $o, $p, $actorId, $note ?: 'Cash collected');
            self::history($orderId, $o['status'], $o['status'], 'Cash on delivery collected', $actorId, false);
        });
        Audit::log('payment.cod_collected', 'order', $orderId, null, ['note' => $note]);
    }

    /** The order's payment method when it is verified by hand (crypto or bank transfer), else null. */
    public static function manualProvider(array $order): ?ManualPayment
    {
        $p = Registry::get((string) $order['payment_method']);
        return $p instanceof ManualPayment ? $p : null;
    }

    /** Staff approve or reject a customer-submitted transfer after checking it (explorer / bank statement). */
    public static function verifyBankTransfer(int $orderId, bool $approve, ?string $note, int $actorId): void
    {
        Database::get()->transaction(static function (Database $db) use ($orderId, $approve, $note, $actorId): void {
            $o = self::lock($db, $orderId);
            if (!self::manualProvider($o)) {
                throw ValidationException::one('payment', 'This order is not paid by manual transfer.');
            }
            $p = $db->one('SELECT * FROM payments WHERE order_id = ? AND provider = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$orderId, $o['payment_method']]);
            if (!$p || !in_array($p['status'], ['pending', 'awaiting_verification'], true)) {
                throw ValidationException::one('payment', 'There is no transfer waiting for verification.');
            }
            if ($approve) {
                if ($o['status'] === 'cancelled') {
                    throw ValidationException::one('payment', 'This order was cancelled. Refund the customer instead of approving.');
                }
                self::applyPaid($db, $o, $p, $actorId, $note ?: 'Transfer verified');
            } else {
                $db->exec("UPDATE payments SET status = 'failed', verified_by = ?, verified_at = UTC_TIMESTAMP(), verification_note = ?, failure_reason = 'Transfer could not be verified' WHERE id = ?", [$actorId, $note, $p['id']]);
                $db->exec("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$orderId]);
                self::history($orderId, $o['status'], $o['status'], 'Transfer could not be verified' . ($note ? ": {$note}" : ''), $actorId);
                Notifications::paymentRejected($o, $note);
            }
        });
        Audit::log($approve ? 'payment.transfer_verified' : 'payment.transfer_rejected', 'order', $orderId, null, ['note' => $note]);
    }

    /** Customer submits their transaction hash / transfer reference (and optional receipt). */
    public static function submitBankReference(int $orderId, string $reference, ?string $receiptPath): void
    {
        Database::get()->transaction(static function (Database $db) use ($orderId, $reference, $receiptPath): void {
            $o = self::lock($db, $orderId);
            $provider = self::manualProvider($o);
            if (!$provider || $o['status'] !== 'awaiting_payment') {
                throw ValidationException::one('reference', 'This order is not waiting for a transfer.');
            }
            if (!$provider->validReference($reference)) {
                throw ValidationException::one('reference', 'Enter a valid ' . $provider->referenceLabel() . '.');
            }
            $used = $db->value(
                "SELECT COUNT(*) FROM payments WHERE provider = ? AND customer_reference = ? AND order_id <> ? AND status IN ('awaiting_verification','paid','partially_refunded','refunded')",
                [$o['payment_method'], $reference, $orderId]
            );
            if ((int) $used > 0) {
                throw ValidationException::one('reference', 'This transaction has already been submitted for another order.');
            }
            $p = $db->one('SELECT * FROM payments WHERE order_id = ? AND provider = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$orderId, $o['payment_method']]);
            if (!$p || !in_array($p['status'], ['pending', 'failed', 'awaiting_verification'], true)) {
                throw ValidationException::one('reference', 'Payment details can no longer be changed.');
            }
            $db->exec(
                "UPDATE payments SET status = 'awaiting_verification', customer_reference = ?, receipt_path = COALESCE(?, receipt_path), failure_reason = NULL WHERE id = ?",
                [$reference, $receiptPath, $p['id']]
            );
            $db->exec("UPDATE orders SET payment_status = 'pending_verification' WHERE id = ?", [$orderId]);
            self::history($orderId, $o['status'], $o['status'], 'Transfer details submitted — awaiting verification', null);
            Notifications::staffAlert($o, 'Payment submitted for verification (' . $provider->referenceLabel() . ')');
        });
    }

    /**
     * Records a (partial) refund. Items are optional; restocked quantities go
     * back to inventory as "return". A full refund moves the order to Refunded.
     *
     * @param list<array{order_item_id:int, quantity:int, restock:bool}> $items
     */
    public static function refund(int $orderId, int $amountMinor, string $reason, array $items, string $method, ?string $reference, string $idempotencyKey, int $actorId): array
    {
        if ($amountMinor <= 0) {
            throw ValidationException::one('amount', 'Refund amount must be greater than zero.');
        }
        $db = Database::get();
        $existing = $db->one('SELECT * FROM refunds WHERE idempotency_key = ?', [$idempotencyKey]);
        if ($existing) {
            return $existing; // duplicate submit
        }
        $refund = $db->transaction(static function (Database $db) use ($orderId, $amountMinor, $reason, $items, $method, $reference, $idempotencyKey, $actorId): array {
            $o = self::lock($db, $orderId);
            if (!in_array($o['payment_status'], ['paid', 'partially_refunded'], true)) {
                throw ValidationException::one('amount', 'Only collected payments can be refunded.');
            }
            $refundable = (int) $o['total_minor'] - (int) $o['refunded_minor'];
            if ($amountMinor > $refundable) {
                throw ValidationException::one('amount', 'Refund exceeds the refundable balance of ' . money($refundable) . '.');
            }
            $payment = $db->one("SELECT * FROM payments WHERE order_id = ? AND status IN ('paid','partially_refunded') ORDER BY id DESC LIMIT 1 FOR UPDATE", [$orderId]);

            $providerRef = $reference;
            if ($method === 'provider') {
                $provider = $payment ? Registry::get($payment['provider']) : null;
                if (!$provider || !$provider->supportsRefunds()) {
                    throw ValidationException::one('method', 'This payment method cannot be refunded through the provider. Record a manual refund.');
                }
                $providerRef = $provider->refund($payment, $amountMinor, $reason);
            }

            $cleanItems = [];
            foreach ($items as $it) {
                $qty = (int) $it['quantity'];
                if ($qty <= 0) {
                    continue;
                }
                $row = $db->one('SELECT * FROM order_items WHERE id = ? AND order_id = ? FOR UPDATE', [(int) $it['order_item_id'], $orderId]);
                if (!$row) {
                    throw ValidationException::one('items', 'Invalid order item.');
                }
                if ($qty > (int) $row['quantity'] - (int) $row['refunded_quantity']) {
                    throw ValidationException::one('items', "Refund quantity for {$row['sku']} exceeds what was purchased.");
                }
                $db->exec('UPDATE order_items SET refunded_quantity = refunded_quantity + ? WHERE id = ?', [$qty, $row['id']]);
                if (!empty($it['restock']) && $row['variant_id']) {
                    Inventory::apply((int) $row['variant_id'], $qty, 'return', 'Refund on ' . $o['order_number'], $orderId, $actorId);
                }
                $cleanItems[] = ['order_item_id' => (int) $row['id'], 'quantity' => $qty, 'restock' => !empty($it['restock'])];
            }

            $id = $db->insert(
                'INSERT INTO refunds (order_id, payment_id, amount_minor, currency, reason, method, reference, items, actor_id, idempotency_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $payment['id'] ?? null, $amountMinor, $o['currency'], $reason, $method, $providerRef, json_encode($cleanItems), $actorId, $idempotencyKey]
            );
            $newRefunded = (int) $o['refunded_minor'] + $amountMinor;
            $full = $newRefunded >= (int) $o['total_minor'];
            Marketplace::reverseForRefund($db, $o, $amountMinor, $cleanItems, $full, $actorId);
            $db->exec(
                'UPDATE orders SET refunded_minor = ?, payment_status = ? WHERE id = ?',
                [$newRefunded, $full ? 'refunded' : 'partially_refunded', $orderId]
            );
            if ($payment) {
                $db->exec('UPDATE payments SET status = ? WHERE id = ?', [$full ? 'refunded' : 'partially_refunded', $payment['id']]);
            }
            if ($full && $o['status'] !== 'refunded' && OrderState::canTransition($o['status'], 'refunded')) {
                $db->exec("UPDATE orders SET status = 'refunded' WHERE id = ?", [$orderId]);
                self::history($orderId, $o['status'], 'refunded', 'Order refunded in full', $actorId);
                $o['status'] = 'refunded';
            } elseif ($full && $o['status'] === 'cancelled') {
                self::history($orderId, 'cancelled', 'cancelled', 'Late payment refunded in full', $actorId);
            } else {
                self::history($orderId, $o['status'], $o['status'], 'Partial refund of ' . money($amountMinor), $actorId);
            }
            Notifications::refunded($o, $amountMinor, $full);
            return $db->one('SELECT * FROM refunds WHERE id = ?', [$id]);
        });
        Audit::log('order.refund', 'order', $orderId, null, ['amount_minor' => $amountMinor, 'method' => $method, 'reason' => $reason, 'items' => $items]);
        return $refund;
    }

    /**
     * Cancels unpaid online orders whose stock reservation expired and stale
     * unverified bank transfers. Run by cron. Returns number cancelled.
     */
    public static function expireStale(): int
    {
        $db = Database::get();
        $ids = $db->column(
            "SELECT id FROM orders WHERE status = 'awaiting_payment' AND payment_status <> 'pending_verification' AND reservation_expires_at IS NOT NULL AND reservation_expires_at < UTC_TIMESTAMP() LIMIT 200"
        );
        $days = (int) Settings::get('payments.bank_transfer.auto_cancel_days', 3);
        if ($days > 0) {
            $ids = array_merge($ids, $db->column(
                "SELECT id FROM orders WHERE status = 'awaiting_payment' AND payment_method = 'bank_transfer' AND payment_status IN ('unpaid','failed') AND placed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY) LIMIT 200",
                [$days]
            ));
        }
        $n = 0;
        foreach (array_unique($ids) as $id) {
            try {
                // Re-check provider status before cancelling an online payment.
                $payment = $db->one("SELECT * FROM payments WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$id]);
                if ($payment && ($provider = Registry::get($payment['provider'])) && $provider->reservationExpires()) {
                    $remote = $provider->fetchStatus($payment);
                    if ($remote === 'paid') {
                        Payments::confirmFromProvider($payment, 'cron-reconcile');
                        continue;
                    }
                }
                self::transition((int) $id, 'cancelled', 'Payment not received in time — order cancelled automatically', null, true);
                $db->exec("UPDATE payments SET status = 'expired' WHERE order_id = ? AND status = 'cancelled'", [$id]);
                $n++;
            } catch (\Throwable $e) {
                Logger::warning('Expire order failed', ['order_id' => $id, 'error' => $e->getMessage()]);
            }
        }
        return $n;
    }
}
