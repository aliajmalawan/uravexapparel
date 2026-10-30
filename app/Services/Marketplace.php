<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Mail\Mailer;
use App\Payments\CryptoTransferProvider;

/**
 * Multi-seller marketplace: seller accounts, earnings and withdrawals.
 *
 * Money flow (one direction only): a customer pays for an order → each seller
 * line is credited (order line total minus commission) as a PENDING ledger
 * entry → when the order is delivered the entry becomes available after the
 * hold period → the seller requests a withdrawal → staff pay it from the store
 * wallet and record the TXID. Refunds reverse the seller's share. Sellers
 * never deposit money and never pay to fulfil orders.
 *
 * The ledger is append-only; every balance is a SUM over it.
 */
final class Marketplace
{
    public const WITHDRAW_NETWORK = 'TRC20';

    public static function setting(string $key): mixed
    {
        return Settings::get('marketplace.' . $key);
    }

    // ───────────────────────────── Sellers

    public static function sellerForUser(?int $userId): ?array
    {
        return $userId ? Database::get()->one('SELECT * FROM sellers WHERE user_id = ?', [$userId]) : null;
    }

    public static function seller(int $id): ?array
    {
        return Database::get()->one('SELECT * FROM sellers WHERE id = ?', [$id]);
    }

    public static function bySlug(string $slug): ?array
    {
        return Database::get()->one("SELECT * FROM sellers WHERE slug = ? AND status = 'approved'", [$slug]);
    }

    /** Validates and stores a seller application (or re-application after rejection). */
    public static function apply(int $userId, array $d): int
    {
        if (!self::setting('applications_open')) {
            throw ValidationException::one('shop_name', 'Seller applications are closed at the moment.');
        }
        $db = Database::get();
        $existing = self::sellerForUser($userId);
        if ($existing && $existing['status'] !== 'rejected') {
            throw ValidationException::one('shop_name', 'You have already applied.');
        }
        self::checkPayoutAddress($d['payout_address']);
        $slug = slugify($d['shop_name']);
        if ($slug === '' || $db->value('SELECT id FROM sellers WHERE (slug = ? OR shop_name = ?) AND id <> ?', [$slug, $d['shop_name'], $existing['id'] ?? 0])) {
            throw ValidationException::one('shop_name', 'That shop name is taken. Please choose another.');
        }
        if ($existing) {
            $db->exec(
                "UPDATE sellers SET shop_name = ?, slug = ?, description = ?, contact_phone = ?, payout_address = ?, status = 'pending', status_note = NULL, applied_at = UTC_TIMESTAMP(), reviewed_at = NULL, reviewed_by = NULL WHERE id = ?",
                [$d['shop_name'], $slug, $d['description'], $d['contact_phone'], $d['payout_address'], $existing['id']]
            );
            $id = (int) $existing['id'];
        } else {
            $id = $db->insert(
                'INSERT INTO sellers (user_id, shop_name, slug, description, contact_phone, payout_address) VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $d['shop_name'], $slug, $d['description'], $d['contact_phone'], $d['payout_address']]
            );
        }
        Audit::log('seller.apply', 'seller', $id, null, ['shop_name' => $d['shop_name']]);
        return $id;
    }

    public static function checkPayoutAddress(?string $address): void
    {
        if ($address !== null && $address !== '' && !CryptoTransferProvider::validAddress('usdt_trc20', $address)) {
            throw ValidationException::one('payout_address', 'Enter a valid USDT TRC20 (TRON) address — it starts with T and is 34 characters.');
        }
    }

    /**
     * Staff decision on a seller: approve, reject, suspend or reinstate.
     * Suspended sellers' products disappear from the store immediately
     * (Catalog only shows products of approved sellers).
     */
    public static function decide(int $sellerId, string $action, ?string $note, int $actorId): array
    {
        $map = ['approve' => ['pending', 'approved'], 'reject' => ['pending', 'rejected'], 'suspend' => ['approved', 'suspended'], 'reinstate' => ['suspended', 'approved']];
        if (!isset($map[$action])) {
            throw ValidationException::one('action', 'Unknown action.');
        }
        [$from, $to] = $map[$action];
        $db = Database::get();
        $s = $db->transaction(static function (Database $db) use ($sellerId, $from, $to, $note, $actorId): array {
            $s = $db->one('SELECT * FROM sellers WHERE id = ? FOR UPDATE', [$sellerId]);
            if (!$s) {
                throw ValidationException::one('action', 'Seller not found.');
            }
            if ($s['status'] !== $from) {
                throw ValidationException::one('action', 'This seller is ' . $s['status'] . '; that action is not available.');
            }
            if (in_array($to, ['rejected', 'suspended'], true) && trim((string) $note) === '') {
                throw ValidationException::one('note', 'Give a reason — the seller will see it.');
            }
            $db->exec('UPDATE sellers SET status = ?, status_note = ?, reviewed_at = UTC_TIMESTAMP(), reviewed_by = ? WHERE id = ?', [$to, $note ?: null, $actorId, $sellerId]);
            $s['status'] = $to;
            $s['status_note'] = $note;
            return $s;
        });
        Audit::log('seller.' . $action, 'seller', $sellerId, ['status' => $from], ['status' => $to, 'note' => $note]);
        $email = (string) $db->value('SELECT email FROM users WHERE id = ?', [$s['user_id']]);
        Mailer::queue($email, 'seller_status', ['seller' => $s, 'email' => $email, 'panelUrl' => url('/seller'), 'loginUrl' => url('/seller/login'), 'shopUrl' => url('/sellers/' . $s['slug'])]);
        return $s;
    }

    /** Commission % for a seller as a decimal string (seller override or store default). */
    public static function commissionPercent(array $seller): string
    {
        $pct = $seller['commission_percent'] ?? null;
        if ($pct === null || $pct === '') {
            $pct = (string) self::setting('commission_percent');
        }
        return bcadd((string) $pct, '0', 2);
    }

    /** Commission on an amount, rounded half-up to the minor unit. */
    public static function commissionOn(int $amountMinor, string $percent): int
    {
        return (int) bcadd(bcdiv(bcmul((string) $amountMinor, $percent, 4), '100', 4), '0.5', 0);
    }

    // ───────────────────────────── Order hooks

    /**
     * Snapshots seller, commission and seller share on each order line.
     * Called inside the checkout transaction right after the lines are written.
     */
    public static function snapshotOrder(Database $db, int $orderId): void
    {
        $items = $db->all(
            'SELECT oi.id, oi.line_total_minor, oi.product_title, p.seller_id FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? AND p.seller_id IS NOT NULL',
            [$orderId]
        );
        $sellers = [];
        foreach ($items as $it) {
            $sid = (int) $it['seller_id'];
            $sellers[$sid] ??= $db->one('SELECT * FROM sellers WHERE id = ?', [$sid]);
            if (($sellers[$sid]['status'] ?? '') !== 'approved') {
                throw ValidationException::one('checkout', "{$it['product_title']} is no longer available. Please remove it from your bag.");
            }
            $commission = min((int) $it['line_total_minor'], self::commissionOn((int) $it['line_total_minor'], self::commissionPercent($sellers[$sid])));
            $db->exec(
                'UPDATE order_items SET seller_id = ?, commission_minor = ?, seller_amount_minor = ? WHERE id = ?',
                [$sid, $commission, (int) $it['line_total_minor'] - $commission, $it['id']]
            );
        }
    }

    /**
     * Credits each seller's share once the customer's payment is confirmed.
     * Idempotent (unique sale_item_id). Caller holds the order lock.
     */
    public static function creditOrder(Database $db, array $order): void
    {
        if (in_array($order['status'], ['cancelled', 'refunded'], true)) {
            return; // late payment on a cancelled order is refunded, not credited
        }
        $items = $db->all('SELECT * FROM order_items WHERE order_id = ? AND seller_id IS NOT NULL AND seller_amount_minor > 0', [$order['id']]);
        $availableAt = !empty($order['delivered_at']) ? self::releaseDate((string) $order['delivered_at']) : null;
        $credited = [];
        foreach ($items as $it) {
            $n = $db->exec(
                "INSERT IGNORE INTO seller_ledger (seller_id, type, amount_minor, currency, order_id, order_item_id, sale_item_id, available_at, note)
                 VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?)",
                [$it['seller_id'], $it['seller_amount_minor'], $order['currency'], $order['id'], $it['id'], $it['id'], $availableAt,
                 'Order ' . $order['order_number'] . ' — ' . $it['quantity'] . ' × ' . mb_substr($it['product_title'], 0, 120)]
            );
            if ($n === 1) {
                $credited[(int) $it['seller_id']][] = $it;
            }
        }
        foreach ($credited as $sellerId => $lines) {
            $s = $db->one('SELECT s.*, u.email FROM sellers s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$sellerId]);
            if ($s) {
                Mailer::queue($s['email'], 'seller_new_order', ['seller' => $s, 'order' => $order, 'lines' => $lines, 'orderUrl' => url('/seller/orders/' . $order['order_number'])]);
            }
        }
    }

    /** Order delivered: pending earnings start their hold period. */
    public static function orderDelivered(Database $db, int $orderId): void
    {
        $db->exec('UPDATE seller_ledger SET available_at = ? WHERE order_id = ? AND available_at IS NULL', [self::releaseDate(gmdate('Y-m-d H:i:s')), $orderId]);
    }

    private static function releaseDate(string $deliveredUtc): string
    {
        $days = max(0, (int) self::setting('hold_days'));
        return gmdate('Y-m-d H:i:s', strtotime($deliveredUtc . ' UTC') + $days * 86400);
    }

    /**
     * Reverses sellers' shares for a refund. Full refund: everything left on
     * the order. Partial: refunded item quantities, or — for amount-only
     * refunds — each seller's proportional share of the refunded amount.
     *
     * @param list<array{order_item_id:int, quantity:int}> $items
     */
    public static function reverseForRefund(Database $db, array $order, int $amountMinor, array $items, bool $full, ?int $actorId): void
    {
        $lines = $db->all('SELECT * FROM order_items WHERE order_id = ? AND seller_id IS NOT NULL', [$order['id']]);
        if (!$lines) {
            return;
        }
        $byId = [];
        foreach ($lines as $l) {
            $byId[(int) $l['id']] = $l;
        }
        $want = [];
        if ($full) {
            foreach ($byId as $id => $l) {
                $want[$id] = PHP_INT_MAX;
            }
        } elseif ($items) {
            foreach ($items as $it) {
                $l = $byId[(int) $it['order_item_id']] ?? null;
                if ($l && (int) $l['quantity'] > 0) {
                    $want[(int) $l['id']] = intdiv((int) $l['seller_amount_minor'] * (int) $it['quantity'], (int) $l['quantity']);
                }
            }
        } elseif ((int) $order['total_minor'] > 0) {
            foreach ($byId as $id => $l) {
                $want[$id] = intdiv((int) $l['seller_amount_minor'] * $amountMinor, (int) $order['total_minor']);
            }
        }
        foreach ($want as $itemId => $amount) {
            $sale = $db->one("SELECT * FROM seller_ledger WHERE sale_item_id = ?", [$itemId]);
            if (!$sale) {
                continue; // never credited (unpaid)
            }
            $remaining = (int) $db->value("SELECT COALESCE(SUM(amount_minor), 0) FROM seller_ledger WHERE order_item_id = ? AND type IN ('sale','refund')", [$itemId]);
            $amount = min($amount, $remaining);
            if ($amount <= 0) {
                continue;
            }
            // Still pending → the reversal stays pending with it; already released → comes off the available balance now.
            $pending = $sale['available_at'] === null || strtotime($sale['available_at'] . ' UTC') > time();
            $db->insert(
                "INSERT INTO seller_ledger (seller_id, type, amount_minor, currency, order_id, order_item_id, available_at, note, created_by) VALUES (?, 'refund', ?, ?, ?, ?, ?, ?, ?)",
                [$sale['seller_id'], -$amount, $sale['currency'], $order['id'], $itemId, $pending ? $sale['available_at'] : gmdate('Y-m-d H:i:s'),
                 'Refund on order ' . $order['order_number'], $actorId]
            );
        }
    }

    // ───────────────────────────── Balances & withdrawals

    /** @return array{pending:int, available:int, withdrawn:int, in_withdrawal:int, lifetime:int, currency:string} */
    public static function balances(int $sellerId): array
    {
        $cur = Currency::base();
        $row = Database::get()->one(
            "SELECT
               COALESCE(SUM(CASE WHEN available_at IS NULL OR available_at > UTC_TIMESTAMP() THEN amount_minor END), 0) AS pending,
               COALESCE(SUM(CASE WHEN available_at <= UTC_TIMESTAMP() THEN amount_minor END), 0) AS available,
               COALESCE(SUM(CASE WHEN type IN ('sale','refund') THEN amount_minor END), 0) AS lifetime
             FROM seller_ledger WHERE seller_id = ? AND currency = ?",
            [$sellerId, $cur]
        );
        $w = Database::get()->one(
            "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_minor END), 0) AS withdrawn,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount_minor END), 0) AS in_withdrawal
             FROM seller_withdrawals WHERE seller_id = ? AND currency = ?",
            [$sellerId, $cur]
        );
        return [
            'pending' => (int) $row['pending'], 'available' => (int) $row['available'], 'lifetime' => (int) $row['lifetime'],
            'withdrawn' => (int) $w['withdrawn'], 'in_withdrawal' => (int) $w['in_withdrawal'], 'currency' => $cur,
        ];
    }

    /** Seller requests a payout of (part of) their available balance to their saved TRC20 address. */
    public static function requestWithdrawal(int $sellerId, int $amountMinor, int $userId): int
    {
        $db = Database::get();
        $id = $db->transaction(static function (Database $db) use ($sellerId, $amountMinor, $userId): int {
            // Locking the seller row serialises withdrawals: no double spending from parallel requests.
            $s = $db->one('SELECT * FROM sellers WHERE id = ? FOR UPDATE', [$sellerId]);
            if (!$s || $s['status'] !== 'approved') {
                throw ValidationException::one('amount', 'Withdrawals are not available for this account.');
            }
            $address = (string) $s['payout_address'];
            if (!CryptoTransferProvider::validAddress('usdt_trc20', $address)) {
                throw ValidationException::one('amount', 'Add your USDT TRC20 payout address in Shop settings first.');
            }
            if ($db->value("SELECT id FROM seller_withdrawals WHERE seller_id = ? AND status = 'pending'", [$sellerId])) {
                throw ValidationException::one('amount', 'You already have a withdrawal being processed.');
            }
            $min = (int) self::setting('min_withdrawal_minor');
            if ($amountMinor < max(1, $min)) {
                throw ValidationException::one('amount', 'The minimum withdrawal is ' . money($min) . '.');
            }
            $available = self::balances($sellerId)['available'];
            if ($amountMinor > $available) {
                throw ValidationException::one('amount', 'You can withdraw up to ' . money(max(0, $available)) . '.');
            }
            $cur = Currency::base();
            $wid = $db->insert(
                'INSERT INTO seller_withdrawals (seller_id, amount_minor, currency, network, address) VALUES (?, ?, ?, ?, ?)',
                [$sellerId, $amountMinor, $cur, self::WITHDRAW_NETWORK, $address]
            );
            $db->insert(
                "INSERT INTO seller_ledger (seller_id, type, amount_minor, currency, withdrawal_id, available_at, note, created_by) VALUES (?, 'withdrawal', ?, ?, ?, UTC_TIMESTAMP(), ?, ?)",
                [$sellerId, -$amountMinor, $cur, $wid, 'Withdrawal #' . $wid . ' to ' . $address, $userId]
            );
            return $wid;
        });
        Audit::log('seller.withdrawal_request', 'seller', $sellerId, null, ['withdrawal' => $id, 'amount_minor' => $amountMinor]);
        Notifications::staffAlertText('Seller withdrawal requested', 'A seller requested a withdrawal of ' . money($amountMinor) . '. Review it in Admin → Withdrawals.');
        return $id;
    }

    /** Staff mark a withdrawal paid (with the on-chain TXID) or reject it (funds return to the seller's balance). */
    public static function processWithdrawal(int $withdrawalId, bool $paid, ?string $txid, ?string $note, int $actorId): array
    {
        $db = Database::get();
        $w = $db->transaction(static function (Database $db) use ($withdrawalId, $paid, $txid, $note, $actorId): array {
            $w = $db->one('SELECT * FROM seller_withdrawals WHERE id = ? FOR UPDATE', [$withdrawalId]);
            if (!$w) {
                throw ValidationException::one('txid', 'Withdrawal not found.');
            }
            if ($w['status'] !== 'pending') {
                throw ValidationException::one('txid', 'This withdrawal was already ' . $w['status'] . '.');
            }
            if ($paid) {
                $txid = trim((string) $txid);
                if (!(new CryptoTransferProvider('usdt_trc20'))->validReference($txid)) {
                    throw ValidationException::one('txid', 'Enter the 64-character TRC20 transaction hash of the payout.');
                }
                if ($db->value('SELECT id FROM seller_withdrawals WHERE txid = ?', [$txid])) {
                    throw ValidationException::one('txid', 'This transaction hash is already recorded on another withdrawal.');
                }
                $db->exec("UPDATE seller_withdrawals SET status = 'paid', txid = ?, admin_note = ?, processed_at = UTC_TIMESTAMP(), processed_by = ? WHERE id = ?", [$txid, $note, $actorId, $withdrawalId]);
            } else {
                if (trim((string) $note) === '') {
                    throw ValidationException::one('note', 'Give a reason — the seller will see it.');
                }
                $db->exec("UPDATE seller_withdrawals SET status = 'rejected', admin_note = ?, processed_at = UTC_TIMESTAMP(), processed_by = ? WHERE id = ?", [$note, $actorId, $withdrawalId]);
                $db->insert(
                    "INSERT INTO seller_ledger (seller_id, type, amount_minor, currency, withdrawal_id, available_at, note, created_by) VALUES (?, 'withdrawal_reversal', ?, ?, ?, UTC_TIMESTAMP(), ?, ?)",
                    [$w['seller_id'], $w['amount_minor'], $w['currency'], $withdrawalId, 'Withdrawal #' . $withdrawalId . ' rejected — returned to balance', $actorId]
                );
            }
            return $db->one('SELECT * FROM seller_withdrawals WHERE id = ?', [$withdrawalId]);
        });
        Audit::log($paid ? 'seller.withdrawal_paid' : 'seller.withdrawal_rejected', 'seller', (int) $w['seller_id'], null, ['withdrawal' => $withdrawalId, 'amount_minor' => (int) $w['amount_minor'], 'txid' => $w['txid'], 'note' => $note]);
        $s = $db->one('SELECT s.*, u.email FROM sellers s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$w['seller_id']]);
        if ($s) {
            Mailer::queue($s['email'], 'seller_withdrawal', ['seller' => $s, 'w' => $w, 'walletUrl' => url('/seller/wallet')]);
        }
        return $w;
    }

    /** Items of one seller in an order (for the seller's order view). */
    public static function sellerItems(int $sellerId, int $orderId): array
    {
        return Database::get()->all('SELECT * FROM order_items WHERE order_id = ? AND seller_id = ? ORDER BY id', [$orderId, $sellerId]);
    }

    /** Statuses in which a seller sees an order (paid, not cancelled). */
    public const SELLER_ORDER_STATUSES = ['confirmed', 'processing', 'packed', 'shipped', 'delivered', 'refunded'];
}
