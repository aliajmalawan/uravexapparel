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
        $edit = isset($r->query['edit']) ? $db->one('SELECT * FROM categories WHERE id = ?', [(int) $r->query['edit']]) : null;
        return $this->admin('catalog/categories', [
            'rows' => $db->all('SELECT c.*, p.name AS parent_name, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS products FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY c.status, c.sort_order, c.name'),
            'edit' => $edit,
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
        $db->exec('UPDATE categories SET parent_id = NULL WHERE parent_id = ?', [$c['id']]);
        $db->exec('DELETE FROM categories WHERE id = ?', [$c['id']]);
        Audit::log('category.delete', 'category', $c['id'], ['name' => $c['name']], null);
        return $this->redirect('/admin/categories', 'Category deleted.');
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
