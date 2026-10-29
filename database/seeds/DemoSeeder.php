<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Uploads;
use App\Services\Inventory;

/**
 * DEVELOPMENT DEMO DATA ONLY. Every row is flagged is_demo = 1 and is removed
 * by `php bin/console.php demo:purge`. Product imagery is generated (flat
 * illustrations) through the real upload pipeline — replace with real
 * photography before launch.
 */
final class DemoSeeder
{
    private const COLORS = [
        'Onyx' => '#1A1A1A', 'Ivory' => '#EFE9DC', 'Camel' => '#B08A5A', 'Navy' => '#22304A',
        'Olive' => '#5D6340', 'Stone' => '#B9B2A5', 'White' => '#F7F7F5', 'Burgundy' => '#5E1F2A',
    ];

    public function run(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM products WHERE is_demo = 1') > 0) {
            fwrite(STDOUT, "Demo data already present — skipping. Run demo:purge first to reseed.\n");
            return;
        }

        $cats = [];
        foreach ([
            ['Shirts', 'shirts', 'Oxford, poplin and linen shirts cut for a clean silhouette.'],
            ['T-Shirts & Polos', 't-shirts', 'Heavyweight cotton essentials.'],
            ['Denim', 'denim', 'Selvedge-inspired denim in modern fits.'],
            ['Outerwear', 'outerwear', 'Jackets and overshirts for every season.'],
            ['Knitwear', 'knitwear', 'Fine-gauge knits and cardigans.'],
            ['Trousers', 'trousers', 'Tailored chinos and pleated trousers.'],
        ] as $i => [$name, $slug, $desc]) {
            $cats[$slug] = $db->insert('INSERT INTO categories (name, slug, description, sort_order, is_demo) VALUES (?, ?, ?, ?, 1)', [$name, $slug, $desc, $i]);
        }

        $cols = [];
        foreach ([
            ['Signature Tailoring', 'signature-tailoring', 'Precise cuts and refined fabrics — the pieces URAVEX is known for.', true],
            ['Weekend Essentials', 'weekend-essentials', 'Relaxed staples built for everyday wear.', true],
            ['The Winter Edit', 'winter-edit', 'Layer-ready knits and outerwear.', true],
        ] as $i => [$name, $slug, $desc, $home]) {
            $img = $this->image($cats ? array_values(self::COLORS)[$i + 2] : '#222', 'collection', $name, 1200, 1500);
            $cols[$slug] = $db->insert(
                'INSERT INTO collections (name, slug, description, image_url, featured_on_home, home_order, sort_order, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$name, $slug, $desc, $img['path'], $home, $i, $i]
            );
        }

        $products = [
            ['Classic Oxford Shirt', 'shirts', 4990, null, ['Ivory', 'Navy', 'White'], ['S', 'M', 'L', 'XL'], ['signature-tailoring'], 'shirt', true],
            ['Linen Camp Collar Shirt', 'shirts', 5490, 4390, ['Stone', 'Olive'], ['S', 'M', 'L', 'XL'], ['weekend-essentials'], 'shirt', true],
            ['Poplin Dress Shirt', 'shirts', 5990, null, ['White', 'Ivory'], ['S', 'M', 'L', 'XL', 'XXL'], ['signature-tailoring'], 'shirt', false],
            ['Heavyweight Crew Tee', 't-shirts', 2490, null, ['Onyx', 'White', 'Stone'], ['S', 'M', 'L', 'XL'], ['weekend-essentials'], 'tee', true],
            ['Piqué Polo', 't-shirts', 3290, 2790, ['Navy', 'Onyx', 'Burgundy'], ['S', 'M', 'L', 'XL'], ['weekend-essentials'], 'tee', false],
            ['Slim Tapered Jeans', 'denim', 6990, null, ['Navy', 'Onyx'], ['30', '32', '34', '36'], ['weekend-essentials'], 'trouser', true],
            ['Straight Fit Selvedge Jeans', 'denim', 8490, null, ['Navy'], ['30', '32', '34', '36', '38'], ['signature-tailoring'], 'trouser', false],
            ['Wool Blend Overcoat', 'outerwear', 18990, 15990, ['Camel', 'Onyx'], ['S', 'M', 'L', 'XL'], ['winter-edit', 'signature-tailoring'], 'jacket', true],
            ['Quilted Field Jacket', 'outerwear', 11990, null, ['Olive', 'Navy'], ['S', 'M', 'L', 'XL'], ['winter-edit'], 'jacket', false],
            ['Cotton Overshirt', 'outerwear', 7490, null, ['Stone', 'Onyx'], ['S', 'M', 'L', 'XL'], ['weekend-essentials', 'winter-edit'], 'jacket', false],
            ['Merino Crew Knit', 'knitwear', 6490, null, ['Burgundy', 'Navy', 'Ivory'], ['S', 'M', 'L', 'XL'], ['winter-edit'], 'knit', true],
            ['Cable Knit Cardigan', 'knitwear', 7990, 6490, ['Ivory', 'Camel'], ['S', 'M', 'L'], ['winter-edit'], 'knit', false],
            ['Tailored Chino', 'trousers', 4990, null, ['Stone', 'Navy', 'Olive'], ['30', '32', '34', '36'], ['signature-tailoring', 'weekend-essentials'], 'trouser', false],
            ['Pleated Wool Trouser', 'trousers', 8990, null, ['Onyx', 'Stone'], ['30', '32', '34', '36'], ['signature-tailoring'], 'trouser', true],
            ['Relaxed Linen Trouser', 'trousers', 5490, null, ['Ivory', 'Stone'], ['S', 'M', 'L', 'XL'], ['weekend-essentials'], 'trouser', false],
            ['Mock Neck Knit', 'knitwear', 5990, null, ['Onyx', 'Camel'], ['S', 'M', 'L', 'XL'], ['winter-edit'], 'knit', false],
        ];

        $owner = $db->value("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1");
        foreach ($products as $i => [$title, $cat, $price, $sale, $colors, $sizes, $collections, $shape, $featured]) {
            $slug = slugify($title);
            $sku = 'UVX-' . strtoupper(substr(preg_replace('/[^A-Z]/i', '', $title), 0, 4)) . sprintf('%03d', $i + 1);
            $published = gmdate('Y-m-d H:i:s', time() - ($i * 3 + 1) * 86400);
            $desc = '<p>The ' . e($title) . ' is cut from carefully selected fabric and finished in our own facilities. A considered fit that sits cleanly without feeling restrictive.</p><ul><li>Premium construction with reinforced seams</li><li>Pre-washed for minimal shrinkage</li><li>Designed and made by URAVEX</li></ul>';
            $pid = $db->insert(
                "INSERT INTO products (title, slug, sku, description, care_details, category_id, tags, price_minor, sale_price_minor, cost_minor, status, featured, featured_order, published_at, is_demo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, 1)",
                [$title, $slug, $sku, $desc, "Machine wash cold, inside out.\nDo not tumble dry. Warm iron.", $cats[$cat], strtolower($cat . ',' . implode(',', $colors)),
                 $price * 100, $sale ? $sale * 100 : null, (int) round($price * 100 * 0.42), $featured, $i, $published]
            );
            foreach ($collections as $c) {
                $db->exec('INSERT INTO product_collections (product_id, collection_id, sort_order) VALUES (?, ?, ?)', [$pid, $cols[$c], $i]);
            }
            $pos = 0;
            foreach ($colors as $color) {
                foreach ([0, 1] as $alt) {
                    $img = $this->image(self::COLORS[$color], $shape, $alt ? '' : $title, 1200, 1600, (bool) $alt);
                    $db->insert(
                        'INSERT INTO product_images (product_id, path, alt, color, width, height, position) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$pid, $img['path'], $title . ' in ' . $color . ($alt ? ' — detail' : ''), $color, $img['width'], $img['height'], $pos++]
                    );
                }
            }
            $vpos = 0;
            foreach ($colors as $ci => $color) {
                foreach ($sizes as $si => $size) {
                    // Varied stock so the demo shows in-stock, low-stock and sold-out states.
                    $stock = (($i + $ci + $si) % 7 === 0) ? 0 : ((($i + $si) % 5 === 0) ? 3 : 12 + (($i * 7 + $si * 3) % 20));
                    $vid = $db->insert(
                        'INSERT INTO product_variants (product_id, sku, size, color, color_hex, stock, low_stock_threshold, position) VALUES (?, ?, ?, ?, ?, 0, 5, ?)',
                        [$pid, $sku . '-' . strtoupper(substr($color, 0, 3)) . '-' . $size, $size, $color, self::COLORS[$color], $vpos++]
                    );
                    if ($stock > 0) {
                        Inventory::apply($vid, $stock, 'initial', 'Demo opening stock', null, $owner ? (int) $owner : null);
                    }
                }
            }
        }

        $hero = $this->image('#14110C', 'hero', '', 1920, 1080);
        $db->insert(
            "INSERT INTO banners (placement, eyebrow, title, subtitle, image_path, image_alt, cta_label, cta_href, secondary_cta_label, secondary_cta_href, sort_order, is_demo)
             VALUES ('hero', 'Autumn / Winter 2026', 'Crafted to be worn. Made to last.', 'Signature tailoring, refined knitwear and outerwear — made in our own facilities.', ?, 'URAVEX autumn winter campaign', 'Shop now', '/shop', 'Explore collection', '/collections/winter-edit', 0, 1)",
            [$hero['path']]
        );
        $db->insert(
            "INSERT INTO banners (placement, eyebrow, title, subtitle, cta_label, cta_href, sort_order, is_demo)
             VALUES ('promo', 'Limited time', 'Up to 20% off selected pieces', 'Linen shirts, polos and knitwear — while stock lasts.', 'Shop the sale', '/sale', 0, 1)"
        );

        $db->insert("INSERT INTO coupons (code, description, type, value, min_subtotal_minor, per_customer_limit, is_demo) VALUES ('WELCOME10', '10% off your first order', 'percentage', 10, 300000, 1, 1)");
        $db->insert("INSERT INTO coupons (code, description, type, value, free_shipping, min_subtotal_minor, is_demo) VALUES ('FREESHIP', 'Free delivery on any order', 'free_shipping', 0, 1, 0, 1)");

        // Demo customers and historical orders for the dashboard.
        $customers = [];
        foreach ([['Ayesha Khan', 'ayesha.demo@example.com', 'Lahore', 'Punjab'], ['Bilal Ahmed', 'bilal.demo@example.com', 'Karachi', 'Sindh'], ['Sara Malik', 'sara.demo@example.com', 'Islamabad', 'Islamabad Capital Territory']] as [$n, $em, $city, $prov]) {
            $customers[] = [
                'id' => $db->insert(
                    "INSERT INTO users (name, email, phone, password_hash, email_verified_at, is_demo) VALUES (?, ?, '03001234567', ?, UTC_TIMESTAMP(), 1)",
                    [$n, $em, Auth::hashPassword(bin2hex(random_bytes(16)))]
                ),
                'name' => $n, 'email' => $em, 'city' => $city, 'province' => $prov,
            ];
        }
        $variants = $db->all('SELECT v.*, p.title, p.price_minor AS p_price, p.sale_price_minor AS p_sale, p.id AS pid FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.is_demo = 1 AND v.stock > 5 ORDER BY v.id');
        $statuses = ['delivered', 'delivered', 'delivered', 'shipped', 'processing', 'confirmed', 'delivered', 'cancelled', 'delivered', 'packed', 'delivered', 'awaiting_payment'];
        for ($n = 0; $n < 36; $n++) {
            $c = $customers[$n % 3];
            $status = $statuses[$n % count($statuses)];
            $method = $status === 'awaiting_payment' ? 'bank_transfer' : 'cod';
            $daysAgo = 2 + $n * 2;
            $placed = gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400 - ($n * 3571) % 40000);
            $lines = [];
            for ($k = 0; $k <= $n % 3; $k++) {
                $v = $variants[($n * 5 + $k * 11) % count($variants)];
                $unit = App\Services\Pricing::unitPrice(['price_minor' => $v['p_price'], 'sale_price_minor' => $v['p_sale']], $v)['price'];
                $lines[] = [$v, $unit, 1 + ($k + $n) % 2];
            }
            $subtotal = array_sum(array_map(static fn ($l) => $l[1] * $l[2], $lines));
            $shipping = $subtotal >= 500000 ? 0 : 25000;
            $addr = json_encode(['full_name' => $c['name'], 'phone' => '03001234567', 'line1' => 'House 12, Street 4', 'line2' => null, 'city' => $c['city'], 'province' => $c['province'], 'postal_code' => null, 'country' => 'PK']);
            $paid = $status === 'delivered';
            $oid = $db->insert(
                'INSERT INTO orders (user_id, email, phone, customer_name, status, payment_status, payment_method, currency, subtotal_minor, shipping_minor, total_minor, shipping_address, billing_address, shipping_method, idempotency_key, is_demo, placed_at, paid_at, stock_released)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'PKR\', ?, ?, ?, ?, ?, \'Standard delivery (2–3 days)\', ?, 1, ?, ?, ?)',
                [$c['id'], $c['email'], '03001234567', $c['name'], $status, $paid ? 'paid' : ($status === 'cancelled' ? 'voided' : 'unpaid'), $method,
                 $subtotal, $shipping, $subtotal + $shipping, $addr, $addr, bin2hex(random_bytes(32)), $placed, $paid ? $placed : null, $status === 'cancelled']
            );
            $db->exec('UPDATE orders SET order_number = ? WHERE id = ?', ['UVX-' . (100000 + $oid), $oid]);
            foreach ($lines as [$v, $unit, $qty]) {
                $db->insert(
                    'INSERT INTO order_items (order_id, product_id, variant_id, product_title, variant_label, sku, unit_price_minor, quantity, line_total_minor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$oid, $v['pid'], $v['id'], $v['title'], App\Services\Cart::variantLabel($v['size'], $v['color']), $v['sku'], $unit, $qty, $unit * $qty]
                );
            }
            $db->insert(
                'INSERT INTO payments (order_id, provider, status, amount_minor, currency, created_at) VALUES (?, ?, ?, ?, \'PKR\', ?)',
                [$oid, $method, $paid ? 'paid' : ($status === 'cancelled' ? 'cancelled' : 'pending'), $subtotal + $shipping, $placed]
            );
            $db->insert('INSERT INTO order_status_history (order_id, from_status, to_status, note, created_at) VALUES (?, NULL, ?, \'Demo order\', ?)', [$oid, $status, $placed]);
            if (in_array($status, ['shipped', 'delivered'], true)) {
                $db->insert('INSERT INTO shipments (order_id, courier, tracking_number, shipped_at) VALUES (?, \'TCS\', ?, ?)', [$oid, 'DEMO' . (700000 + $oid), $placed]);
            }
        }
    }

    /** Draws a flat garment illustration and stores it through Uploads::storeImage(). */
    private function image(string $hex, string $shape, string $label, int $w, int $h, bool $detail = false): array
    {
        $im = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $bg = $shape === 'hero' || $shape === 'collection'
            ? imagecolorallocate($im, max(0, $r - 10), max(0, $g - 10), max(0, $b - 10))
            : imagecolorallocate($im, 245, 243, 238);
        imagefill($im, 0, 0, $bg);
        $fill = imagecolorallocate($im, $r, $g, $b);
        $shade = imagecolorallocate($im, (int) max(0, $r * 0.82), (int) max(0, $g * 0.82), (int) max(0, $b * 0.82));
        $gold = imagecolorallocate($im, 200, 155, 60);
        $line = imagecolorallocate($im, 229, 193, 107);
        $cx = (int) ($w / 2);
        $s = $w / 1200;
        $p = static fn (array $pts) => array_map(static fn ($v, $i) => (int) ($i % 2 ? $v * $s + $h * 0.12 : $cx + ($v - 600) * $s), $pts, array_keys($pts));

        if ($shape === 'hero' || $shape === 'collection') {
            for ($i = 0; $i < $h; $i += 2) {
                $a = (int) (40 + 60 * $i / $h);
                imageline($im, 0, $i, $w, $i, imagecolorallocatealpha($im, 0, 0, 0, max(0, 127 - $a)));
            }
            imagesetthickness($im, max(2, (int) (3 * $s)));
            imageline($im, (int) ($w * 0.58), (int) ($h * 0.15), (int) ($w * 0.86), (int) ($h * 0.85), $gold);
            imageline($im, (int) ($w * 0.62), (int) ($h * 0.15), (int) ($w * 0.90), (int) ($h * 0.85), $line);
            imagefilledellipse($im, (int) ($w * 0.74), (int) ($h * 0.5), (int) ($h * 0.62), (int) ($h * 0.62), imagecolorallocatealpha($im, 200, 155, 60, 112));
        } else {
            $zoom = $detail ? 1.5 : 1.0;
            $poly = match ($shape) {
                'tee' => [380, 120, 520, 90, 680, 90, 820, 120, 980, 300, 880, 380, 800, 320, 800, 980, 400, 980, 400, 320, 320, 380, 220, 300],
                'trouser' => [420, 80, 780, 80, 820, 1050, 640, 1050, 600, 380, 560, 1050, 380, 1050],
                'jacket' => [390, 110, 520, 80, 680, 80, 810, 110, 960, 260, 990, 940, 860, 960, 830, 420, 830, 1040, 370, 1040, 370, 420, 340, 960, 210, 940, 240, 260],
                'knit' => [380, 130, 520, 100, 680, 100, 820, 130, 960, 320, 1000, 900, 880, 920, 820, 420, 820, 1000, 380, 1000, 380, 420, 320, 920, 200, 900, 240, 320],
                default => [390, 120, 520, 90, 600, 140, 680, 90, 810, 120, 960, 300, 1000, 920, 880, 940, 820, 420, 820, 1020, 380, 1020, 380, 420, 320, 940, 200, 920, 240, 300],
            };
            if ($zoom > 1) {
                $poly = array_map(static fn ($v, $i) => $i % 2 ? (int) (($v - 200) * $zoom) : (int) (600 + ($v - 600) * $zoom), $poly, array_keys($poly));
            }
            $pts = $p($poly);
            imagefilledpolygon($im, $pts, $fill);
            imagesetthickness($im, max(2, (int) (2 * $s)));
            imagepolygon($im, $pts, $shade);
            if ($shape === 'shirt' || $shape === 'jacket') {
                imageline($im, $cx, (int) (140 * $s * $zoom + $h * 0.12), $cx, (int) min($h - 10, 1000 * $s * $zoom + $h * 0.12), $shade);
                for ($y = 260; $y < 980; $y += 120) {
                    imagefilledellipse($im, $cx + (int) (14 * $s), (int) ($y * $s * $zoom + $h * 0.12), (int) (14 * $s), (int) (14 * $s), $lum < 0.5 ? $line : $shade);
                }
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'uvx') . '.png';
        imagepng($im, $tmp, 6);
        imagedestroy($im);
        $folder = $shape === 'hero' ? 'banners' : ($shape === 'collection' ? 'collections' : 'products');
        $stored = Uploads::storeImage(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => 'demo.png'], $folder);
        @unlink($tmp);
        return $stored;
    }

    /** Removes every demo row (and its generated images). */
    public static function purge(Database $db): void
    {
        $db->transaction(static function (Database $db): void {
            foreach ($db->column('SELECT path FROM product_images pi JOIN products p ON p.id = pi.product_id WHERE p.is_demo = 1') as $path) {
                Uploads::deleteImage((string) $path);
            }
            foreach ($db->column('SELECT image_path FROM banners WHERE is_demo = 1 AND image_path IS NOT NULL') as $path) {
                Uploads::deleteImage((string) $path);
            }
            foreach ($db->column('SELECT image_url FROM collections WHERE is_demo = 1 AND image_url IS NOT NULL') as $path) {
                Uploads::deleteImage((string) $path);
            }
            $db->exec('DELETE cr FROM coupon_redemptions cr JOIN coupons c ON c.id = cr.coupon_id WHERE c.is_demo = 1');
            $db->exec('DELETE FROM orders WHERE is_demo = 1');
            $db->exec('DELETE FROM coupons WHERE is_demo = 1');
            $db->exec('DELETE FROM banners WHERE is_demo = 1');
            $db->exec('DELETE FROM products WHERE is_demo = 1');
            $db->exec('DELETE FROM collections WHERE is_demo = 1');
            $db->exec('DELETE FROM categories WHERE is_demo = 1');
            $db->exec('DELETE FROM users WHERE is_demo = 1');
        });
    }
}
