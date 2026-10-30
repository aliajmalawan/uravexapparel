<?php

declare(strict_types=1);

namespace App\Controllers\Seller;

use App\Auth\Auth;
use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\RateLimiter;
use App\Core\RedirectException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\Marketplace;
use App\Services\Orders;
use App\Services\Shipping;

/**
 * Seller panel. Every query is scoped to the signed-in user's own seller
 * account — a seller can never see or change another seller's data.
 */
class SellerController extends Controller
{
    /** The signed-in user's seller account; with $approved, only an approved one. */
    protected function seller(bool $approved = true): array
    {
        $s = Marketplace::sellerForUser(Auth::id());
        if (!$s) {
            throw new RedirectException('/sell');
        }
        if ($approved && $s['status'] !== 'approved') {
            Session::flash('warning', $s['status'] === 'pending' ? 'Your shop is waiting for approval.' : 'Your seller account is ' . $s['status'] . '.');
            throw new RedirectException('/seller');
        }
        return $s;
    }

    /** Re-checks the account password before money-related actions. */
    private function passwordOk(string $password): bool
    {
        $hash = (string) Database::get()->value('SELECT password_hash FROM users WHERE id = ?', [Auth::id()]);
        return $password !== '' && $hash !== '' && Auth::verifyPassword($password, $hash);
    }

    protected function panel(string $template, array $data, string $title): Response
    {
        return $this->view($template, $data + ['seller' => $data['seller'] ?? $this->seller(false)], ['title' => $title, 'noindex' => true]);
    }

    // ───────────────────────────── Application

    public function applyForm(Request $r): Response
    {
        $existing = Marketplace::sellerForUser(Auth::id());
        if ($existing && $existing['status'] !== 'rejected') {
            return $this->redirect('/seller');
        }
        if (!Auth::check()) {
            Session::put('intended', '/sell');
        }
        return $this->view('seller/apply', [
            'existing' => $existing,
            'open' => (bool) Marketplace::setting('applications_open'),
            'commission' => Marketplace::commissionPercent([]),
            'holdDays' => (int) Marketplace::setting('hold_days'),
        ], ['title' => 'Sell on URAVEX', 'description' => 'Open a shop on URAVEX and sell your apparel to customers across the United States.']);
    }

    public function apply(Request $r): Response
    {
        RateLimiter::hit('seller-apply:' . Auth::id(), 5, 3600);
        $d = Validator::make($r->post, [
            'shop_name' => 'required|string|min:3|max:80',
            'description' => 'required|string|min:20|max:2000',
            'contact_phone' => 'string|max:32',
            'payout_address' => 'string|max:60',
            'agree' => 'bool',
        ])->validated();
        if (!$d['agree']) {
            throw ValidationException::one('agree', 'Please accept the seller terms.');
        }
        Marketplace::apply((int) Auth::id(), $d + ['payout_address' => '']);
        return $this->redirect('/seller', 'Application received — we will review your shop and email you.');
    }

    // ───────────────────────────── Panel pages

    public function dashboard(Request $r): Response
    {
        $s = $this->seller(false);
        $db = Database::get();
        $data = ['seller' => $s];
        if ($s['status'] === 'approved') {
            $data += [
                'balances' => Marketplace::balances((int) $s['id']),
                'productCounts' => $db->one(
                    "SELECT SUM(status = 'active') AS live, SUM(review_status = 'pending') AS in_review, SUM(review_status = 'rejected') AS rejected, COUNT(*) AS total FROM products WHERE seller_id = ? AND status <> 'archived'",
                    [$s['id']]
                ),
                'toShip' => (int) $db->value(
                    "SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id = o.id
                     WHERE oi.seller_id = ? AND o.status IN ('confirmed','processing','packed')
                       AND NOT EXISTS (SELECT 1 FROM seller_shipments ss WHERE ss.order_id = o.id AND ss.seller_id = oi.seller_id)",
                    [$s['id']]
                ),
                'recent' => $this->orderRows((int) $s['id'], 5),
            ];
        }
        return $this->panel('seller/dashboard', $data, 'Seller dashboard');
    }

    /** Paid orders containing this seller's items, newest first. */
    private function orderRows(int $sellerId, int $limit, int $offset = 0): array
    {
        return Database::get()->all(
            'SELECT o.id, o.order_number, o.status, o.placed_at, o.currency, o.customer_name,
                    SUM(oi.quantity) AS qty, SUM(oi.seller_amount_minor) AS earnings,
                    (SELECT COUNT(*) FROM seller_shipments ss WHERE ss.order_id = o.id AND ss.seller_id = ?) AS shipped_by_me
             FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE oi.seller_id = ? AND o.status IN ' . Database::in(Marketplace::SELLER_ORDER_STATUSES) . '
             GROUP BY o.id ORDER BY o.placed_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$sellerId, $sellerId, ...Marketplace::SELLER_ORDER_STATUSES]
        );
    }

    public function orders(Request $r): Response
    {
        $s = $this->seller();
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $total = (int) Database::get()->value(
            'SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.status IN ' . Database::in(Marketplace::SELLER_ORDER_STATUSES),
            [$s['id'], ...Marketplace::SELLER_ORDER_STATUSES]
        );
        return $this->panel('seller/orders', [
            'seller' => $s, 'rows' => $this->orderRows((int) $s['id'], 25, ($page - 1) * 25),
            'page' => $page, 'pages' => max(1, (int) ceil($total / 25)),
        ], 'Seller orders');
    }

    /** Order view limited to this seller's lines; shows the ship-to address (no email/payment details). */
    private function sellerOrder(array $s, string $number): array
    {
        $o = Orders::findByNumber($number);
        if (!$o || !in_array($o['status'], Marketplace::SELLER_ORDER_STATUSES, true)) {
            throw new HttpException(404);
        }
        $items = Marketplace::sellerItems((int) $s['id'], (int) $o['id']);
        if (!$items) {
            throw new HttpException(404);
        }
        $o['items'] = $items;
        $o['shipping'] = json_decode((string) $o['shipping_address'], true) ?: [];
        $o['my_shipment'] = Database::get()->one('SELECT * FROM seller_shipments WHERE order_id = ? AND seller_id = ?', [$o['id'], $s['id']]);
        return $o;
    }

    public function order(Request $r): Response
    {
        $s = $this->seller();
        return $this->panel('seller/order', [
            'seller' => $s, 'o' => $this->sellerOrder($s, $r->params['number']),
            'couriers' => Database::get()->column('SELECT name FROM couriers WHERE is_active = 1 ORDER BY name'),
        ], 'Order ' . $r->params['number']);
    }

    public function tracking(Request $r): Response
    {
        $s = $this->seller();
        $o = $this->sellerOrder($s, $r->params['number']);
        if (!in_array($o['status'], ['confirmed', 'processing', 'packed', 'shipped'], true)) {
            throw ValidationException::one('tracking_number', 'Tracking can no longer be changed for this order.');
        }
        $d = Validator::make($r->post, ['courier' => 'required|string|max:80', 'tracking_number' => 'required|string|min:4|max:80|regex:/^[A-Za-z0-9 -]+$/'])->validated();
        $db = Database::get();
        if (!$db->value('SELECT id FROM couriers WHERE name = ? AND is_active = 1', [$d['courier']])) {
            throw ValidationException::one('courier', 'Choose a carrier from the list.');
        }
        $url = Shipping::trackingUrl($d['courier'], $d['tracking_number']);
        $db->exec(
            'INSERT INTO seller_shipments (order_id, seller_id, courier, tracking_number, tracking_url) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE courier = VALUES(courier), tracking_number = VALUES(tracking_number), tracking_url = VALUES(tracking_url), shipped_at = UTC_TIMESTAMP()',
            [$o['id'], $s['id'], $d['courier'], $d['tracking_number'], $url]
        );
        Orders::history((int) $o['id'], $o['status'], $o['status'], 'Items from ' . $s['shop_name'] . " shipped with {$d['courier']} — tracking {$d['tracking_number']}", (int) Auth::id());
        Audit::log('seller.tracking', 'order', (int) $o['id'], null, ['seller' => (int) $s['id']] + $d);
        return $this->redirect('/seller/orders/' . $o['order_number'], 'Tracking saved. The customer can see it on their order page.');
    }

    public function wallet(Request $r): Response
    {
        $s = $this->seller();
        $db = Database::get();
        return $this->panel('seller/wallet', [
            'seller' => $s,
            'balances' => Marketplace::balances((int) $s['id']),
            'ledger' => $db->all('SELECT * FROM seller_ledger WHERE seller_id = ? ORDER BY id DESC LIMIT 100', [$s['id']]),
            'withdrawals' => $db->all('SELECT * FROM seller_withdrawals WHERE seller_id = ? ORDER BY id DESC LIMIT 50', [$s['id']]),
            'minWithdrawal' => (int) Marketplace::setting('min_withdrawal_minor'),
            'holdDays' => (int) Marketplace::setting('hold_days'),
        ], 'Wallet');
    }

    public function withdraw(Request $r): Response
    {
        $s = $this->seller();
        RateLimiter::hit('seller-withdraw:' . $s['id'], 10, 3600);
        $amount = Money::parse($r->str('amount'));
        if ($amount === null || $amount <= 0) {
            throw ValidationException::one('amount', 'Enter an amount, e.g. 50.00.');
        }
        if (!$this->passwordOk($r->str('password'))) {
            throw ValidationException::one('password', 'Your password is incorrect.');
        }
        Marketplace::requestWithdrawal((int) $s['id'], $amount, (int) Auth::id());
        return $this->redirect('/seller/wallet', 'Withdrawal requested. You will get an email when it is sent.');
    }

    public function settings(Request $r): Response
    {
        return $this->panel('seller/settings', ['seller' => $this->seller(false)], 'Shop settings');
    }

    public function saveSettings(Request $r): Response
    {
        $s = $this->seller(false);
        if (in_array($s['status'], ['suspended', 'rejected'], true)) {
            throw new HttpException(403);
        }
        $d = Validator::make($r->post, [
            'shop_name' => 'required|string|min:3|max:80',
            'description' => 'required|string|min:20|max:2000',
            'contact_phone' => 'string|max:32',
            'payout_address' => 'string|max:60',
            'password' => 'string|max:128',
        ])->validated();
        // Shop name (and its URL) can be changed; names stay unique across sellers.
        $name = trim(preg_replace('/\s+/', ' ', (string) $d['shop_name']));
        $slug = $name !== $s['shop_name'] ? slugify($name) : $s['slug'];
        if ($name !== $s['shop_name'] && Database::get()->value('SELECT id FROM sellers WHERE (shop_name = ? OR slug = ?) AND id <> ?', [$name, $slug, $s['id']])) {
            throw ValidationException::one('shop_name', 'That shop name is taken. Please choose another.');
        }
        $address = (string) $d['payout_address'];
        Marketplace::checkPayoutAddress($address);
        $changedAddress = $address !== (string) $s['payout_address'];
        // Changing where money is sent requires the account password.
        if ($changedAddress && !$this->passwordOk((string) $d['password'])) {
            throw ValidationException::one('password', 'Enter your account password to change the payout address.');
        }
        Database::get()->exec('UPDATE sellers SET shop_name = ?, slug = ?, description = ?, contact_phone = ?, payout_address = ? WHERE id = ?', [$name, $slug, $d['description'], $d['contact_phone'], $address ?: null, $s['id']]);
        if ($name !== $s['shop_name']) {
            Audit::log('seller.rename', 'seller', (int) $s['id'], ['shop_name' => $s['shop_name']], ['shop_name' => $name]);
        }
        Audit::log('seller.settings', 'seller', (int) $s['id'], $changedAddress ? ['payout_address' => $s['payout_address']] : null, $changedAddress ? ['payout_address' => $address] : ['profile' => 'updated']);
        if ($changedAddress) {
            \App\Mail\Mailer::queue((string) Auth::user()['email'], 'seller_payout_changed', ['seller' => $s, 'address' => $address]);
            \App\Mail\Mailer::flush();
        }
        return $this->redirect('/seller/settings', 'Shop settings saved.');
    }
}
