<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;

final class SeoController extends Controller
{
    public function sitemap(Request $r): Response
    {
        $e = Catalog::sitemapEntries();
        $urls = [
            [url('/'), null, '1.0'], [url('/shop'), null, '0.9'], [url('/new-arrivals'), null, '0.8'],
            [url('/collections'), null, '0.7'], [url('/about'), null, '0.4'], [url('/contact'), null, '0.3'], [url('/faq'), null, '0.3'],
        ];
        foreach ($e['products'] as $p) { $urls[] = [url('/product/' . $p['slug']), $p['updated_at'], '0.8']; }
        foreach ($e['categories'] as $c) { $urls[] = [url('/category/' . $c['slug']), $c['updated_at'], '0.7']; }
        foreach ($e['collections'] as $c) { $urls[] = [url('/collections/' . $c['slug']), $c['updated_at'], '0.7']; }
        foreach ($e['pages'] as $p) { if ($p['slug'] !== 'about') { $urls[] = [url('/pages/' . $p['slug']), $p['updated_at'], '0.3']; } }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod, $prio]) {
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
                . ($mod ? '<lastmod>' . gmdate('Y-m-d', strtotime($mod . ' UTC')) . '</lastmod>' : '')
                . '<priority>' . $prio . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        return (new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']));
    }

    public function robots(Request $r): Response
    {
        $base = (string) Config::get('base_path');
        $lines = ['User-agent: *'];
        if (!Config::isProduction()) {
            $lines[] = 'Disallow: /'; // never index development/staging
        } else {
            foreach (['/admin', '/account', '/cart', '/checkout', '/payments', '/webhooks', '/search', '/track-order', '/login', '/register', '/reset-password', '/forgot-password'] as $p) {
                $lines[] = 'Disallow: ' . $base . $p;
            }
            $lines[] = 'Disallow: ' . $base . '/*?*sort=';
            $lines[] = 'Allow: ' . $base . '/';
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('/sitemap.xml');
        return Response::text(implode("\n", $lines) . "\n");
    }

    public function manifest(Request $r): Response
    {
        return new Response(json_encode([
            'name' => 'URAVEX Apparel',
            'short_name' => 'URAVEX',
            'start_url' => url('/'),
            'display' => 'standalone',
            'background_color' => '#0B0B0B',
            'theme_color' => '#0B0B0B',
            'icons' => [
                ['src' => path_url('brand/uravex-emblem-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => path_url('brand/uravex-emblem-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/manifest+json']);
    }
}
