<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;
use App\Services\Settings;
use App\Services\Wishlist;

final class ProductController extends Controller
{
    public function show(Request $r): Response
    {
        $p = Catalog::product($r->params['slug']);
        if (!$p) {
            throw new HttpException(404, 'This product is no longer available.');
        }
        return $this->render($p, false);
    }

    /** Shared with the admin preview (which may show drafts). */
    public function render(array $p, bool $preview): Response
    {
        $inStock = (bool) array_filter($p['variants'], static fn ($v) => (int) $v['stock'] > 0);
        $images = $p['images'];
        $canonical = url('/product/' . $p['slug']);

        $offers = [];
        foreach ($p['variants'] as $v) {
            $offers[] = [
                '@type' => 'Offer',
                'sku' => $v['sku'],
                'price' => Money::decimal($v['pricing']['price'], $p['currency']),
                'priceCurrency' => $p['currency'],
                'availability' => (int) $v['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $canonical,
            ];
        }
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['title'],
            'sku' => $p['sku'],
            'description' => str_limit($p['description'], 500),
            'brand' => ['@type' => 'Brand', 'name' => 'URAVEX'],
            'image' => array_map(static fn ($i) => self::abs(media($i['path'], 1600)), $images),
            'offers' => $offers ?: [
                '@type' => 'Offer', 'price' => Money::decimal($p['pricing']['price'], $p['currency']), 'priceCurrency' => $p['currency'],
                'availability' => 'https://schema.org/OutOfStock', 'url' => $canonical,
            ],
        ];
        if ($p['category_name']) {
            $ld['category'] = $p['category_name'];
        }
        $crumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            $p['category_slug'] ? ['@type' => 'ListItem', 'position' => 2, 'name' => $p['category_name'], 'item' => url('/category/' . $p['category_slug'])] : null,
            ['@type' => 'ListItem', 'position' => $p['category_slug'] ? 3 : 2, 'name' => $p['title'], 'item' => $canonical],
        ]))];

        return $this->view('store/product', [
            'p' => $p,
            'inStock' => $inStock,
            'related' => Catalog::related($p),
            'wishlistIds' => Wishlist::ids(),
            'preview' => $preview,
            'defaultShipping' => Settings::get('shipping'),
        ], [
            'title' => $p['seo_title'] ?: $p['title'],
            'description' => $p['seo_description'] ?: strip_tags($p['description']),
            'canonical' => $canonical,
            'image' => $images ? self::abs(media($images[0]['path'], 1600)) : null,
            'type' => 'product',
            'noindex' => $preview,
            'jsonld' => $preview ? [] : [$ld, $crumbs],
        ]);
    }

    private static function abs(string $path): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        $base = (string) Config::get('base_path');
        return rtrim((string) Config::get('url'), '/') . substr($path, strlen($base));
    }
}
