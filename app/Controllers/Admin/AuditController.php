<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/** Read-only view of the append-only audit trail. */
final class AuditController extends AdminController
{
    public function index(Request $r): Response
    {
        $where = ['1=1'];
        $params = [];
        if (($a = trim((string) ($r->query['action'] ?? ''))) !== '') {
            $where[] = 'l.action LIKE ?';
            $params[] = addcslashes($a, '%_\\') . '%';
        }
        if (($t = trim((string) ($r->query['entity'] ?? ''))) !== '') {
            $where[] = 'l.entity_type = ?';
            $params[] = $t;
        }
        if (($id = trim((string) ($r->query['entity_id'] ?? ''))) !== '') {
            $where[] = 'l.entity_id = ?';
            $params[] = $id;
        }
        $db = Database::get();
        $pg = $this->page($r->query, 50);
        $sql = ' FROM admin_audit_logs l WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->all('SELECT l.*, (l.entity_type = \'order\' AND EXISTS (SELECT 1 FROM orders o WHERE o.id = l.entity_id)) AS order_exists' . $sql . ' ORDER BY l.id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], $params);
        return $this->admin('audit/index', [
            'rows' => $rows, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])),
            'query' => array_intersect_key($r->query, ['action' => 1, 'entity' => 1, 'entity_id' => 1]),
            'entities' => $db->column('SELECT DISTINCT entity_type FROM admin_audit_logs ORDER BY entity_type'),
        ], 'Audit log');
    }
}
