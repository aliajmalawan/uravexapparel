<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Mail\Mailer;
use App\Services\Audit;
use App\Services\Marketplace;

/** Marketplace administration: seller accounts, product review and payouts. */
final class SellerController extends AdminController
{
    public function index(Request $r): Response
    {
        $db = Database::get();
        $status = (string) ($r->query['status'] ?? '');
        $where = in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true) ? 'WHERE s.status = ?' : '';
        $rows = $db->all(
            "SELECT s.*, u.name AS owner_name, u.email,
                    (SELECT COUNT(*) FROM products p WHERE p.seller_id = s.id AND p.status = 'active') AS live_products,
                    (SELECT COALESCE(SUM(oi.line_total_minor), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = s.id AND o.payment_status IN ('paid','partially_refunded')) AS gross_sales
             FROM sellers s JOIN users u ON u.id = s.user_id {$where}
             ORDER BY FIELD(s.status, 'pending', 'approved', 'suspended', 'rejected'), s.applied_at DESC LIMIT 200",
            $where ? [$status] : []
        );
        return $this->admin('sellers/index', [
            'rows' => $rows, 'status' => $status,
            'pendingReviews' => (int) $db->value("SELECT COUNT(*) FROM products WHERE seller_id IS NOT NULL AND review_status = 'pending' AND status <> 'archived'"),
            'pendingWithdrawals' => (int) $db->value("SELECT COUNT(*) FROM seller_withdrawals WHERE status = 'pending'"),
            'm' => \App\Services\Settings::get('marketplace'),
        ], 'Sellers');
    }

    public function show(Request $r): Response
    {
        $db = Database::get();
        $s = $db->one('SELECT s.*, u.name AS owner_name, u.email, u.phone AS owner_phone FROM sellers s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) $r->params['id']]);
        if (!$s) {
            throw new HttpException(404);
        }
        return $this->admin('sellers/show', [
            's' => $s,
            'balances' => Marketplace::balances((int) $s['id']),
            'commission' => Marketplace::commissionPercent($s),
            'products' => $db->all("SELECT id, title, status, review_status, price_minor, currency FROM products WHERE seller_id = ? AND status <> 'archived' ORDER BY updated_at DESC LIMIT 100", [$s['id']]),
            'ledger' => $db->all('SELECT * FROM seller_ledger WHERE seller_id = ? ORDER BY id DESC LIMIT 50', [$s['id']]),
            'withdrawals' => $db->all('SELECT * FROM seller_withdrawals WHERE seller_id = ? ORDER BY id DESC LIMIT 20', [$s['id']]),
        ], $s['shop_name']);
    }

    public function decide(Request $r): Response
    {
        $s = Marketplace::decide((int) $r->params['id'], $r->str('action'), mb_substr(trim($r->str('note')), 0, 500) ?: null, $this->actorId());
        return $this->redirect('/admin/sellers/' . $s['id'], 'Seller is now ' . $s['status'] . '. They have been emailed.');
    }

    public function commission(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $raw = trim($r->str('commission_percent'));
        if ($raw !== '' && (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $raw) || bccomp($raw, '90', 2) > 0)) {
            throw ValidationException::one('commission_percent', 'Enter a percentage between 0 and 90, or leave blank for the store default.');
        }
        $db = Database::get();
        $before = $db->value('SELECT commission_percent FROM sellers WHERE id = ?', [$id]);
        $db->exec('UPDATE sellers SET commission_percent = ? WHERE id = ?', [$raw === '' ? null : $raw, $id]);
        Audit::log('seller.commission', 'seller', $id, ['commission_percent' => $before], ['commission_percent' => $raw ?: 'default']);
        return $this->redirect('/admin/sellers/' . $id, 'Commission saved. It applies to new orders only.');
    }

    public function reviews(Request $r): Response
    {
        $rows = Database::get()->all(
            "SELECT p.*, s.shop_name, s.id AS sid, c.name AS category_name,
                    (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY position, id LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) AS image_count,
                    (SELECT COALESCE(SUM(stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
             FROM products p JOIN sellers s ON s.id = p.seller_id LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.review_status = 'pending' AND p.status <> 'archived' ORDER BY p.updated_at"
        );
        return $this->admin('sellers/reviews', ['rows' => $rows], 'Product reviews');
    }

    public function reviewProduct(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $approve = $r->str('decision') === 'approve';
        $note = mb_substr(trim($r->str('note')), 0, 500);
        $db = Database::get();
        $p = $db->one("SELECT p.*, s.status AS seller_status, s.shop_name, u.email FROM products p JOIN sellers s ON s.id = p.seller_id JOIN users u ON u.id = s.user_id WHERE p.id = ? AND p.review_status = 'pending'", [$id]);
        if (!$p) {
            throw ValidationException::one('decision', 'This product is not waiting for review.');
        }
        if ($approve) {
            if ($p['seller_status'] !== 'approved') {
                throw ValidationException::one('decision', 'The seller is not approved.');
            }
            if (!(int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_active = 1', [$id])) {
                throw ValidationException::one('decision', 'The product has no active variants.');
            }
            $db->exec("UPDATE products SET status = 'active', review_status = 'approved', review_note = NULL, published_at = COALESCE(published_at, UTC_TIMESTAMP()) WHERE id = ?", [$id]);
        } else {
            if ($note === '') {
                throw ValidationException::one('note', 'Tell the seller what to fix.');
            }
            $db->exec("UPDATE products SET status = 'draft', review_status = 'rejected', review_note = ? WHERE id = ?", [$note, $id]);
        }
        Audit::log($approve ? 'seller.product_approve' : 'seller.product_reject', 'product', $id, null, ['note' => $note]);
        Mailer::queue($p['email'], 'seller_product_review', ['p' => $p, 'approved' => $approve, 'note' => $note, 'url' => url('/seller/products/' . $id)]);
        return $this->redirect('/admin/sellers/reviews', $approve ? 'Product approved and live.' : 'Product sent back to the seller.');
    }

    public function withdrawals(Request $r): Response
    {
        $status = (string) ($r->query['status'] ?? 'pending');
        $status = in_array($status, ['pending', 'paid', 'rejected', 'all'], true) ? $status : 'pending';
        $rows = Database::get()->all(
            'SELECT w.*, s.shop_name, s.id AS sid FROM seller_withdrawals w JOIN sellers s ON s.id = w.seller_id'
            . ($status !== 'all' ? ' WHERE w.status = ?' : '') . ' ORDER BY w.requested_at ' . ($status === 'pending' ? 'ASC' : 'DESC') . ' LIMIT 200',
            $status !== 'all' ? [$status] : []
        );
        foreach ($rows as &$w) {
            $w['balances'] = Marketplace::balances((int) $w['seller_id']);
        }
        unset($w);
        return $this->admin('sellers/withdrawals', ['rows' => $rows, 'status' => $status], 'Withdrawals');
    }

    public function processWithdrawal(Request $r): Response
    {
        $paid = $r->str('decision') === 'paid';
        Marketplace::processWithdrawal((int) $r->params['id'], $paid, $r->str('txid'), mb_substr(trim($r->str('note')), 0, 500) ?: null, $this->actorId());
        return $this->redirect('/admin/withdrawals', $paid ? 'Withdrawal marked as paid. The seller has been emailed.' : 'Withdrawal rejected; the amount is back in the seller\'s balance.');
    }
}
