<?php

declare(strict_types=1);

namespace App\Controllers\Seller;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\Currency;
use App\Services\Products;

/**
 * Seller-owned products. Sellers edit content, price, variants and images of
 * their OWN products only; publishing goes through staff review
 * (none → pending → approved | rejected). Changing the listing content of a
 * live product sends it back to review; stock changes do not.
 */
final class SellerProductController extends SellerController
{
    private const RULES = [
        'title' => 'required|string|min:3|max:191',
        'description' => 'required|string|min:20|max:5000',
        'category_id' => 'required|int|min:1',
        'price' => 'required|money',
        'sale_price' => 'money',
        'tags' => 'string|max:300',
    ];

    private function own(array $s, int $id): array
    {
        $p = Database::get()->one('SELECT * FROM products WHERE id = ? AND seller_id = ?', [$id, $s['id']]);
        if (!$p) {
            throw new HttpException(404);
        }
        return $p;
    }

    private function categories(): array
    {
        return Database::get()->all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY sort_order, name");
    }

    public function index(Request $r): Response
    {
        $s = $this->seller();
        $rows = Database::get()->all(
            "SELECT p.*, (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY position, id LIMIT 1) AS image,
                    (SELECT COALESCE(SUM(stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
             FROM products p WHERE p.seller_id = ? AND p.status <> 'archived' ORDER BY p.updated_at DESC",
            [$s['id']]
        );
        return $this->panel('seller/products', ['seller' => $s, 'rows' => $rows], 'My products');
    }

    public function create(Request $r): Response
    {
        $s = $this->seller();
        return $this->panel('seller/product-form', ['seller' => $s, 'p' => null, 'categories' => $this->categories(), 'variants' => [], 'images' => []], 'New product');
    }

    public function edit(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        $db = Database::get();
        return $this->panel('seller/product-form', [
            'seller' => $s, 'p' => $p, 'categories' => $this->categories(),
            'variants' => $db->all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY position, id', [$p['id']]),
            'images' => $db->all('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id', [$p['id']]),
        ], 'Edit: ' . $p['title']);
    }

    private function validated(array $input): array
    {
        $d = Validator::make($input, self::RULES)->validated();
        if ($d['price'] <= 0) {
            throw ValidationException::one('price', 'Price must be greater than zero.');
        }
        if ($d['sale_price'] !== null && $d['sale_price'] >= $d['price']) {
            throw ValidationException::one('sale_price', 'Sale price must be lower than the regular price.');
        }
        if (!Database::get()->value("SELECT id FROM categories WHERE id = ? AND status = 'active'", [$d['category_id']])) {
            throw ValidationException::one('category_id', 'Choose a category.');
        }
        // Plain text from sellers → safe paragraphs (no seller-supplied HTML).
        $paras = array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $d['description']) ?: []));
        $d['description'] = Sanitizer::html(implode('', array_map(static fn ($p) => '<p>' . nl2br(e($p), false) . '</p>', $paras)));
        $d['tags'] = implode(',', array_slice(array_values(array_unique(array_filter(array_map(static fn ($t) => mb_strtolower(trim($t)), explode(',', (string) $d['tags']))))), 0, 20));
        return $d;
    }

    private function uniqueSlug(string $title, int $exceptId = 0): string
    {
        $base = mb_substr(slugify($title), 0, 170);
        $slug = $base;
        $db = Database::get();
        for ($i = 2; $db->value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, $exceptId]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function store(Request $r): Response
    {
        $s = $this->seller();
        $d = $this->validated($r->post);
        $db = Database::get();
        $sku = 'S' . $s['id'] . '-' . strtoupper(bin2hex(random_bytes(3)));
        $id = $db->insert(
            "INSERT INTO products (seller_id, title, slug, sku, description, category_id, tags, currency, price_minor, sale_price_minor, status, review_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'none')",
            [$s['id'], $d['title'], $this->uniqueSlug($d['title']), $sku, $d['description'], $d['category_id'], $d['tags'], Currency::base(), $d['price'], $d['sale_price']]
        );
        Audit::log('seller.product_create', 'product', $id, null, ['seller' => (int) $s['id'], 'title' => $d['title']]);
        return $this->redirect('/seller/products/' . $id, 'Product saved as a draft. Add sizes/colours with stock and at least one photo, then submit it for review.');
    }

    public function update(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        if ($p['status'] === 'archived') {
            throw ValidationException::one('title', 'This product is archived.');
        }
        $d = $this->validated($r->post);
        $changed = $d['title'] !== $p['title'] || $d['description'] !== $p['description'] || (int) $d['category_id'] !== (int) $p['category_id']
            || (int) $d['price'] !== (int) $p['price_minor'] || $d['sale_price'] !== ($p['sale_price_minor'] === null ? null : (int) $p['sale_price_minor']) || $d['tags'] !== $p['tags'];
        // A live product that changes goes back to review (hidden until re-approved).
        $back = $changed && $p['review_status'] === 'approved';
        Database::get()->exec(
            'UPDATE products SET title = ?, slug = ?, description = ?, category_id = ?, tags = ?, price_minor = ?, sale_price_minor = ?'
            . ($back ? ", status = 'draft', review_status = 'pending', review_note = NULL" : '') . ' WHERE id = ? AND seller_id = ?',
            [$d['title'], $d['title'] !== $p['title'] ? $this->uniqueSlug($d['title'], (int) $p['id']) : $p['slug'], $d['description'], $d['category_id'], $d['tags'], $d['price'], $d['sale_price'], $p['id'], $s['id']]
        );
        Audit::log('seller.product_update', 'product', (int) $p['id'], null, ['seller' => (int) $s['id'], 'resubmitted' => $back]);
        return $this->redirect('/seller/products/' . $p['id'], $back ? 'Saved. Your changes were sent for review — the product is hidden until approved.' : 'Product saved.');
    }

    /** Sizes/colours/stock. SKUs are generated for new rows. */
    public function variants(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        $db = Database::get();
        $existing = $db->all('SELECT id, sku FROM product_variants WHERE product_id = ?', [$p['id']]);
        $skus = array_column($existing, 'sku', 'id');
        $rows = [];
        foreach ($r->arr('variants') as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = ctype_digit((string) ($row['id'] ?? '')) ? (int) $row['id'] : null;
            if ($id !== null && !isset($skus[$id])) {
                throw new HttpException(404); // not this product's variant
            }
            $size = trim((string) ($row['size'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));
            if ($id === null && $size === '' && $color === '') {
                continue;
            }
            $rows[$key] = [
                'id' => $id !== null ? (string) $id : '',
                'sku' => $id !== null ? $skus[$id] : $p['sku'] . '-' . strtoupper(bin2hex(random_bytes(2))),
                'size' => $size, 'color' => $color, 'color_hex' => '', 'price' => '', 'sale_price' => '',
                'stock' => trim((string) ($row['stock'] ?? '0')), 'low' => '3', 'active' => '1',
                'remove' => !empty($row['remove']) ? '1' : '',
            ];
        }
        Products::saveVariants((int) $p['id'], $rows, (int) Auth::id(), 'adjustment', true);
        Audit::log('seller.product_variants', 'product', (int) $p['id'], null, ['seller' => (int) $s['id'], 'rows' => count($rows)]);
        return $this->redirect('/seller/products/' . $p['id'] . '#variants', 'Sizes and stock saved.');
    }

    public function uploadImages(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        $db = Database::get();
        $files = $r->fileList('images');
        if (!$files) {
            throw ValidationException::one('images', 'Choose one or more photos to upload.');
        }
        $count = (int) $db->value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$p['id']]);
        if ($count + count($files) > 10) {
            throw ValidationException::one('images', 'A product can have up to 10 photos.');
        }
        $pos = (int) $db->value('SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id = ?', [$p['id']]);
        $ok = 0;
        $errors = [];
        foreach ($files as $f) {
            try {
                $img = Uploads::storeImage($f, 'products');
                $db->insert(
                    'INSERT INTO product_images (product_id, path, alt, width, height, position) VALUES (?, ?, ?, ?, ?, ?)',
                    [$p['id'], $img['path'], mb_substr($p['title'], 0, 191), $img['width'], $img['height'], $pos++]
                );
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = mb_substr((string) $f['name'], 0, 60) . ': ' . $e->getMessage();
            }
        }
        Audit::log('seller.product_images', 'product', (int) $p['id'], null, ['uploaded' => $ok]);
        return $this->redirect('/seller/products/' . $p['id'] . '#images', $ok ? "{$ok} photo(s) uploaded." : null, $errors ? implode(' ', $errors) : null);
    }

    public function deleteImage(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        $db = Database::get();
        $img = $db->one('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int) $r->params['image'], $p['id']]);
        if (!$img) {
            throw new HttpException(404);
        }
        $db->exec('DELETE FROM product_images WHERE id = ?', [$img['id']]);
        if (!$db->value('SELECT 1 FROM order_items WHERE image_path = ? LIMIT 1', [$img['path']])) {
            Uploads::deleteImage($img['path']);
        }
        return $this->redirect('/seller/products/' . $p['id'] . '#images', 'Photo removed.');
    }

    public function submit(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        $db = Database::get();
        if (!in_array($p['review_status'], ['none', 'rejected'], true) || $p['status'] === 'archived') {
            throw ValidationException::one('submit', 'This product is already ' . ($p['review_status'] === 'pending' ? 'in review' : 'approved') . '.');
        }
        if (!(int) $db->value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$p['id']])) {
            throw ValidationException::one('submit', 'Add at least one photo before submitting.');
        }
        if (!(int) $db->value('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_active = 1', [$p['id']])) {
            throw ValidationException::one('submit', 'Add at least one size/colour before submitting.');
        }
        $db->exec("UPDATE products SET review_status = 'pending', review_note = NULL WHERE id = ? AND seller_id = ?", [$p['id'], $s['id']]);
        Audit::log('seller.product_submit', 'product', (int) $p['id'], null, ['seller' => (int) $s['id']]);
        return $this->redirect('/seller/products/' . $p['id'], 'Submitted for review. We will email you when it is live.');
    }

    public function unpublish(Request $r): Response
    {
        $s = $this->seller();
        $p = $this->own($s, (int) $r->params['id']);
        Database::get()->exec("UPDATE products SET status = 'archived' WHERE id = ? AND seller_id = ?", [$p['id'], $s['id']]);
        Audit::log('seller.product_archive', 'product', (int) $p['id'], null, ['seller' => (int) $s['id']]);
        return $this->redirect('/seller/products', 'Product removed from the store.');
    }
}
