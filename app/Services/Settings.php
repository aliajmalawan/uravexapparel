<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Key/value site settings stored as JSON in site_settings. Each top-level key
 * is a group (e.g. "store", "announcement"); dotted lookups read into it.
 * Defaults keep the storefront working before anything is configured.
 */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'store' => [
            'name' => 'URAVEX Apparel',
            'tagline' => 'Global clothing manufacturing & exports',
            'support_email' => 'support@uravexapparel.com',
            'support_phone' => '',
            'whatsapp' => '',
            'address' => '',
            'business_hours' => 'Mon–Sat, 10am – 7pm PKT',
        ],
        'announcement' => [
            'enabled' => true,
            'text' => 'Free US shipping on orders over $75',
            'link' => '/shop',
        ],
        'social' => ['instagram' => '', 'facebook' => '', 'tiktok' => '', 'youtube' => '', 'linkedin' => ''],
        'seo' => [
            'default_title' => 'URAVEX Apparel — Premium Clothing',
            'default_description' => 'Premium apparel crafted by URAVEX — new arrivals, signature collections and essentials, shipped across the United States.',
            'og_image' => '',
        ],
        'home' => [
            'story_eyebrow' => 'The URAVEX story',
            'story_title' => 'Made with intent. Built to last.',
            'story_body' => "URAVEX began as a manufacturer and exporter trusted by global labels. Today we bring that same craft directly to you — considered fabrics, precise tailoring and finishing that holds up to everyday life.",
            'story_image' => '',
            'story_cta_label' => 'Our story',
            'story_cta_href' => '/about',
            'vision_title' => 'Our Vision',
            'vision_body' => 'To make well-made, honest clothing easy to buy — premium fabrics, precise tailoring and fair prices, delivered to your door anywhere in the United States.',
            'history_title' => 'Our Story',
            'history_body' => 'URAVEX began as a clothing manufacturer and exporter producing for international labels. Today we bring that same factory craftsmanship directly to customers through our own store.',
            'newsletter_title' => 'Join the URAVEX list',
            'newsletter_body' => 'New drops, private sales and styling notes. No spam — unsubscribe anytime.',
            'assurances' => [
                ['icon' => 'truck', 'title' => 'Fast US shipping', 'text' => '2–7 business days nationwide'],
                ['icon' => 'refresh', 'title' => 'Easy exchanges', 'text' => '7-day exchange on unworn items'],
                ['icon' => 'shield', 'title' => 'Secure checkout', 'text' => 'Pay with USDT, BTC or ETH'],
                ['icon' => 'chat', 'title' => 'Real support', 'text' => 'Talk to us, Mon–Sat'],
            ],
        ],
        'footer' => [
            'about' => 'Premium apparel from a manufacturer trusted by global brands.',
            'copyright' => 'URAVEX Apparel. All rights reserved.',
        ],
        // Header menu and footer link columns (Admin → Content → Navigation).
        'navigation' => [
            'header' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Shop', 'href' => '/shop'],
                ['label' => 'New Arrivals', 'href' => '/new-arrivals'],
                ['label' => 'Collections', 'href' => '/collections'],
                ['label' => 'About', 'href' => '/about'],
                ['label' => 'Contact', 'href' => '/contact'],
            ],
            'footer' => [
                ['title' => 'Shop', 'links' => [
                    ['label' => 'New arrivals', 'href' => '/new-arrivals'],
                    ['label' => 'All products', 'href' => '/shop'],
                    ['label' => 'Collections', 'href' => '/collections'],
                    ['label' => 'Sale', 'href' => '/sale'],
                ]],
                ['title' => 'Help', 'links' => [
                    ['label' => 'Track your order', 'href' => '/track-order'],
                    ['label' => 'FAQs', 'href' => '/faq'],
                    ['label' => 'Contact us', 'href' => '/contact'],
                    ['label' => 'My account', 'href' => '/account'],
                ]],
            ],
            // Third footer column lists active content pages automatically.
            'footer_pages_title' => 'Company',
        ],
        // Homepage section headings, visibility and product counts.
        'sections' => [
            'hero_eyebrow' => 'New season',
            'hero_title' => 'Crafted to be worn, made to last.',
            'hero_subtitle' => 'Signature tailoring, everyday essentials and statement layers from URAVEX.',
            'show_new' => true, 'new_eyebrow' => 'Just landed', 'new_title' => 'New arrivals', 'new_count' => 8,
            'show_collections' => true, 'collections_eyebrow' => 'Curated', 'collections_title' => 'Featured collections',
            'show_bestsellers' => true, 'bestsellers_eyebrow' => 'Most loved', 'bestsellers_title' => 'Best sellers', 'bestsellers_count' => 8,
            'show_promos' => true, 'show_story' => false, 'show_assurances' => false, 'show_newsletter' => false,
            // Marketplace-style layout blocks.
            'show_category_rail' => true,
            'show_discover' => true,
            'show_rails' => true, 'rails_count' => 3,
            'show_signup' => true,
            'signup_eyebrow' => 'Finish in 1 minute!',
            'signup_title' => 'Get special deals for signing up',
            'signup_body' => 'Create your URAVEX account to save favourites, track your orders and hear first about new drops and private sales.',
            'signup_cta' => 'Sign up',
            'help_title' => 'Need help?',
            'help_body' => 'Our team replies quickly — message us anytime.',
        ],
        // Uploaded replacements for the default brand files (empty = original assets).
        'branding' => [
            'emblem' => '',
            'logo' => '',
            'show_wordmark' => true,
            'theme' => 'logo',  // storefront colours: logo | navy | onyx | ivory | emerald
        ],
        'payments' => [
            'cod' => ['enabled' => true, 'max_order_minor' => 0, 'fee_minor' => 0, 'note' => 'Pay in cash when your order arrives.'],
            'bank_transfer' => [
                'enabled' => false,
                'bank_name' => '',
                'account_title' => '',
                'account_number' => '',
                'iban' => '',
                'branch' => '',
                'instructions' => 'Transfer the order total and enter your transaction ID below. Your order is confirmed once we verify the payment.',
                'auto_cancel_days' => 3,
            ],
            'sandbox' => ['enabled' => true],
            // Crypto (the store's live methods): shown once a valid wallet address is saved.
            'usdt_trc20' => ['enabled' => true, 'address' => ''],
            'usdt_erc20' => ['enabled' => true, 'address' => ''],
            'btc' => ['enabled' => true, 'address' => ''],
            'eth' => ['enabled' => true, 'address' => ''],
            // Per-method display overrides: code => [label, description, sort].
            'methods' => [],
        ],
        'marketplace' => [
            'applications_open' => true,
            'commission_percent' => '10',  // % kept by URAVEX on each seller sale (line total after discounts)
            'hold_days' => 7,              // days after delivery before earnings can be withdrawn
            'min_withdrawal_minor' => 2000, // in store-currency minor units (20.00)
        ],
        'checkout' => [
            'guest_checkout' => true,
            'tax_percent' => 0,
            'reservation_minutes' => 30,
            'require_phone' => true,
        ],
        'shipping' => [
            'free_threshold_minor' => 500000,
        ],
        // Base currency ('' = DEFAULT_CURRENCY) and optional customer display currencies (code => rate).
        'currency' => ['base' => '', 'display' => [], 'default_display' => '', 'show_switcher' => true, 'auto_rates' => true],
        'inventory' => [
            'default_low_stock' => 5,
        ],
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = Database::get()->all('SELECT `key`, value FROM site_settings');
            $stored = [];
            foreach ($rows as $r) {
                $stored[$r['key']] = json_decode((string) $r['value'], true);
            }
            self::$cache = self::merge(self::DEFAULTS, $stored);
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();
        foreach (explode('.', $key) as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }

    /** Replaces a whole group. Caller is responsible for validation + audit. */
    public static function put(string $group, array $value, ?int $actorId): void
    {
        Database::get()->exec(
            'INSERT INTO site_settings (`key`, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
            [$group, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $actorId]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $k => $v) {
            if (is_array($v) && isset($defaults[$k]) && is_array($defaults[$k]) && !array_is_list($defaults[$k])) {
                $defaults[$k] = self::merge($defaults[$k], $v);
            } else {
                $defaults[$k] = $v;
            }
        }
        return $defaults;
    }
}
