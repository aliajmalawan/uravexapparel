<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\Sanitizer;
use App\Core\Uploads;
use App\Core\ValidationException;
use App\Core\Validator;

/** Product write operations shared by the admin form and CSV import. */
final class Products
{
    public const RULES = [
        'title' => 'required|string|min:2|max:191',
        'slug' => 'string|max:191|slug',
        'sku' => 'required|string|max:64|regex:/^[A-Za-z0-9._-]+$/',
        'description' => 'string|max:60000',
        'care_details' => 'string|max:5000',
        'shipping_returns' => 'string|max:5000',
        'category_id' => 'int|min:1',
        'tags' => 'string|max:500',
        'price' => 'required|money',
        'sale_price' => 'money',
        'cost' => 'money',
        'status' => 'required|in:draft,active,archived',
        'featured' => 'bool',
        'seo_title' => 'string|max:191',
        'seo_description' => 'string|max:320',
    ];

    public static function validate(array $input): array
    {
        $d = Validator::make($input, self::RULES)->validated();
        $d['slug'] = $d['slug'] ?: slugify($d['title']);
        $d['sku'] = strtoupper($d['sku']);
        if ($d['price'] <= 0) {
            throw ValidationException::one('price', 'Price must be greater than zero.');
        }
        if ($d['sale_price'] !== null && $d['sale_price'] >= $d['price']) {
            throw ValidationException::one('sale_price', 'Sale price must be lower than the regular price.');
        }
        $d['tags'] = implode(',', array_slice(array_values(array_unique(array_filter(array_map(static fn ($t) => mb_strtolower(trim($t)), explode(',', (string) $d['tags']))))), 0, 30));
        $d['description'] = Sanitizer::html((string) $d['description']);
        return $d;
    }

    /** Creates or updates a product. Returns the id. */
    public static function save(array $d, ?int $id, array $collectionIds = []): int
    {
        $db = Database::get();
        if ($db->value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$d['slug'], $id ?? 0])) {
            throw ValidationException::one('slug', 'Another product already uses this URL slug.');
        }
        if ($db->value('SELECT id FROM products WHERE sku = ? AND id <> ?', [$d['sku'], $id ?? 0])) {
            throw ValidationException::one('sku', 'Another product already uses this SKU.');
        }
        if ($d['category_id'] && !$db->value('SELECT id FROM categories WHERE id = ?', [$d['category_id']])) {
            throw ValidationException::one('category_id', 'Choose a valid category.');
        }
        $cols = [$d['title'], $d['slug'], $d['sku'], $d['description'], $d['care_details'], $d['shipping_returns'], $d['category_id'], $d['tags'],
            $d['price'], $d['sale_price'], $d['cost'], $d['status'], $d['featured'], $d['seo_title'], $d['seo_description']];

        return $db->transaction(static function (Database $db) use ($cols, $d, $id, $collectionIds): int {
            if ($id) {
                $db->exec(
                    'UPDATE products SET title=?, slug=?, sku=?, description=?, care_details=?, shipping_returns=?, category_id=?, tags=?, price_minor=?, sale_price_minor=?, cost_minor=?, status=?, featured=?, seo_title=?, seo_description=?,
                       published_at = IF(? = \'active\' AND published_at IS NULL, UTC_TIMESTAMP(), published_at) WHERE id = ?',
                    [...$cols, $d['status'], $id]
                );
            } else {
                $id = $db->insert(
                    'INSERT INTO products (title, slug, sku, description, care_details, shipping_returns, category_id, tags, price_minor, sale_price_minor, cost_minor, status, featured, seo_title, seo_description, published_at, currency)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, IF(? = \'active\', UTC_TIMESTAMP(), NULL), ?)',
                    [...$cols, $d['status'], Currency::base()]
                );
            }
            $db->exec('DELETE FROM product_collections WHERE product_id = ?', [$id]);
            foreach (array_unique(array_map('intval', $collectionIds)) as $cid) {
                if ($cid > 0) {
                    $db->exec('INSERT IGNORE INTO product_collections (product_id, collection_id) SELECT ?, id FROM collections WHERE id = ?', [$id, $cid]);
                }
            }
            return $id;
        });
    }

    /**
     * Saves variant rows. Stock changes are recorded as inventory movements
     * (never a silent overwrite). Variants with order history are deactivated
     * rather than deleted.
     *
     * @param list<array{id?:string,sku:string,size:string,color:string,color_hex:string,price:string,sale_price:string,stock:string,low:string,active?:string,remove?:string}> $rows
     */
    public static function saveVariants(int $productId, array $rows, int $actorId, string $reason = 'adjustment', bool $allowStockEdit = true): array
    {
        $db = Database::get();
        $product = $db->one('SELECT * FROM products WHERE id = ?', [$productId]);
        if (!$product) {
            throw ValidationException::one('variants', 'Product not found.');
        }
        $errors = [];
        $clean = [];
        $seen = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            $size = mb_substr(trim((string) ($row['size'] ?? '')), 0, 40);
            $color = mb_substr(trim((string) ($row['color'] ?? '')), 0, 60);
            $isNew = !ctype_digit((string) ($row['id'] ?? ''));
            if ($isNew && $sku === '' && $size === '' && $color === '') {
                continue; // blank new row
            }
            $label = $sku !== '' ? $sku : "row {$key}";
            if (!preg_match('/^[A-Z0-9._-]{1,64}$/', $sku)) {
                $errors[] = "{$label}: SKU is required (letters, numbers, . _ -).";
                continue;
            }
            $combo = mb_strtolower($size . '|' . $color);
            if (isset($seen['sku:' . $sku]) || isset($seen['opt:' . $combo])) {
                $errors[] = "{$label}: duplicate SKU or size/colour combination.";
                continue;
            }
            $seen['sku:' . $sku] = $seen['opt:' . $combo] = true;
            $price = trim((string) ($row['price'] ?? '')) === '' ? null : Money::parse((string) $row['price']);
            $sale = trim((string) ($row['sale_price'] ?? '')) === '' ? null : Money::parse((string) $row['sale_price']);
            $stock = trim((string) ($row['stock'] ?? '0'));
            $low = trim((string) ($row['low'] ?? '5'));
            $hex = trim((string) ($row['color_hex'] ?? ''));
            if (($row['price'] ?? '') !== '' && $price === null || ($row['sale_price'] ?? '') !== '' && $sale === null) {
                $errors[] = "{$label}: invalid price.";
                continue;
            }
            $effectiveRegular = $price ?? (int) $product['price_minor'];
            if ($sale !== null && $sale >= $effectiveRegular) {
                $errors[] = "{$label}: sale price must be below the regular price.";
                continue;
            }
            if (!preg_match('/^\d{1,6}$/', $stock) || !preg_match('/^\d{1,5}$/', $low)) {
                $errors[] = "{$label}: stock and low-stock threshold must be whole numbers.";
                continue;
            }
            if ($hex !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                $errors[] = "{$label}: colour must be a hex value like #0B0B0B.";
                continue;
            }
            $clean[] = [
                'id' => $isNew ? null : (int) $row['id'], 'sku' => $sku, 'size' => $size, 'color' => $color, 'hex' => $hex ?: null,
                'price' => $price, 'sale' => $sale, 'stock' => (int) $stock, 'low' => (int) $low,
                'active' => !empty($row['active']) || $isNew, 'remove' => !empty($row['remove']),
            ];
        }
        if ($errors) {
            throw new ValidationException(['variants' => implode(' ', $errors)], 'Some variants could not be saved: ' . implode(' ', $errors));
        }

        $db->transaction(static function (Database $db) use ($productId, $clean, $actorId, $reason, $allowStockEdit): void {
            foreach ($clean as $pos => $v) {
                $clash = $db->value('SELECT id FROM product_variants WHERE sku = ? AND id <> ?', [$v['sku'], $v['id'] ?? 0]);
                if ($clash) {
                    throw ValidationException::one('variants', "SKU {$v['sku']} is already used by another variant.");
                }
                if ($v['id']) {
                    $existing = $db->one('SELECT * FROM product_variants WHERE id = ? AND product_id = ? FOR UPDATE', [$v['id'], $productId]);
                    if (!$existing) {
                        throw ValidationException::one('variants', 'Variant not found.');
                    }
                    if ($v['remove']) {
                        $hasOrders = $db->value('SELECT 1 FROM order_items WHERE variant_id = ? LIMIT 1', [$v['id']]);
                        if ($hasOrders) {
                            $db->exec('UPDATE product_variants SET is_active = 0 WHERE id = ?', [$v['id']]);
                        } else {
                            $db->exec('DELETE FROM product_variants WHERE id = ?', [$v['id']]);
                        }
                        continue;
                    }
                    $db->exec(
                        'UPDATE product_variants SET sku=?, size=?, color=?, color_hex=?, price_minor=?, sale_price_minor=?, low_stock_threshold=?, is_active=?, position=? WHERE id = ?',
                        [$v['sku'], $v['size'], $v['color'], $v['hex'], $v['price'], $v['sale'], $v['low'], $v['active'], $pos, $v['id']]
                    );
                    if ($allowStockEdit && $v['stock'] !== (int) $existing['stock']) {
                        Inventory::set($v['id'], $v['stock'], $reason, 'Edited on product page', $actorId);
                    }
                } else {
                    if ($v['remove']) {
                        continue;
                    }
                    $vid = $db->insert(
                        'INSERT INTO product_variants (product_id, sku, size, color, color_hex, price_minor, sale_price_minor, stock, low_stock_threshold, is_active, position) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 1, ?)',
                        [$productId, $v['sku'], $v['size'], $v['color'], $v['hex'], $v['price'], $v['sale'], $v['low'], $pos]
                    );
                    if ($v['stock'] > 0) {
                        Inventory::apply($vid, $v['stock'], $reason === 'import' ? 'import' : 'initial', 'Opening stock', null, $actorId);
                    }
                }
            }
        });
        return $clean;
    }

    public static function deleteOrArchive(int $id): string
    {
        $db = Database::get();
        $hasOrders = $db->value('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1', [$id]);
        if ($hasOrders) {
            $db->exec("UPDATE products SET status = 'archived' WHERE id = ?", [$id]);
            return 'archived';
        }
        $paths = $db->column('SELECT path FROM product_images WHERE product_id = ?', [$id]);
        $db->exec('DELETE FROM products WHERE id = ?', [$id]);
        foreach ($paths as $p) {
            Uploads::deleteImage((string) $p);
        }
        return 'deleted';
    }
}
