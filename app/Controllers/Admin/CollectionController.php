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

final class CollectionController extends AdminController
{
    public function index(Request $r): Response
    {
        return $this->admin('catalog/collections', [
            'rows' => Database::get()->all('SELECT c.*, (SELECT COUNT(*) FROM product_collections WHERE collection_id = c.id) AS products FROM collections c ORDER BY c.status, c.sort_order, c.name'),
            'edit' => null,
        ], 'Collections');
    }

    public function edit(Request $r): Response
    {
        $db = Database::get();
        $c = $db->one('SELECT * FROM collections WHERE id = ?', [(int) $r->params['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        return $this->admin('catalog/collection', [
            'c' => $c,
            'members' => $db->all('SELECT p.id, p.title, p.sku, p.status, pc.sort_order FROM product_collections pc JOIN products p ON p.id = pc.product_id WHERE pc.collection_id = ? ORDER BY pc.sort_order, p.title', [$c['id']]),
            'all' => $db->all("SELECT id, title, sku FROM products WHERE status <> 'archived' AND id NOT IN (SELECT product_id FROM product_collections WHERE collection_id = ?) ORDER BY title", [$c['id']]),
        ], 'Collection: ' . $c['name']);
    }

    public function save(Request $r): Response
    {
        $d = Validator::make($r->post, [
            'id' => 'int', 'name' => 'required|string|min:2|max:120', 'slug' => 'string|max:140|slug', 'description' => 'string|max:2000',
            'sort_order' => 'int|min:0|max:9999', 'home_order' => 'int|min:0|max:9999', 'featured_on_home' => 'bool',
            'status' => 'required|in:active,archived', 'seo_title' => 'string|max:191', 'seo_description' => 'string|max:320',
        ])->validated();
        $d['slug'] = $d['slug'] ?: slugify($d['name']);
        $db = Database::get();
        if ($db->value('SELECT id FROM collections WHERE slug = ? AND id <> ?', [$d['slug'], $d['id'] ?? 0])) {
            throw ValidationException::one('slug', 'Another collection uses this slug.');
        }
        $image = ($f = $r->file('image')) ? Uploads::storeImage($f, 'collections')['path'] : null;
        $vals = [$d['name'], $d['slug'], $d['description'], $d['sort_order'] ?? 0, $d['home_order'] ?? 0, $d['featured_on_home'], $d['status'], $d['seo_title'], $d['seo_description']];
        if ($d['id']) {
            if (!$db->exec('UPDATE collections SET name=?, slug=?, description=?, sort_order=?, home_order=?, featured_on_home=?, status=?, seo_title=?, seo_description=?, image_url = COALESCE(?, image_url) WHERE id = ?', [...$vals, $image, $d['id']])
                && !$db->value('SELECT id FROM collections WHERE id = ?', [$d['id']])) {
                throw new HttpException(404);
            }
            $id = $d['id'];
            Audit::log('collection.update', 'collection', $id, null, ['name' => $d['name'], 'featured_on_home' => $d['featured_on_home'], 'status' => $d['status']]);
        } else {
            $id = $db->insert('INSERT INTO collections (name, slug, description, sort_order, home_order, featured_on_home, status, seo_title, seo_description, image_url) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$vals, $image]);
            Audit::log('collection.create', 'collection', $id, null, ['name' => $d['name']]);
        }
        return $this->redirect('/admin/collections/' . $id, 'Collection saved.');
    }

    /** Adds/removes/reorders products in a collection. */
    public function products(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $db = Database::get();
        if (!$db->value('SELECT id FROM collections WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        $db->transaction(static function (Database $db) use ($r, $id): void {
            foreach ($r->arr('order') as $pid => $pos) {
                $db->exec('UPDATE product_collections SET sort_order = ? WHERE collection_id = ? AND product_id = ?', [(int) $pos, $id, (int) $pid]);
            }
            foreach ($r->arr('remove') as $pid) {
                $db->exec('DELETE FROM product_collections WHERE collection_id = ? AND product_id = ?', [$id, (int) $pid]);
            }
            foreach ($r->arr('add') as $pid) {
                $db->exec('INSERT IGNORE INTO product_collections (product_id, collection_id, sort_order) SELECT id, ?, 999 FROM products WHERE id = ?', [$id, (int) $pid]);
            }
        });
        Audit::log('collection.products', 'collection', $id, null, ['added' => count($r->arr('add')), 'removed' => count($r->arr('remove'))]);
        return $this->redirect('/admin/collections/' . $id, 'Collection products updated.');
    }

    /** Deletes the collection only; its products are untouched. */
    public function destroy(Request $r): Response
    {
        $db = Database::get();
        $c = $db->one('SELECT id, name, image_url FROM collections WHERE id = ?', [(int) $r->params['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        $db->exec('DELETE FROM collections WHERE id = ?', [$c['id']]);
        $db->exec("DELETE FROM coupon_targets WHERE target_type = 'collection' AND target_id = ?", [$c['id']]);
        if ($c['image_url']) {
            Uploads::deleteImage($c['image_url']);
        }
        Audit::log('collection.delete', 'collection', $c['id'], ['name' => $c['name']], null);
        return $this->redirect('/admin/collections', 'Collection deleted. Its products were not changed.');
    }

    public function archive(Request $r): Response
    {
        $id = (int) $r->params['id'];
        if (!Database::get()->exec("UPDATE collections SET status = IF(status = 'active', 'archived', 'active') WHERE id = ?", [$id])) {
            throw new HttpException(404);
        }
        Audit::log('collection.toggle_archive', 'collection', $id);
        return $this->redirect('/admin/collections', 'Collection updated.');
    }
}
