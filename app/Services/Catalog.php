<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Storefront catalog queries. Only ACTIVE, published products are ever
 * returned to customers.
 */
final class Catalog
{
    public const PER_PAGE = 24;

    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'best_selling' => 'Best selling',
    ];

    // Marketplace products show only while their seller is approved (suspension hides them at once).
    private const VISIBLE = "p.status = 'active' AND p.published_at IS NOT NULL AND p.published_at <= UTC_TIMESTAMP()
        AND (p.seller_id IS NULL OR EXISTS (SELECT 1 FROM sellers sl WHERE sl.id = p.seller_id AND sl.status = 'approved'))";
    private const PRICE = 'COALESCE(p.sale_price_minor, p.price_minor)';

    /**
     * @param array{q?:string,category?:string,collection?:string,sizes?:list<string>,colors?:list<string>,min?:?int,max?:?int,in_stock?:bool,sale?:bool,new?:bool,sort?:string,page?:int} $f
     * @return array{items:list<array>,total:int,page:int,pages:int}
     */
    public static function search(array $f): array
    {
        $db = Database::get();
        $where = [self::VISIBLE];
        $params = [];

        if (!empty($f['q'])) {
            $like = '%' . addcslashes(mb_substr($f['q'], 0, 80), '%_\\') . '%';
            $where[] = '(p.title LIKE ? OR p.tags LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['category'])) {
            $where[] = '(c.slug = ? OR c.parent_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1))';
            array_push($params, $f['category'], $f['category']);
        }
        if (!empty($f['seller_id'])) {
            $where[] = 'p.seller_id = ?';
            $params[] = (int) $f['seller_id'];
        }
        if (!empty($f['collection'])) {
            $where[] = "EXISTS (SELECT 1 FROM product_collections pc JOIN collections co ON co.id = pc.collection_id WHERE pc.product_id = p.id AND co.slug = ? AND co.status = 'active')";
            $params[] = $f['collection'];
        }
        if (!empty($f['sizes'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.size IN ' . Database::in($f['sizes']) . ')';
            array_push($params, ...$f['sizes']);
        }
        if (!empty($f['colors'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.color IN ' . Database::in($f['colors']) . ')';
            array_push($params, ...$f['colors']);
        }
        if (isset($f['min']) && $f['min'] !== null) {
            $where[] = self::PRICE . ' >= ?';
            $params[] = $f['min'];
        }
        if (isset($f['max']) && $f['max'] !== null) {
            $where[] = self::PRICE . ' <= ?';
            $params[] = $f['max'];
        }
        if (!empty($f['in_stock'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND v.stock > 0)';
        }
        if (!empty($f['sale'])) {
            $where[] = 'p.sale_price_minor IS NOT NULL';
        }
        if (!empty($f['new'])) {
            $where[] = 'p.published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 DAY)';
        }

        $order = match ($f['sort'] ?? 'featured') {
            'newest' => 'p.published_at DESC, p.id DESC',
            'price_asc' => self::PRICE . ' ASC, p.id DESC',
            'price_desc' => self::PRICE . ' DESC, p.id DESC',
            'best_selling' => 'sold DESC, p.id DESC',
            default => 'p.featured DESC, p.featured_order ASC, p.published_at DESC, p.id DESC',
        };

        $sql = ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min((int) ($f['page'] ?? 1), $pages));
        $offset = ($page - 1) * self::PER_PAGE;

        $soldSql = ($f['sort'] ?? '') === 'best_selling'
            ? ", (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status NOT IN ('cancelled','pending','awaiting_payment')) AS sold"
            : '';
        $rows = $db->all(
            'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at, c.name AS category_name' . $soldSql
            . $sql . " ORDER BY {$order} LIMIT " . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );
        return ['items' => self::hydrateCards($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Adds images, swatches and stock flags to product rows in 2 queries. */
    public static function hydrateCards(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $db = Database::get();
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $images = [];
        foreach ($db->all('SELECT product_id, path, alt, position FROM product_images WHERE product_id IN ' . Database::in($ids) . ' ORDER BY product_id, position, id', $ids) as $img) {
            $images[$img['product_id']][] = $img;
        }
        $variants = [];
        foreach ($db->all('SELECT product_id, color, color_hex, size, stock FROM product_variants WHERE is_active = 1 AND product_id IN ' . Database::in($ids) . ' ORDER BY position, id', $ids) as $v) {
            $variants[$v['product_id']][] = $v;
        }
        $newCutoff = gmdate('Y-m-d H:i:s', time() - 45 * 86400);
        foreach ($rows as &$r) {
            $imgs = $images[$r['id']] ?? [];
            $vs = $variants[$r['id']] ?? [];
            $r['image'] = $imgs[0]['path'] ?? null;
            $r['image_alt'] = ($imgs[0]['alt'] ?? '') ?: $r['title'];
            $r['image_hover'] = $imgs[1]['path'] ?? null;
            $colors = [];
            foreach ($vs as $v) {
                if ($v['color'] !== '' && !isset($colors[$v['color']])) {
                    $colors[$v['color']] = $v['color_hex'];
                }
            }
            $r['swatches'] = $colors;
            $r['in_stock'] = (bool) array_filter($vs, static fn ($v) => (int) $v['stock'] > 0);
            $r['is_new'] = $r['published_at'] !== null && $r['published_at'] >= $newCutoff;
            $p = Pricing::unitPrice($r);
            $r['price'] = $p['price'];
            $r['regular'] = $p['regular'];
            $r['on_sale'] = $p['on_sale'];
        }
        return $rows;
    }

    public static function product(string $slug): ?array
    {
        $db = Database::get();
        $p = $db->one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, ms.shop_name AS seller_name, ms.slug AS seller_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN sellers ms ON ms.id = p.seller_id WHERE p.slug = ? AND ' . self::VISIBLE,
            [$slug]
        );
        if (!$p) {
            return null;
        }
        $p['images'] = $db->all('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id', [$p['id']]);
        $p['variants'] = $db->all('SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY position, id', [$p['id']]);
        $p['collections'] = $db->all("SELECT co.id, co.name, co.slug FROM collections co JOIN product_collections pc ON pc.collection_id = co.id WHERE pc.product_id = ? AND co.status = 'active'", [$p['id']]);
        foreach ($p['variants'] as &$v) {
            $v['pricing'] = Pricing::unitPrice($p, $v);
        }
        unset($v);
        $p['pricing'] = Pricing::unitPrice($p);
        return $p;
    }

    public static function related(array $product, int $limit = 4): array
    {
        $rows = Database::get()->all(
            'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at FROM products p WHERE ' . self::VISIBLE
            . ' AND p.id <> ? AND (p.category_id <=> ?) ORDER BY p.featured DESC, p.published_at DESC LIMIT ' . $limit,
            [$product['id'], $product['category_id']]
        );
        if (count($rows) < $limit) {
            $have = array_merge([(int) $product['id']], array_map(static fn ($r) => (int) $r['id'], $rows));
            $more = Database::get()->all(
                'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at FROM products p WHERE ' . self::VISIBLE
                . ' AND p.id NOT IN ' . Database::in($have) . ' ORDER BY p.published_at DESC LIMIT ' . ($limit - count($rows)),
                $have
            );
            $rows = array_merge($rows, $more);
        }
        return self::hydrateCards($rows);
    }

    /** Real store figures for the homepage stats strip (visible catalogue only). */
    public static function stats(): array
    {
        $db = Database::get();
        return [
            'products' => (int) $db->value('SELECT COUNT(*) FROM products p WHERE ' . self::VISIBLE),
            'categories' => count(self::categories()),
            'colours' => (int) $db->value("SELECT COUNT(DISTINCT v.color) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.is_active = 1 AND v.color <> '' AND " . self::VISIBLE),
            'sizes' => (int) $db->value("SELECT COUNT(DISTINCT v.size) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.is_active = 1 AND v.size <> '' AND " . self::VISIBLE),
        ];
    }

    public static function newArrivals(int $limit = 8): array
    {
        return self::hydrateCards(Database::get()->all(
            'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at, p.category_id FROM products p WHERE ' . self::VISIBLE . ' ORDER BY p.published_at DESC, p.id DESC LIMIT ' . $limit
        ));
    }

    public static function featured(int $limit = 8): array
    {
        return self::hydrateCards(Database::get()->all(
            'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at FROM products p WHERE ' . self::VISIBLE . ' AND p.featured = 1 ORDER BY p.featured_order, p.published_at DESC LIMIT ' . $limit
        ));
    }

    /** Best sellers by units sold in paid/fulfilled orders (falls back to featured). */
    public static function bestSellers(int $limit = 8): array
    {
        $rows = Database::get()->all(
            'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at, SUM(oi.quantity) AS sold
             FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
             WHERE ' . self::VISIBLE . " AND o.status IN ('confirmed','processing','packed','shipped','delivered') AND o.is_test = 0
             GROUP BY p.id ORDER BY sold DESC LIMIT " . $limit
        );
        if (count($rows) < min(4, $limit)) {
            return self::featured($limit);
        }
        return self::hydrateCards($rows);
    }

    public static function categories(): array
    {
        return Database::get()->all(
            "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND " . self::VISIBLE . ") AS product_count
             FROM categories c WHERE c.status = 'active' ORDER BY c.sort_order, c.name"
        );
    }

    public static function category(string $slug): ?array
    {
        return Database::get()->one("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$slug]);
    }

    public static function collections(bool $homeOnly = false): array
    {
        $sql = "SELECT co.*, (SELECT COUNT(*) FROM product_collections pc JOIN products p ON p.id = pc.product_id WHERE pc.collection_id = co.id AND " . self::VISIBLE . ") AS product_count FROM collections co WHERE co.status = 'active'";
        $sql .= $homeOnly ? ' AND co.featured_on_home = 1 ORDER BY co.home_order, co.name' : ' ORDER BY co.sort_order, co.name';
        return Database::get()->all($sql);
    }

    public static function collection(string $slug): ?array
    {
        return Database::get()->one("SELECT * FROM collections WHERE slug = ? AND status = 'active'", [$slug]);
    }

    /** Available filter facets across visible products. */
    public static function facets(): array
    {
        $db = Database::get();
        $sizes = $db->column("SELECT DISTINCT v.size FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.is_active = 1 AND v.size <> '' AND " . self::VISIBLE);
        $order = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];
        usort($sizes, static function ($a, $b) use ($order) {
            $ia = array_search(strtoupper((string) $a), $order, true);
            $ib = array_search(strtoupper((string) $b), $order, true);
            if ($ia !== false && $ib !== false) {
                return $ia <=> $ib;
            }
            if (is_numeric($a) && is_numeric($b)) {
                return (float) $a <=> (float) $b;
            }
            return ($ia === false) <=> ($ib === false) ?: strnatcasecmp((string) $a, (string) $b);
        });
        $colors = $db->all("SELECT v.color, MAX(v.color_hex) AS hex FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.is_active = 1 AND v.color <> '' AND " . self::VISIBLE . ' GROUP BY v.color ORDER BY v.color');
        $range = $db->one('SELECT MIN(' . self::PRICE . ') AS min, MAX(' . self::PRICE . ') AS max FROM products p WHERE ' . self::VISIBLE);
        return ['sizes' => $sizes, 'colors' => $colors, 'price' => $range];
    }

    /**
     * Tiles for the homepage "Discover" grid: homepage collections first, then
     * categories with products. Each tile uses its own image or, if it has none,
     * the featured image of its newest product.
     *
     * @return list<array{title:string, subtitle:string, href:string, image:?string}>
     */
    public static function discoverTiles(int $limit = 7): array
    {
        $db = Database::get();
        $tiles = [];
        foreach (self::collections(true) as $c) {
            if ((int) $c['product_count'] === 0) {
                continue;
            }
            $img = $c['image_url'] ?: $db->value(
                'SELECT pi.path FROM product_images pi JOIN product_collections pc ON pc.product_id = pi.product_id JOIN products p ON p.id = pi.product_id
                 WHERE pc.collection_id = ? AND pi.position = 0 AND ' . self::VISIBLE . ' ORDER BY p.published_at DESC LIMIT 1',
                [$c['id']]
            );
            $tiles[] = ['title' => $c['name'], 'subtitle' => str_limit((string) $c['description'], 60), 'href' => '/collections/' . $c['slug'], 'image' => $img ?: null];
        }
        foreach (self::categories() as $c) {
            if ((int) $c['product_count'] === 0) {
                continue;
            }
            $img = $c['image_url'] ?: $db->value(
                'SELECT pi.path FROM product_images pi JOIN products p ON p.id = pi.product_id WHERE p.category_id = ? AND pi.position = 0 AND ' . self::VISIBLE . ' ORDER BY p.featured DESC, p.published_at DESC LIMIT 1',
                [$c['id']]
            );
            $tiles[] = ['title' => $c['name'], 'subtitle' => str_limit((string) $c['description'], 60), 'href' => '/category/' . $c['slug'], 'image' => $img ?: null];
        }
        return array_slice($tiles, 0, $limit);
    }

    /**
     * Product rows per category for the homepage ("Women / Men / Kids" style rails).
     *
     * @return list<array{category:array, products:list<array>}>
     */
    public static function categoryRails(int $categories = 3, int $perRail = 10): array
    {
        $rails = [];
        foreach (self::categories() as $c) {
            if ((int) $c['product_count'] === 0) {
                continue;
            }
            $rows = Database::get()->all(
                'SELECT p.id, p.title, p.slug, p.price_minor, p.sale_price_minor, p.currency, p.published_at FROM products p
                 WHERE p.category_id = ? AND ' . self::VISIBLE . ' ORDER BY p.featured DESC, p.published_at DESC LIMIT ' . max(1, $perRail),
                [$c['id']]
            );
            $rails[] = ['category' => $c, 'products' => self::hydrateCards($rows)];
            if (count($rails) >= $categories) {
                break;
            }
        }
        return $rails;
    }

    public static function sitemapEntries(): array
    {
        $db = Database::get();
        return [
            'products' => $db->all('SELECT slug, updated_at FROM products p WHERE ' . self::VISIBLE),
            'categories' => $db->all("SELECT slug, updated_at FROM categories WHERE status = 'active'"),
            'collections' => $db->all("SELECT slug, updated_at FROM collections WHERE status = 'active'"),
            'pages' => $db->all("SELECT slug, updated_at FROM content_pages WHERE status = 'active'"),
        ];
    }
}
