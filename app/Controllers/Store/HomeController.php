<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Catalog;
use App\Services\Content;
use App\Services\Settings;
use App\Services\Wishlist;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $store = Settings::get('store');
        $sec = Settings::get('sections');
        return $this->view('store/home', [
            'heroes' => Content::banners('hero', 3),
            'newArrivals' => !empty($sec['show_new']) ? Catalog::newArrivals(max(1, (int) $sec['new_count'])) : [],
            'bestSellers' => !empty($sec['show_bestsellers']) ? Catalog::bestSellers(max(1, (int) $sec['bestsellers_count'])) : [],
            'collections' => !empty($sec['show_collections']) ? Catalog::collections(true) : [],
            'promos' => !empty($sec['show_promos']) ? Content::banners('promo', 2) : [],
            'sec' => $sec,
            'tiles' => !empty($sec['show_discover']) ? Catalog::discoverTiles(7) : [],
            'rails' => !empty($sec['show_rails']) ? Catalog::categoryRails(max(1, (int) ($sec['rails_count'] ?? 3))) : [],
            'home' => Settings::get('home'),
            'stats' => Catalog::stats(),
            'wishlistIds' => Wishlist::ids(),
        ], [
            'canonical' => url('/'),
            'jsonld' => [[
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $store['name'] ?? 'URAVEX Apparel',
                'url' => url('/'),
                'logo' => url('/brand/uravex-logo-1200.png'),
                'sameAs' => array_values(array_filter(Settings::get('social', []))),
            ], [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'URAVEX Apparel',
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/search') . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ]],
        ]);
    }
}
