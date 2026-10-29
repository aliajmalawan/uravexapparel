<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Controllers\Store\ProductController as StoreProduct;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Services\Audit;
use App\Services\Pricing;
use App\Services\Products;

final class ProductController extends AdminController
{
    public function index(Request $r): Response
    {
        $db = Database::get();
        $q = trim((string) ($r->query['q'] ?? ''));
        $status = (string) ($r->query['status'] ?? '');
        $cat = (int) ($r->query['category'] ?? 0);
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.title LIKE ? OR p.sku LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.sku LIKE ?))';
            array_push($params, $like, $like, $like);
        }
        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        } else {
            $where[] = "p.status <> 'archived'";
        }
        if ($cat > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $cat;
        }
        $pg = $this->page($r->query, 25);
        $sql = ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $rows = $db->all(
            'SELECT p.*, c.name AS category_name, (SELECT shop_name FROM sellers sl WHERE sl.id = p.seller_id) AS seller_name,
               (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY position, id LIMIT 1) AS image,
               (SELECT COALESCE(SUM(stock),0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock,
               (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS variant_count'
            . $sql . ' ORDER BY p.updated_at DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'],
            $params
        );
        return $this->admin('products/index', [
            'rows' => $rows, 'total' => $total, 'page' => $pg['page'], 'pages' => max(1, (int) ceil($total / $pg['per'])),
            'q' => $q, 'status' => $status, 'category' => $cat,
            'categories' => $db->all('SELECT id, name FROM categories ORDER BY name'),
        ], 'Products');
    }

    private function formData(?array $product): array
    {
        $db = Database::get();
        return [
            'p' => $product,
            'categories' => $db->all("SELECT id, name FROM categories WHERE status = 'active' OR id = ? ORDER BY sort_order, name", [$product['category_id'] ?? 0]),
            'collections' => $db->all('SELECT id, name FROM collections ORDER BY sort_order, name'),
            'selectedCollections' => $product ? array_map('intval', $db->column('SELECT collection_id FROM product_collections WHERE product_id = ?', [$product['id']])) : [],
            'variants' => $product ? $db->all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY position, id', [$product['id']]) : [],
            'images' => $product ? $db->all('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id', [$product['id']]) : [],
            'canManage' => Auth::can('products.manage'),
        ];
    }

    public function create(Request $r): Response
    {
        return $this->admin('products/form', $this->formData(null), 'New product');
    }

    public function edit(Request $r): Response
    {
        $p = Database::get()->one('SELECT * FROM products WHERE id = ?', [(int) $r->params['id']]);
        if (!$p) {
            throw new HttpException(404);
        }
        return $this->admin('products/form', $this->formData($p), 'Edit: ' . $p['title']);
    }

    public function store(Request $r): Response
    {
        $d = Products::validate($r->post);
        $id = Products::save($d, null, $r->arr('collections'));
        Audit::log('product.create', 'product', $id, null, ['title' => $d['title'], 'sku' => $d['sku'], 'price_minor' => $d['price'], 'status' => $d['status']]);
        return $this->redirect('/admin/products/' . $id . '/edit', 'Product created. Now add variants and images.');
    }

    public function update(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $before = Database::get()->one('SELECT title, slug, sku, price_minor, sale_price_minor, cost_minor, status, featured, category_id FROM products WHERE id = ?', [$id]);
        if (!$before) {
            throw new HttpException(404);
        }
        $d = Products::validate($r->post);
        Products::save($d, $id, $r->arr('collections'));
        $after = ['title' => $d['title'], 'slug' => $d['slug'], 'sku' => $d['sku'], 'price_minor' => $d['price'], 'sale_price_minor' => $d['sale_price'], 'cost_minor' => $d['cost'], 'status' => $d['status'], 'featured' => (int) $d['featured'], 'category_id' => $d['category_id']];
        [$b, $a] = Audit::diff($before, $after);
        if ($a) {
            Audit::log('product.update', 'product', $id, $b, $a);
        }
        return $this->redirect('/admin/products/' . $id . '/edit', 'Product saved.');
    }

    public function status(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $status = $r->str('status');
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            throw ValidationException::one('status', 'Invalid status.');
        }
        $db = Database::get();
        $p = $db->one('SELECT id, status FROM products WHERE id = ?', [$id]);
        if (!$p) {
            throw new HttpException(404);
        }
        if ($status === 'active' && !(int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_active = 1', [$id])) {
            throw ValidationException::one('status', 'Add at least one active variant before publishing.');
        }
        $db->exec("UPDATE products SET status = ?, published_at = IF(? = 'active' AND published_at IS NULL, UTC_TIMESTAMP(), published_at) WHERE id = ?", [$status, $status, $id]);
        Audit::log('product.status', 'product', $id, ['status' => $p['status']], ['status' => $status]);
        return $this->back('/admin/products');
    }

    public function feature(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $n = Database::get()->exec('UPDATE products SET featured = 1 - featured WHERE id = ?', [$id]);
        if (!$n) {
            throw new HttpException(404);
        }
        Audit::log('product.feature_toggle', 'product', $id);
        return $this->back('/admin/products');
    }

    public function destroy(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $p = Database::get()->one('SELECT id, title, sku FROM products WHERE id = ?', [$id]);
        if (!$p) {
            throw new HttpException(404);
        }
        $result = Products::deleteOrArchive($id);
        Audit::log('product.' . ($result === 'deleted' ? 'delete' : 'archive'), 'product', $id, $p, null);
        return $this->redirect('/admin/products', $result === 'deleted' ? 'Product deleted.' : 'This product has order history, so it was archived instead of deleted.');
    }

    public function saveVariants(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $rows = $r->arr('variants');
        Products::saveVariants($id, $rows, $this->actorId(), 'adjustment', Auth::can('inventory.adjust'));
        Audit::log('product.variants', 'product', $id, null, ['rows' => count($rows)]);
        return $this->redirect('/admin/products/' . $id . '/edit#variants', 'Variants saved. Stock changes were recorded in the inventory log.');
    }

    public function uploadImages(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $db = Database::get();
        $title = $db->value('SELECT title FROM products WHERE id = ?', [$id]);
        if ($title === null) {
            throw new HttpException(404);
        }
        $files = $r->fileList('images');
        if (!$files) {
            throw ValidationException::one('images', 'Choose one or more images to upload.');
        }
        if (count($files) > 12) {
            throw ValidationException::one('images', 'Upload at most 12 images at a time.');
        }
        $color = mb_substr($r->str('color'), 0, 60) ?: null;
        $pos = (int) $db->value('SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id = ?', [$id]);
        $ok = 0;
        $errors = [];
        foreach ($files as $f) {
            try {
                $img = Uploads::storeImage($f, 'products');
                $db->insert(
                    'INSERT INTO product_images (product_id, path, alt, color, width, height, position) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $img['path'], mb_substr($r->str('alt') ?: (string) $title, 0, 191), $color, $img['width'], $img['height'], $pos++]
                );
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = mb_substr((string) $f['name'], 0, 60) . ': ' . $e->getMessage();
            }
        }
        Audit::log('product.images_upload', 'product', $id, null, ['uploaded' => $ok, 'rejected' => count($errors)]);
        $msg = $ok ? "{$ok} image(s) uploaded." : null;
        return $this->redirect('/admin/products/' . $id . '/edit#images', $msg, $errors ? implode(' ', $errors) : null);
    }

    /** Saves positions, alt text, colour tags; "featured" moves an image to position 0. */
    public function orderImages(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $db = Database::get();
        $images = $db->all('SELECT id FROM product_images WHERE product_id = ? ORDER BY position, id', [$id]);
        $positions = $r->arr('position');
        $alts = $r->arr('alt');
        $colors = $r->arr('img_color');
        $featured = $r->int('featured');
        $ordered = [];
        foreach ($images as $i => $img) {
            $pid = (int) $img['id'];
            $ordered[$pid] = $pid === $featured ? -1 : (int) ($positions[$pid] ?? $i);
        }
        asort($ordered);
        $db->transaction(static function (Database $db) use ($ordered, $alts, $colors, $id): void {
            $n = 0;
            foreach (array_keys($ordered) as $imgId) {
                $db->exec(
                    'UPDATE product_images SET position = ?, alt = ?, color = ? WHERE id = ? AND product_id = ?',
                    [$n++, mb_substr(trim((string) ($alts[$imgId] ?? '')), 0, 191), mb_substr(trim((string) ($colors[$imgId] ?? '')), 0, 60) ?: null, $imgId, $id]
                );
            }
        });
        return $this->redirect('/admin/products/' . $id . '/edit#images', 'Images updated.');
    }

    public function deleteImage(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $db = Database::get();
        $img = $db->one('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int) $r->params['image'], $id]);
        if (!$img) {
            throw new HttpException(404);
        }
        $db->exec('DELETE FROM product_images WHERE id = ?', [$img['id']]);
        // Order items keep their own image snapshot path; only delete files no longer referenced.
        if (!$db->value('SELECT 1 FROM order_items WHERE image_path = ? LIMIT 1', [$img['path']])) {
            Uploads::deleteImage($img['path']);
        }
        Audit::log('product.image_delete', 'product', $id, ['image' => $img['path']], null);
        return $this->redirect('/admin/products/' . $id . '/edit#images', 'Image removed.');
    }

    public function preview(Request $r): Response
    {
        $db = Database::get();
        $p = $db->one('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int) $r->params['id']]);
        if (!$p) {
            throw new HttpException(404);
        }
        $p['images'] = $db->all('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id', [$p['id']]);
        $p['variants'] = $db->all('SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY position, id', [$p['id']]);
        $p['collections'] = [];
        foreach ($p['variants'] as &$v) {
            $v['pricing'] = Pricing::unitPrice($p, $v);
        }
        unset($v);
        $p['pricing'] = Pricing::unitPrice($p);
        return (new StoreProduct())->render($p, true);
    }

    public function export(Request $r): Response
    {
        $rows = Database::get()->all(
            "SELECT p.sku AS product_sku, p.title, p.slug, c.slug AS category, p.status, p.price_minor, p.sale_price_minor, p.tags,
                    v.sku AS variant_sku, v.size, v.color, v.color_hex, v.price_minor AS v_price, v.stock, v.low_stock_threshold
             FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN product_variants v ON v.product_id = p.id
             WHERE p.status <> 'archived' ORDER BY p.id, v.position"
        );
        Audit::log('export.products', 'export', null, null, ['rows' => count($rows)]);
        return $this->csv('products-' . gmdate('Ymd') . '.csv', ImportController::COLUMNS, array_map(static fn ($x) => [
            $x['product_sku'], $x['title'], $x['slug'], $x['category'], $x['status'], Money::decimal((int) $x['price_minor']),
            $x['sale_price_minor'] !== null ? Money::decimal((int) $x['sale_price_minor']) : '', $x['tags'],
            $x['variant_sku'], $x['size'], $x['color'], $x['color_hex'], $x['v_price'] !== null ? Money::decimal((int) $x['v_price']) : '', $x['stock'], $x['low_stock_threshold'], '',
        ], $rows));
    }
}
