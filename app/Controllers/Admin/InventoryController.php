<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\Inventory;

final class InventoryController extends AdminController
{
    public function index(Request $r): Response
    {
        $db = Database::get();
        $q = trim((string) ($r->query['q'] ?? ''));
        $level = (string) ($r->query['level'] ?? '');
        $where = ["p.status <> 'archived'", 'v.is_active = 1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.title LIKE ? OR v.sku LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($level === 'low') {
            $where[] = 'v.stock > 0 AND v.stock <= v.low_stock_threshold';
        } elseif ($level === 'out') {
            $where[] = 'v.stock = 0';
        }
        $pg = $this->page($r->query, 50);
        $sql = ' FROM product_variants v JOIN products p ON p.id = v.product_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->all('SELECT v.*, p.title, p.id AS product_id' . $sql . ' ORDER BY (v.stock = 0) DESC, (v.stock <= v.low_stock_threshold) DESC, p.title, v.position LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], $params);
        if (($r->query['export'] ?? '') === '1') {
            Auth::authorize('reports.export');
            Audit::log('export.inventory', 'export', null, null, ['level' => $level]);
            $all = $db->all('SELECT p.title, v.sku, v.size, v.color, v.stock, v.low_stock_threshold' . $sql . ' ORDER BY p.title, v.position', $params);
            return $this->csv('inventory-' . gmdate('Ymd') . '.csv', ['product', 'sku', 'size', 'color', 'stock', 'low_stock_threshold'], $all);
        }
        return $this->admin('inventory/index', [
            'rows' => $rows, 'q' => $q, 'level' => $level, 'total' => $total, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])),
            'counts' => Inventory::lowStockCount(), 'canAdjust' => Auth::can('inventory.adjust'),
        ], 'Inventory');
    }

    public function adjust(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'variant_id' => 'required|int|min:1',
            'mode' => 'required|in:add,remove,set',
            'quantity' => 'required|int|min:0|max:100000',
            'reason' => 'required|in:restock,adjustment,damage,return',
            'note' => 'string|max:500',
        ])->validated();
        $db = Database::get();
        $v = $db->one('SELECT v.id, v.sku, v.stock FROM product_variants v WHERE v.id = ?', [$d['variant_id']]);
        if (!$v) {
            throw ValidationException::one('variant_id', 'Variant not found.');
        }
        if ($d['mode'] !== 'set' && $d['quantity'] === 0) {
            throw ValidationException::one('quantity', 'Enter a quantity greater than zero.');
        }
        $after = match ($d['mode']) {
            'add' => Inventory::apply($v['id'], $d['quantity'], $d['reason'], $d['note'], null, $this->actorId()),
            'remove' => Inventory::apply($v['id'], -$d['quantity'], $d['reason'], $d['note'], null, $this->actorId()),
            'set' => Inventory::set($v['id'], $d['quantity'], $d['reason'], $d['note'], $this->actorId()),
        };
        Audit::log('inventory.adjust', 'variant', $v['id'], ['stock' => (int) $v['stock']], ['stock' => $after, 'reason' => $d['reason'], 'note' => $d['note']]);
        \App\Core\Session::flash('success', "{$v['sku']}: stock is now {$after}.");
        return $this->back('/admin/inventory');
    }

    public function movements(Request $r): Response
    {
        $db = Database::get();
        $where = ['1=1'];
        $params = [];
        if (!empty($r->query['variant'])) {
            $where[] = 'm.variant_id = ?';
            $params[] = (int) $r->query['variant'];
        }
        if (in_array($r->query['reason'] ?? '', Inventory::REASONS, true)) {
            $where[] = 'm.reason = ?';
            $params[] = $r->query['reason'];
        }
        $pg = $this->page($r->query, 50);
        $sql = ' FROM inventory_movements m JOIN product_variants v ON v.id = m.variant_id JOIN products p ON p.id = v.product_id LEFT JOIN users u ON u.id = m.actor_id LEFT JOIN orders o ON o.id = m.order_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->all('SELECT m.*, v.sku, p.title, u.name AS actor, o.order_number' . $sql . ' ORDER BY m.id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], $params);
        return $this->admin('inventory/movements', ['rows' => $rows, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])), 'query' => array_intersect_key($r->query, ['variant' => 1, 'reason' => 1])], 'Inventory movements');
    }
}
