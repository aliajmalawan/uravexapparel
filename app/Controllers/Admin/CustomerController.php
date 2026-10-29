<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Services\Audit;

final class CustomerController extends AdminController
{
    private function where(array $q): array
    {
        $where = ["u.role = 'customer'"];
        $params = [];
        if (($term = trim((string) ($q['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    private const STATS = "(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('cancelled')) AS orders,
        (SELECT COALESCE(SUM(o.total_minor - o.refunded_minor),0) FROM orders o WHERE o.user_id = u.id AND o.payment_status IN ('paid','partially_refunded')) AS spend,
        (SELECT MAX(o.placed_at) FROM orders o WHERE o.user_id = u.id) AS last_order";

    public function index(Request $r): Response
    {
        [$where, $params] = $this->where($r->query);
        $db = Database::get();
        $pg = $this->page($r->query, 25);
        $sort = ($r->query['sort'] ?? '') === 'spend' ? 'spend DESC' : 'u.created_at DESC';
        $total = (int) $db->value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $rows = $db->all("SELECT u.id, u.name, u.email, u.phone, u.created_at, u.email_verified_at, u.is_demo, " . self::STATS . " FROM users u WHERE {$where} ORDER BY {$sort} LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        return $this->admin('customers/index', ['rows' => $rows, 'total' => $total, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])), 'query' => array_intersect_key($r->query, ['q' => 1, 'sort' => 1])], 'Customers');
    }

    public function show(Request $r): Response
    {
        $db = Database::get();
        $u = $db->one("SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at, u.email_verified_at, u.last_login_at, u.disabled, " . self::STATS . " FROM users u WHERE u.id = ?", [(int) $r->params['id']]);
        if (!$u) {
            throw new HttpException(404);
        }
        return $this->admin('customers/show', [
            'u' => $u,
            'orders' => $db->all('SELECT * FROM orders WHERE user_id = ? ORDER BY placed_at DESC LIMIT 50', [$u['id']]),
            'addresses' => $db->all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC', [$u['id']]),
        ], $u['name']);
    }

    /** Edit contact details, verification and account status (staff accounts are managed under Staff). */
    public function update(Request $r): Response
    {
        $db = Database::get();
        $u = $db->one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [(int) $r->params['id']]);
        if (!$u) {
            throw new HttpException(404);
        }
        $d = \App\Core\Validator::make($r->post, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:191',
            'phone' => 'phone|max:32',
            'verified' => 'bool',
            'disabled' => 'bool',
        ])->validated();
        $email = mb_strtolower($d['email']);
        if ($db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
            throw \App\Core\ValidationException::one('email', 'Another account already uses this email.');
        }
        $verifiedAt = $d['verified'] ? ($u['email_verified_at'] ?? now_utc()) : null;
        // Disabling or changing the email signs the customer out everywhere.
        $revoke = $d['disabled'] !== (bool) $u['disabled'] || $email !== $u['email'];
        $db->exec(
            'UPDATE users SET name = ?, email = ?, phone = ?, email_verified_at = ?, disabled = ?, session_version = session_version + ? WHERE id = ?',
            [$d['name'], $email, $d['phone'], $verifiedAt, $d['disabled'], $revoke ? 1 : 0, $u['id']]
        );
        [$b, $a] = Audit::diff(
            ['name' => $u['name'], 'email' => $u['email'], 'phone' => $u['phone'], 'verified' => $u['email_verified_at'] !== null, 'disabled' => (bool) $u['disabled']],
            ['name' => $d['name'], 'email' => $email, 'phone' => $d['phone'], 'verified' => $d['verified'], 'disabled' => $d['disabled']]
        );
        if ($a) {
            Audit::log('customer.update', 'user', $u['id'], $b, $a);
        }
        return $this->redirect('/admin/customers/' . $u['id'], 'Customer updated.');
    }

    /** Export excludes passwords, tokens and sessions; ?masked=1 masks contact details. */
    public function export(Request $r): Response
    {
        [$where, $params] = $this->where($r->query);
        $masked = ($r->query['masked'] ?? '') === '1';
        $rows = Database::get()->all("SELECT u.name, u.email, u.phone, u.created_at, " . self::STATS . " FROM users u WHERE {$where} ORDER BY u.created_at DESC", $params);
        Audit::log('export.customers', 'export', null, null, ['rows' => count($rows), 'masked' => $masked]);
        return $this->csv('customers-' . gmdate('Ymd') . ($masked ? '-masked' : '') . '.csv', ['name', 'email', 'phone', 'joined', 'orders', 'lifetime_spend', 'last_order'], array_map(static fn ($u) => [
            $u['name'],
            $masked ? Logger::maskEmail($u['email']) : $u['email'],
            $masked && $u['phone'] ? substr($u['phone'], 0, 4) . str_repeat('*', max(0, strlen($u['phone']) - 6)) . substr($u['phone'], -2) : $u['phone'],
            date_only($u['created_at']), $u['orders'], Money::decimal((int) $u['spend']), $u['last_order'] ? date_only($u['last_order']) : '',
        ], $rows));
    }
}
