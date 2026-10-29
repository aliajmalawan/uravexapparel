<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Mail\Mailer;
use App\Payments\Registry;
use App\Services\Audit;
use App\Services\Orders;
use App\Services\OrderState;
use App\Services\Settings;

final class OrderController extends AdminController
{
    /** @return array{0:string,1:array} */
    private function filters(array $q): array
    {
        $where = ['1=1'];
        $params = [];
        $term = trim((string) ($q['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $where[] = '(o.order_number LIKE ? OR o.email LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (isset(OrderState::STATUSES[$q['status'] ?? ''])) {
            $where[] = 'o.status = ?';
            $params[] = $q['status'];
        }
        if (in_array($q['payment'] ?? '', ['unpaid', 'pending_verification', 'paid', 'partially_refunded', 'refunded', 'failed', 'voided'], true)) {
            $where[] = 'o.payment_status = ?';
            $params[] = $q['payment'];
        }
        if (Registry::get((string) ($q['method'] ?? ''))) {
            $where[] = 'o.payment_method = ?';
            $params[] = $q['method'];
        }
        if (!empty($q['from']) && ($f = local_to_utc((string) $q['from']))) {
            $where[] = 'o.placed_at >= ?';
            $params[] = $f;
        }
        if (!empty($q['to']) && ($t = local_to_utc((string) $q['to'], true))) {
            $where[] = 'o.placed_at <= ?';
            $params[] = $t;
        }
        if (($q['test'] ?? '') !== '1') {
            $where[] = 'o.is_test = 0';
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(Request $r): Response
    {
        [$where, $params] = $this->filters($r->query);
        $db = Database::get();
        $pg = $this->page($r->query, 25);
        $total = (int) $db->value("SELECT COUNT(*) FROM orders o WHERE {$where}", $params);
        $rows = $db->all(
            "SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items FROM orders o WHERE {$where} ORDER BY o.placed_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        $query = array_intersect_key($r->query, array_flip(['q', 'status', 'payment', 'method', 'from', 'to', 'test']));
        return $this->admin('orders/index', [
            'rows' => $rows, 'total' => $total, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])), 'query' => $query,
            'methods' => Registry::all(),
        ], 'Orders');
    }

    public function export(Request $r): Response
    {
        [$where, $params] = $this->filters($r->query);
        $rows = Database::get()->all(
            "SELECT o.order_number, o.placed_at, o.status, o.payment_status, o.payment_method, o.customer_name, o.email, o.phone,
                    JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.city')) AS city,
                    o.subtotal_minor, o.discount_minor, o.shipping_minor, o.total_minor, o.refunded_minor, o.coupon_code
             FROM orders o WHERE {$where} ORDER BY o.placed_at DESC LIMIT 20000",
            $params
        );
        Audit::log('export.orders', 'export', null, null, ['rows' => count($rows), 'filters' => array_intersect_key($r->query, array_flip(['status', 'payment', 'method', 'from', 'to']))]);
        return $this->csv('orders-' . gmdate('Ymd-His') . '.csv',
            ['order_number', 'placed_at_local', 'status', 'payment_status', 'payment_method', 'customer', 'email', 'phone', 'city', 'subtotal', 'discount', 'shipping', 'total', 'refunded', 'coupon'],
            array_map(static fn ($o) => [
                $o['order_number'], dt($o['placed_at'], 'Y-m-d H:i'), $o['status'], $o['payment_status'], $o['payment_method'], $o['customer_name'], $o['email'], $o['phone'], $o['city'],
                Money::decimal((int) $o['subtotal_minor']), Money::decimal((int) $o['discount_minor']), Money::decimal((int) $o['shipping_minor']),
                Money::decimal((int) $o['total_minor']), Money::decimal((int) $o['refunded_minor']), $o['coupon_code'],
            ], $rows));
    }

    private function load(Request $r): array
    {
        $o = Orders::detail((int) $r->params['id']);
        if (!$o) {
            throw new HttpException(404);
        }
        return $o;
    }

    public function show(Request $r): Response
    {
        $o = $this->load($r);
        $db = Database::get();
        return $this->admin('orders/show', [
            'o' => $o,
            'next' => OrderState::manualNext($o),
            'couriers' => $db->all('SELECT * FROM couriers WHERE is_active = 1 ORDER BY name'),
            'provider' => Registry::get($o['payment_method']),
            'customerOrders' => $o['user_id'] ? (int) $db->value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$o['user_id']]) : (int) $db->value('SELECT COUNT(*) FROM orders WHERE email = ?', [$o['email']]),
            'events' => $db->all('SELECT pe.* FROM payment_events pe JOIN payments p ON p.id = pe.payment_id WHERE p.order_id = ? ORDER BY pe.id', [$o['id']]),
            'refundKey' => bin2hex(random_bytes(32)),
        ], 'Order ' . $o['order_number']);
    }

    public function status(Request $r): Response
    {
        $d = Validator::make($r->post, ['to' => 'required|in:' . implode(',', array_keys(OrderState::STATUSES)), 'note' => 'string|max:1000', 'notify' => 'bool'])->validated();
        if ($d['to'] === 'cancelled') {
            Auth::authorize('orders.cancel');
        }
        Orders::transition((int) $r->params['id'], $d['to'], $d['note'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], 'Order moved to ' . OrderState::label($d['to']) . '.');
    }

    public function cancel(Request $r): Response
    {
        $d = Validator::make($r->post, ['note' => 'required|string|min:3|max:1000'])->validated();
        Orders::transition((int) $r->params['id'], 'cancelled', $d['note'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], 'Order cancelled and stock returned to inventory.');
    }

    public function ship(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'courier' => 'required|string|max:80',
            'tracking_number' => 'required|string|max:80|regex:/^[A-Za-z0-9 \-\/]+$/',
            'tracking_url' => 'url|max:255',
            'note' => 'string|max:500',
        ])->validated();
        Orders::ship((int) $r->params['id'], $d['courier'], $d['tracking_number'], $d['tracking_url'], $d['note'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], 'Shipment recorded and customer notified.');
    }

    public function codCollected(Request $r): Response
    {
        $d = Validator::make($r->post, ['note' => 'string|max:500', 'confirm' => 'bool'])->validated();
        if (!$d['confirm']) {
            throw ValidationException::one('confirm', 'Tick the box to confirm the full amount was collected.');
        }
        Orders::recordCodCollected((int) $r->params['id'], $d['note'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], 'Cash collection recorded.');
    }

    public function bankVerify(Request $r): Response
    {
        $d = Validator::make($r->post, ['decision' => 'required|in:approve,reject', 'note' => 'string|max:500'])->validated();
        if ($d['decision'] === 'reject' && !$d['note']) {
            throw ValidationException::one('note', 'Add a note explaining why the transfer was rejected (sent to the customer).');
        }
        Orders::verifyBankTransfer((int) $r->params['id'], $d['decision'] === 'approve', $d['note'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], $d['decision'] === 'approve' ? 'Payment verified — order confirmed.' : 'Transfer rejected and customer notified.');
    }

    public function refund(Request $r): Response
    {
        Auth::authorize('orders.refund');
        $d = Validator::make($r->post, [
            'amount' => 'required|money',
            'reason' => 'required|string|min:3|max:500',
            'method' => 'required|in:manual,provider',
            'reference' => 'string|max:120',
            'idempotency_key' => 'required|regex:/^[a-f0-9]{64}$/',
        ])->validated();
        $items = [];
        foreach ($r->arr('qty') as $itemId => $qty) {
            if ((int) $qty > 0) {
                $items[] = ['order_item_id' => (int) $itemId, 'quantity' => (int) $qty, 'restock' => !empty($r->arr('restock')[$itemId])];
            }
        }
        Orders::refund((int) $r->params['id'], $d['amount'], $d['reason'], $items, $d['method'], $d['reference'], $d['idempotency_key'], $this->actorId());
        Mailer::flush();
        return $this->redirect('/admin/orders/' . (int) $r->params['id'], 'Refund of ' . money($d['amount']) . ' recorded.');
    }

    public function note(Request $r): Response
    {
        $d = Validator::make($r->post, ['admin_note' => 'string|max:5000'])->validated();
        $id = (int) $r->params['id'];
        if (!Database::get()->value('SELECT id FROM orders WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        Database::get()->exec('UPDATE orders SET admin_note = ? WHERE id = ?', [$d['admin_note'], $id]);
        Audit::log('order.note', 'order', $id, null, ['admin_note' => $d['admin_note']]);
        return $this->redirect('/admin/orders/' . $id, 'Internal note saved.');
    }

    /**
     * Corrects the delivery contact/address before the order ships. The
     * previous values are kept in the audit log.
     */
    public function address(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $d = Validator::make($r->post, [
            'full_name' => 'required|string|min:2|max:120', 'phone' => 'required|phone|max:32', 'email' => 'required|email|max:191',
            'line1' => 'required|string|min:4|max:191', 'line2' => 'string|max:191', 'city' => 'required|string|min:2|max:100',
            'province' => 'required|string|max:100', 'postal_code' => 'string|max:20',
        ])->validated();
        $db = Database::get();
        $db->transaction(static function (Database $db) use ($id, $d): void {
            $o = $db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
            if (!$o) {
                throw new HttpException(404);
            }
            if (!in_array($o['status'], ['pending', 'awaiting_payment', 'confirmed', 'processing', 'packed'], true)) {
                throw ValidationException::one('address', 'The address can only be changed before the order ships.');
            }
            $old = json_decode((string) $o['shipping_address'], true) ?: [];
            $new = ['full_name' => $d['full_name'], 'phone' => $d['phone'], 'line1' => $d['line1'], 'line2' => $d['line2'], 'city' => $d['city'], 'province' => $d['province'], 'postal_code' => $d['postal_code'], 'country' => $old['country'] ?? \App\Services\Region::COUNTRY];
            $db->exec(
                'UPDATE orders SET shipping_address = ?, customer_name = ?, phone = ?, email = ? WHERE id = ?',
                [json_encode($new, JSON_UNESCAPED_UNICODE), $d['full_name'], $d['phone'], mb_strtolower($d['email']), $id]
            );
            Orders::history($id, $o['status'], $o['status'], 'Delivery details updated by staff', \App\Auth\Auth::id(), false);
            Audit::log('order.address', 'order', $id, $old + ['email' => $o['email']], $new + ['email' => mb_strtolower($d['email'])]);
        });
        return $this->redirect('/admin/orders/' . $id, 'Delivery details updated.');
    }

    public function invoice(Request $r): Response
    {
        $o = $this->load($r);
        return Response::html(View::render('admin/orders/document', ['o' => $o, 'type' => 'invoice', 'store' => Settings::get('store'), 'title' => 'Invoice ' . $o['order_number'], 'back' => '/admin/orders/' . $o['id']], 'layouts/print'));
    }

    public function packingSlip(Request $r): Response
    {
        $o = $this->load($r);
        return Response::html(View::render('admin/orders/document', ['o' => $o, 'type' => 'packing', 'store' => Settings::get('store'), 'title' => 'Packing slip ' . $o['order_number'], 'back' => '/admin/orders/' . $o['id']], 'layouts/print'));
    }

    /** Streams a private bank-transfer receipt to authorised staff only. */
    public function receipt(Request $r): Response
    {
        $p = Database::get()->one('SELECT receipt_path FROM payments WHERE id = ? AND order_id = ?', [(int) $r->params['payment'], (int) $r->params['id']]);
        $path = $p && $p['receipt_path'] ? Uploads::receiptPath($p['receipt_path']) : null;
        if (!$path) {
            throw new HttpException(404);
        }
        Audit::log('payment.receipt_view', 'order', (int) $r->params['id']);
        $type = str_ends_with($path, '.pdf') ? 'application/pdf' : 'image/jpeg';
        return Response::file($path, $type, true, 'receipt-' . (int) $r->params['id'] . (str_ends_with($path, '.pdf') ? '.pdf' : '.jpg'))
            ->header('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    }
}
