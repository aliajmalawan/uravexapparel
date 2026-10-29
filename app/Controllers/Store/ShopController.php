<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;
use App\Services\Wishlist;

final class ShopController extends Controller
{
    /** Parses and whitelists listing filters from the query string. */
    private function filters(Request $r): array
    {
        $list = static function ($v): array {
            $v = is_array($v) ? $v : (is_string($v) && $v !== '' ? explode(',', $v) : []);
            return array_values(array_unique(array_filter(array_map(static fn ($s) => mb_substr(trim((string) $s), 0, 60), $v))));
        };
        $min = is_string($r->query['min_price'] ?? null) ? Money::parse($r->query['min_price']) : null;
        $max = is_string($r->query['max_price'] ?? null) ? Money::parse($r->query['max_price']) : null;
        $sort = (string) ($r->query['sort'] ?? 'featured');
        return [
            'q' => mb_substr(trim((string) ($r->query['q'] ?? '')), 0, 80),
            'sizes' => array_slice($list($r->query['size'] ?? []), 0, 20),
            'colors' => array_slice($list($r->query['color'] ?? []), 0, 20),
            'min' => $min,
            'max' => $max,
            'in_stock' => ($r->query['availability'] ?? '') === 'in_stock',
            'sort' => array_key_exists($sort, Catalog::SORTS) ? $sort : 'featured',
            'page' => max(1, (int) ($r->query['page'] ?? 1)),
        ];
    }

    private function listing(Request $r, array $context): Response
    {
        $f = $this->filters($r) + $context['filters'];
        $result = Catalog::search($f);
        $base = $context['path'];
        $isFiltered = $f['q'] !== '' || $f['sizes'] || $f['colors'] || $f['min'] !== null || $f['max'] !== null || $f['in_stock'] || $f['sort'] !== 'featured' || $f['page'] > 1;
        return $this->view('store/listing', [
            'heading' => $context['heading'],
            'intro' => $context['intro'] ?? null,
            'eyebrow' => $context['eyebrow'] ?? null,
            'basePath' => $base,
            'filters' => $f,
            'result' => $result,
            'facets' => Catalog::facets(),
            'categories' => Catalog::categories(),
            'activeCategory' => $context['filters']['category'] ?? null,
            'breadcrumbs' => $context['breadcrumbs'] ?? [],
            'wishlistIds' => Wishlist::ids(),
            'query' => $r->query,
        ], [
            'title' => $context['title'] ?? $context['heading'],
            'description' => $context['description'] ?? null,
            // Filtered/sorted variants canonicalise to the clean listing URL.
            'canonical' => url($base),
            'noindex' => $f['q'] !== '' || ($isFiltered && $f['page'] === 1 && ($f['sizes'] || $f['colors'] || $f['min'] !== null)),
            'jsonld' => [$this->breadcrumbLd(array_merge([['Home', '/']], $context['breadcrumbs'] ?? [], [[$context['heading'], $base]]))],
        ]);
    }

    public function shop(Request $r): Response
    {
        return $this->listing($r, ['path' => '/shop', 'heading' => 'Shop all', 'eyebrow' => 'The collection', 'filters' => [],
            'description' => 'Shop the full URAVEX collection — shirts, denim, outerwear and essentials, shipped across the United States.']);
    }

    public function newArrivals(Request $r): Response
    {
        return $this->listing($r, ['path' => '/new-arrivals', 'heading' => 'New arrivals', 'eyebrow' => 'Just landed', 'filters' => ['new' => true],
            'description' => 'The latest arrivals from URAVEX Apparel.']);
    }

    public function sale(Request $r): Response
    {
        return $this->listing($r, ['path' => '/sale', 'heading' => 'Sale', 'eyebrow' => 'Limited time', 'filters' => ['sale' => true],
            'description' => 'Selected URAVEX pieces at reduced prices.']);
    }

    public function search(Request $r): Response
    {
        $q = mb_substr(trim((string) ($r->query['q'] ?? '')), 0, 80);
        return $this->listing($r, ['path' => '/search', 'heading' => $q !== '' ? 'Results for “' . $q . '”' : 'Search', 'title' => 'Search', 'filters' => []]);
    }

    /** Public storefront of an approved marketplace seller. */
    public function seller(Request $r): Response
    {
        $s = \App\Services\Marketplace::bySlug($r->params['slug']);
        if (!$s) {
            throw new HttpException(404);
        }
        return $this->listing($r, [
            'path' => '/sellers/' . $s['slug'], 'heading' => $s['shop_name'], 'eyebrow' => 'Seller on URAVEX', 'intro' => $s['description'],
            'description' => $s['description'], 'filters' => ['seller_id' => (int) $s['id']],
            'breadcrumbs' => [['Shop', '/shop']],
        ]);
    }

    public function category(Request $r): Response
    {
        $c = Catalog::category($r->params['slug']);
        if (!$c) {
            throw new HttpException(404);
        }
        return $this->listing($r, [
            'path' => '/category/' . $c['slug'], 'heading' => $c['name'], 'eyebrow' => 'Category', 'intro' => $c['description'],
            'title' => $c['seo_title'] ?: $c['name'], 'description' => $c['seo_description'] ?: $c['description'],
            'filters' => ['category' => $c['slug']],
            'breadcrumbs' => [['Shop', '/shop']],
        ]);
    }

    public function collections(Request $r): Response
    {
        return $this->view('store/collections', ['collections' => Catalog::collections()], [
            'title' => 'Collections', 'canonical' => url('/collections'),
            'description' => 'Explore curated URAVEX collections.',
        ]);
    }

    public function collection(Request $r): Response
    {
        $c = Catalog::collection($r->params['slug']);
        if (!$c) {
            throw new HttpException(404);
        }
        return $this->listing($r, [
            'path' => '/collections/' . $c['slug'], 'heading' => $c['name'], 'eyebrow' => 'Collection', 'intro' => $c['description'],
            'title' => $c['seo_title'] ?: $c['name'], 'description' => $c['seo_description'] ?: $c['description'],
            'filters' => ['collection' => $c['slug']],
            'breadcrumbs' => [['Collections', '/collections']],
        ]);
    }

    private function breadcrumbLd(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => [$name, $path]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => url($path)];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
}
