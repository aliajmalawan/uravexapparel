<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;

final class CategoryController extends AdminController
{
    /** Icons available for the storefront category rail. */
    public const ICONS = ['shirt', 'tee', 'trouser', 'jacket', 'knit', 'dress', 'shoe', 'bag', 'cap', 'watch', 'hanger', 'sparkle', 'tag', 'box'];

    public function index(Request $r): Response
    {
        $db = Database::get();
        $rows = $db->all('SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS products FROM categories c ORDER BY c.sort_order, c.name');

        // Tree: main categories with their subcategories; product totals include subcategories.
        $tree = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $tree[(int) $row['id']] = $row + ['children' => [], 'total' => (int) $row['products']];
            }
        }
        $orphans = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                continue;
            }
            if (isset($tree[(int) $row['parent_id']])) {
                $tree[(int) $row['parent_id']]['children'][] = $row;
                $tree[(int) $row['parent_id']]['total'] += (int) $row['products'];
            } else {
                $orphans[] = $row + ['children' => [], 'total' => (int) $row['products']];
            }
        }
        $tree = array_merge(array_values($tree), $orphans);

        $status = in_array($r->query['status'] ?? '', ['active', 'archived'], true) ? $r->query['status'] : 'all';
        $visible = $status === 'all' ? $tree : array_values(array_filter(array_map(static function (array $c) use ($status): ?array {
            $c['children'] = array_values(array_filter($c['children'], static fn ($ch) => $ch['status'] === $status));
            return $c['status'] === $status || $c['children'] ? $c : null;
        }, $tree)));

        // Move buttons: where each category sits among its siblings (whole tree, ignoring the status filter).
        $places = [];
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['parent_id'] === null ? 'top' : (int) $row['parent_id']][] = (int) $row['id'];
        }
        foreach ($groups as $ids) {
            foreach ($ids as $i => $id) {
                $places[$id] = ['first' => $i === 0, 'last' => $i === count($ids) - 1];
            }
        }
        $focus = (int) ($r->query['focus'] ?? 0);

        $edit = isset($r->query['edit']) ? $db->one('SELECT * FROM categories WHERE id = ?', [(int) $r->query['edit']]) : null;
        $newParent = !$edit && isset($r->query['parent']) ? $db->one('SELECT id, name FROM categories WHERE id = ? AND parent_id IS NULL', [(int) $r->query['parent']]) : null;
        $editChildren = $edit ? (int) $db->value('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$edit['id']]) : 0;

        return $this->admin('catalog/categories', [
            'tree' => $visible,
            'status' => $status,
            'counts' => [
                'main' => count(array_filter($rows, static fn ($x) => $x['parent_id'] === null)),
                'sub' => count(array_filter($rows, static fn ($x) => $x['parent_id'] !== null)),
                'active' => count(array_filter($rows, static fn ($x) => $x['status'] === 'active')),
                'archived' => count(array_filter($rows, static fn ($x) => $x['status'] === 'archived')),
                'products' => array_sum(array_map(static fn ($x) => (int) $x['products'], $rows)),
                'all' => count($rows),
            ],
            'parents' => array_values(array_filter($rows, static fn ($x) => $x['parent_id'] === null && (int) $x['id'] !== (int) ($edit['id'] ?? 0))),
            'edit' => $edit,
            'editChildren' => $editChildren,
            'editProducts' => $edit ? (int) $db->value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$edit['id']]) : 0,
            'newParent' => $newParent,
            'places' => $places,
            'focus' => $focus,
        ], 'Categories');
    }

    public function save(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int', 'name' => 'required|string|min:2|max:120', 'slug' => 'string|max:140|slug', 'description' => 'string|max:2000',
            'parent_id' => 'int', 'sort_order' => 'int|min:0|max:9999', 'seo_title' => 'string|max:191', 'seo_description' => 'string|max:320',
            'status' => 'required|in:active,archived',
            'icon' => 'in:' . implode(',', self::ICONS),
        ])->validated();
        $d['slug'] = $d['slug'] ?: slugify($d['name']);
        $db = Database::get();
        if ($db->value('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$d['slug'], $d['id'] ?? 0])) {
            throw ValidationException::one('slug', 'Another category uses this slug.');
        }
        if ($d['parent_id'] && $d['parent_id'] === $d['id']) {
            throw ValidationException::one('parent_id', 'A category cannot be its own parent.');
        }
        if ($d['parent_id']) {
            // Two levels only: main category › subcategory.
            $parent = $db->one('SELECT id, parent_id FROM categories WHERE id = ?', [$d['parent_id']]);
            if (!$parent) {
                throw ValidationException::one('parent_id', 'Choose a main category from the list.');
            }
            if ($parent['parent_id'] !== null) {
                throw ValidationException::one('parent_id', 'Subcategories can only sit under a main category.');
            }
            if ($d['id'] && (int) $db->value('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$d['id']]) > 0) {
                throw ValidationException::one('parent_id', 'This category has its own subcategories, so it must stay a main category.');
            }
        }
        $image = null;
        if ($f = $r->file('image')) {
            $image = Uploads::storeImage($f, 'categories')['path'];
        }
        $vals = [$d['name'], $d['slug'], $d['description'], $d['parent_id'] ?: null, $d['sort_order'] ?? 0, $d['seo_title'], $d['seo_description'], $d['status'], $d['icon']];
        if ($d['id']) {
            $before = $db->one('SELECT * FROM categories WHERE id = ?', [$d['id']]);
            if (!$before) {
                throw new HttpException(404);
            }
            $db->exec('UPDATE categories SET name=?, slug=?, description=?, parent_id=?, sort_order=?, seo_title=?, seo_description=?, status=?, icon=?, image_url = COALESCE(?, image_url) WHERE id = ?', [...$vals, $image, $d['id']]);
            Audit::log('category.update', 'category', $d['id'], ['name' => $before['name'], 'status' => $before['status']], ['name' => $d['name'], 'status' => $d['status']]);
        } else {
            $id = $db->insert('INSERT INTO categories (name, slug, description, parent_id, sort_order, seo_title, seo_description, status, icon, image_url) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$vals, $image]);
            Audit::log('category.create', 'category', $id, null, ['name' => $d['name']]);
        }
        return $this->redirect('/admin/categories', 'Category saved.');
    }

    public function destroy(Request $r): Response
    {
        $db = Database::get();
        $c = $db->one('SELECT id, name FROM categories WHERE id = ?', [(int) $r->params['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        $products = (int) $db->value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$c['id']]);
        if ($products > 0) {
            throw ValidationException::one('category', "Move or delete the {$products} product(s) in “{$c['name']}” first, or archive the category instead.");
        }
        $children = (int) $db->value('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$c['id']]);
        if ($children > 0) {
            throw ValidationException::one('category', "“{$c['name']}” has {$children} subcategor" . ($children === 1 ? 'y' : 'ies') . '. Delete or move them first, or archive the category instead.');
        }
        $db->exec('UPDATE categories SET parent_id = NULL WHERE parent_id = ?', [$c['id']]);
        $db->exec('DELETE FROM categories WHERE id = ?', [$c['id']]);
        Audit::log('category.delete', 'category', $c['id'], ['name' => $c['name']], null);
        return $this->redirect('/admin/categories', 'Category deleted.');
    }

    /**
     * Tree editing from the list: up / down within the same level, right = becomes a
     * subcategory of the main category above it, left = becomes a main category
     * (placed right after its former parent). Two levels only.
     */
    public function move(Request $r): Response
    {
        $db = Database::get();
        $c = $db->one('SELECT * FROM categories WHERE id = ?', [(int) $r->params['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        $dir = $r->str('direction');
        $before = ['parent_id' => $c['parent_id'], 'sort_order' => $c['sort_order']];

        $message = $db->transaction(static function (Database $db) use ($c, $dir): string {
            $siblings = static fn (?int $parentId): array => array_map('intval', $parentId === null
                ? $db->column('SELECT id FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name, id FOR UPDATE')
                : $db->column('SELECT id FROM categories WHERE parent_id = ? ORDER BY sort_order, name, id FOR UPDATE', [$parentId]));
            $renumber = static function (array $ids) use ($db): void {
                foreach (array_values($ids) as $i => $id) {
                    $db->exec('UPDATE categories SET sort_order = ? WHERE id = ?', [$i, $id]);
                }
            };
            $id = (int) $c['id'];
            $parent = $c['parent_id'] === null ? null : (int) $c['parent_id'];
            $list = $siblings($parent);
            $pos = (int) array_search($id, $list, true);
            $name = '“' . $c['name'] . '”';

            switch ($dir) {
                case 'up':
                case 'down':
                    $to = $dir === 'up' ? $pos - 1 : $pos + 1;
                    if ($to < 0 || $to >= count($list)) {
                        throw ValidationException::one('category', "{$name} is already at the " . ($dir === 'up' ? 'top' : 'bottom') . '.');
                    }
                    [$list[$pos], $list[$to]] = [$list[$to], $list[$pos]];
                    $renumber($list);
                    return "{$name} moved {$dir}.";

                case 'right':
                    if ($parent !== null) {
                        throw ValidationException::one('category', "{$name} is already a subcategory.");
                    }
                    if ($pos === 0) {
                        throw ValidationException::one('category', "There is no main category above {$name} to put it under.");
                    }
                    if ((int) $db->value('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id]) > 0) {
                        throw ValidationException::one('category', "{$name} has its own subcategories. Move them out first — only main category › subcategory is allowed.");
                    }
                    $newParent = $list[$pos - 1];
                    $db->exec('UPDATE categories SET parent_id = ?, sort_order = ? WHERE id = ?', [$newParent, count($siblings($newParent)), $id]);
                    unset($list[$pos]);
                    $renumber($list);
                    return "{$name} is now a subcategory of “" . $db->value('SELECT name FROM categories WHERE id = ?', [$newParent]) . '”.';

                case 'left':
                    if ($parent === null) {
                        throw ValidationException::one('category', "{$name} is already a main category.");
                    }
                    unset($list[$pos]);
                    $renumber($list);
                    $mains = $siblings(null);
                    array_splice($mains, (int) array_search($parent, $mains, true) + 1, 0, [$id]);
                    $db->exec('UPDATE categories SET parent_id = NULL WHERE id = ?', [$id]);
                    $renumber($mains);
                    return "{$name} is now a main category.";
            }
            throw ValidationException::one('category', 'Unknown move.');
        });

        $after = $db->one('SELECT parent_id, sort_order FROM categories WHERE id = ?', [$c['id']]);
        Audit::log('category.move', 'category', (int) $c['id'], $before, $after + ['direction' => $dir]);
        return $this->redirect('/admin/categories?focus=' . (int) $c['id'] . '#cat-' . (int) $c['id'], $message);
    }

    public function archive(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $n = Database::get()->exec("UPDATE categories SET status = IF(status = 'active', 'archived', 'active') WHERE id = ?", [$id]);
        if (!$n) {
            throw new HttpException(404);
        }
        Audit::log('category.toggle_archive', 'category', $id);
        return $this->redirect('/admin/categories', 'Category updated.');
    }
}
