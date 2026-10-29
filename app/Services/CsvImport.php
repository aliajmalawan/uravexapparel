<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Core\ValidationException;

/**
 * Product CSV import. One row per variant; product columns repeat per row and
 * rows are grouped by product_sku. Existing product SKUs are updated, new ones
 * created. Validation reports errors per row; commit is all-or-nothing.
 */
final class CsvImport
{
    public const COLUMNS = ['product_sku', 'title', 'slug', 'category', 'status', 'price', 'sale_price', 'tags', 'variant_sku', 'size', 'color', 'color_hex', 'variant_price', 'stock', 'low_stock_threshold', 'description'];
    public const REQUIRED = ['product_sku', 'title', 'price', 'variant_sku'];
    public const MAX_ROWS = 5000;

    /** @return list<array<string,string>> rows keyed by column name, with '_line' */
    public static function parse(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw ValidationException::one('file', 'Could not read the file.');
        }
        $first = fgets($fh);
        if ($first === false) {
            throw ValidationException::one('file', 'The file is empty.');
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), str_getcsv((string) $first, ',', '"', ''));
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            throw ValidationException::one('file', 'Missing required columns: ' . implode(', ', $missing) . '. Download the template for the correct format.');
        }
        $unknown = array_diff($header, self::COLUMNS);
        if ($unknown) {
            throw ValidationException::one('file', 'Unknown columns: ' . implode(', ', $unknown) . '.');
        }
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::one('file', 'Files are limited to ' . self::MAX_ROWS . ' rows.');
            }
            $row = ['_line' => (string) $line];
            foreach ($header as $i => $col) {
                $val = (string) ($cells[$i] ?? '');
                if (!mb_check_encoding($val, 'UTF-8')) {
                    $val = mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
                }
                // Strip spreadsheet formula-escape prefix written by our exporter.
                $row[$col] = trim(str_starts_with($val, "'") && strlen($val) > 1 && in_array($val[1], ['=', '+', '-', '@'], true) ? substr($val, 1) : $val);
            }
            $rows[] = $row;
        }
        fclose($fh);
        if (!$rows) {
            throw ValidationException::one('file', 'The file has a header but no data rows.');
        }
        return $rows;
    }

    /**
     * Validates rows and builds a plan.
     * @return array{products:array, errors:list<array{line:int,message:string}>, summary:array}
     */
    public static function plan(array $rows): array
    {
        $db = Database::get();
        $errors = [];
        $products = [];
        $variantSkus = [];
        $categories = [];
        foreach ($db->all('SELECT id, slug FROM categories') as $c) {
            $categories[$c['slug']] = (int) $c['id'];
        }
        foreach ($rows as $r) {
            $line = (int) $r['_line'];
            $err = static function (string $m) use (&$errors, $line) {
                $errors[] = ['line' => $line, 'message' => $m];
            };
            foreach (self::REQUIRED as $req) {
                if (($r[$req] ?? '') === '') {
                    $err("{$req} is required.");
                    continue 2;
                }
            }
            $psku = strtoupper($r['product_sku']);
            $vsku = strtoupper($r['variant_sku']);
            if (!preg_match('/^[A-Z0-9._-]{1,64}$/', $psku) || !preg_match('/^[A-Z0-9._-]{1,64}$/', $vsku)) {
                $err('SKUs may contain letters, numbers, dot, underscore and hyphen only.');
                continue;
            }
            if (isset($variantSkus[$vsku])) {
                $err("Duplicate variant_sku {$vsku} (also on line {$variantSkus[$vsku]}).");
                continue;
            }
            $variantSkus[$vsku] = $line;
            $price = Money::parse($r['price']);
            $sale = ($r['sale_price'] ?? '') !== '' ? Money::parse($r['sale_price']) : null;
            $vprice = ($r['variant_price'] ?? '') !== '' ? Money::parse($r['variant_price']) : null;
            if ($price === null || $price <= 0 || (($r['sale_price'] ?? '') !== '' && $sale === null) || (($r['variant_price'] ?? '') !== '' && $vprice === null)) {
                $err('Prices must be positive numbers like 2499 or 2499.50.');
                continue;
            }
            if ($sale !== null && $sale >= $price) {
                $err('sale_price must be lower than price.');
                continue;
            }
            $status = strtolower($r['status'] ?? '') ?: 'draft';
            if (!in_array($status, ['draft', 'active', 'archived'], true)) {
                $err('status must be draft, active or archived.');
                continue;
            }
            $catSlug = strtolower($r['category'] ?? '');
            if ($catSlug !== '' && !isset($categories[$catSlug])) {
                $err("Unknown category slug \"{$catSlug}\". Create it first in Admin → Categories.");
                continue;
            }
            $stock = ($r['stock'] ?? '') === '' ? 0 : $r['stock'];
            $low = ($r['low_stock_threshold'] ?? '') === '' ? 5 : $r['low_stock_threshold'];
            if (!preg_match('/^\d{1,6}$/', (string) $stock) || !preg_match('/^\d{1,5}$/', (string) $low)) {
                $err('stock and low_stock_threshold must be whole numbers ≥ 0.');
                continue;
            }
            if (($r['color_hex'] ?? '') !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $r['color_hex'])) {
                $err('color_hex must look like #0B0B0B.');
                continue;
            }
            if (mb_strlen($r['title']) > 191) {
                $err('title is longer than 191 characters.');
                continue;
            }
            $owner = $db->value('SELECT p.sku FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.sku = ?', [$vsku]);
            if ($owner !== null && $owner !== $psku) {
                $err("variant_sku {$vsku} already belongs to product {$owner}.");
                continue;
            }
            if (!isset($products[$psku])) {
                $existing = $db->one('SELECT id, slug FROM products WHERE sku = ?', [$psku]);
                $slug = strtolower($r['slug'] ?? '') ?: ($existing['slug'] ?? slugify($r['title']));
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    $err('slug may contain lowercase letters, numbers and hyphens only.');
                    continue;
                }
                $slugOwner = $db->value('SELECT sku FROM products WHERE slug = ?', [$slug]);
                if ($slugOwner !== null && $slugOwner !== $psku) {
                    $err("slug \"{$slug}\" is already used by product {$slugOwner}.");
                    continue;
                }
                foreach ($products as $other) {
                    if ($other['slug'] === $slug) {
                        $err("slug \"{$slug}\" is used by two products in this file.");
                        continue 2;
                    }
                }
                $products[$psku] = [
                    'id' => $existing ? (int) $existing['id'] : null,
                    'line' => $line,
                    'data' => [
                        'title' => $r['title'], 'slug' => $slug, 'sku' => $psku,
                        'description' => \App\Core\Sanitizer::html($r['description'] ?? ''),
                        'care_details' => null, 'shipping_returns' => null,
                        'category_id' => $catSlug !== '' ? $categories[$catSlug] : null,
                        'tags' => mb_substr(strtolower($r['tags'] ?? ''), 0, 500),
                        'price' => $price, 'sale_price' => $sale, 'cost' => null, 'status' => $status,
                        'featured' => false, 'seo_title' => null, 'seo_description' => null,
                    ],
                    'variants' => [],
                ];
            }
            $products[$psku]['variants'][] = [
                'sku' => $vsku, 'size' => mb_substr($r['size'] ?? '', 0, 40), 'color' => mb_substr($r['color'] ?? '', 0, 60),
                'color_hex' => $r['color_hex'] ?? '', 'price' => $vprice !== null ? Money::decimal($vprice) : '', 'sale_price' => '',
                'stock' => (string) $stock, 'low' => (string) $low, 'active' => '1',
            ];
        }
        $create = count(array_filter($products, static fn ($p) => $p['id'] === null));
        return [
            'products' => $products,
            'errors' => $errors,
            'summary' => ['rows' => count($rows), 'products' => count($products), 'create' => $create, 'update' => count($products) - $create, 'variants' => count($variantSkus), 'errors' => count($errors)],
        ];
    }

    /** Applies a validated plan in one transaction. */
    public static function commit(array $plan, int $actorId): array
    {
        if ($plan['errors']) {
            throw ValidationException::one('file', 'Fix the reported errors before importing.');
        }
        $db = Database::get();
        return $db->transaction(static function (Database $db) use ($plan, $actorId): array {
            $created = $updated = 0;
            foreach ($plan['products'] as $p) {
                $existingCollections = $p['id'] ? array_map('intval', $db->column('SELECT collection_id FROM product_collections WHERE product_id = ?', [$p['id']])) : [];
                $id = Products::save($p['data'], $p['id'], $existingCollections);
                $rows = [];
                foreach ($p['variants'] as $i => $v) {
                    $vid = $db->value('SELECT id FROM product_variants WHERE sku = ? AND product_id = ?', [$v['sku'], $id]);
                    $rows[$vid ? (string) $vid : 'n' . $i] = $v + ($vid ? ['id' => (string) $vid] : []);
                }
                // Keep variants not mentioned in the file untouched.
                foreach ($db->all('SELECT * FROM product_variants WHERE product_id = ?', [$id]) as $ex) {
                    if (!isset($rows[(string) $ex['id']])) {
                        $rows[(string) $ex['id']] = [
                            'id' => (string) $ex['id'], 'sku' => $ex['sku'], 'size' => $ex['size'], 'color' => $ex['color'], 'color_hex' => (string) $ex['color_hex'],
                            'price' => $ex['price_minor'] !== null ? Money::decimal((int) $ex['price_minor']) : '', 'sale_price' => $ex['sale_price_minor'] !== null ? Money::decimal((int) $ex['sale_price_minor']) : '',
                            'stock' => (string) $ex['stock'], 'low' => (string) $ex['low_stock_threshold'], 'active' => $ex['is_active'] ? '1' : '',
                        ];
                    }
                }
                Products::saveVariants($id, $rows, $actorId, 'import');
                $p['id'] ? $updated++ : $created++;
            }
            return ['created' => $created, 'updated' => $updated];
        });
    }
}
