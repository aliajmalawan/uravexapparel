<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Core\Validator;

/**
 * Validation schema for each editable settings group. Groups are split by
 * the permission that may edit them (content vs. owner-only store settings).
 */
final class SettingsSchema
{
    public const CONTENT_GROUPS = ['announcement', 'store', 'social', 'footer', 'seo', 'home', 'navigation', 'sections', 'branding'];
    public const OWNER_GROUPS = ['checkout', 'shipping', 'payments_cod', 'payments_bank_transfer', 'payments_sandbox', 'payments_methods', 'inventory', 'marketplace'];

    private const RULES = [
        'announcement' => ['enabled' => 'bool', 'text' => 'string|max:200', 'link' => 'link|max:255'],
        'store' => ['name' => 'required|string|max:120', 'tagline' => 'string|max:191', 'support_email' => 'required|email', 'support_phone' => 'string|max:40', 'whatsapp' => 'string|max:40', 'address' => 'string|max:500', 'business_hours' => 'string|max:120'],
        'social' => ['instagram' => 'url|max:255', 'facebook' => 'url|max:255', 'tiktok' => 'url|max:255', 'youtube' => 'url|max:255', 'linkedin' => 'url|max:255'],
        'footer' => ['about' => 'string|max:500', 'copyright' => 'string|max:191'],
        'seo' => ['default_title' => 'required|string|max:120', 'default_description' => 'string|max:320'],
        'home' => [
            'story_eyebrow' => 'string|max:80', 'story_title' => 'string|max:160', 'story_body' => 'string|max:2000',
            'story_cta_label' => 'string|max:60', 'story_cta_href' => 'link|max:255',
            'vision_title' => 'string|max:80', 'vision_body' => 'string|max:600', 'history_title' => 'string|max:80', 'history_body' => 'string|max:600',
            'newsletter_title' => 'string|max:120', 'newsletter_body' => 'string|max:300',
        ],
        'navigation' => ['footer_pages_title' => 'string|max:60'],
        'sections' => [
            'hero_eyebrow' => 'string|max:80', 'hero_title' => 'string|max:160', 'hero_subtitle' => 'string|max:320',
            'show_new' => 'bool', 'new_eyebrow' => 'string|max:80', 'new_title' => 'string|max:120', 'new_count' => 'required|int|min:1|max:24',
            'show_collections' => 'bool', 'collections_eyebrow' => 'string|max:80', 'collections_title' => 'string|max:120',
            'show_bestsellers' => 'bool', 'bestsellers_eyebrow' => 'string|max:80', 'bestsellers_title' => 'string|max:120', 'bestsellers_count' => 'required|int|min:1|max:24',
            'show_promos' => 'bool', 'show_story' => 'bool', 'show_assurances' => 'bool', 'show_newsletter' => 'bool',
            'show_category_rail' => 'bool', 'show_discover' => 'bool', 'show_rails' => 'bool', 'rails_count' => 'int|min:1|max:8',
            'show_signup' => 'bool', 'signup_eyebrow' => 'string|max:60', 'signup_title' => 'string|max:120', 'signup_body' => 'string|max:400', 'signup_cta' => 'string|max:40',
            'help_title' => 'string|max:60', 'help_body' => 'string|max:200',
        ],
        'branding' => ['show_wordmark' => 'bool', 'theme' => 'string|in:logo,navy,onyx,ivory,emerald'],
        'checkout' => ['guest_checkout' => 'bool', 'tax_percent' => 'int|min:0|max:50', 'reservation_minutes' => 'required|int|min:5|max:1440', 'require_phone' => 'bool'],
        'shipping' => ['free_threshold' => 'money'],
        'payments_cod' => ['enabled' => 'bool', 'max_order' => 'money', 'note' => 'string|max:200'],
        'payments_bank_transfer' => ['enabled' => 'bool', 'bank_name' => 'string|max:120', 'account_title' => 'string|max:120', 'account_number' => 'string|max:60', 'iban' => 'string|max:40|regex:/^[A-Za-z0-9 ]*$/', 'branch' => 'string|max:160', 'instructions' => 'string|max:1000', 'auto_cancel_days' => 'int|min:0|max:30'],
        'payments_sandbox' => ['enabled' => 'bool'],
        'payments_methods' => [],
        'inventory' => ['default_low_stock' => 'int|min:0|max:1000'],
        'marketplace' => ['applications_open' => 'bool', 'commission_percent' => 'required|string|max:6|regex:/^\d{1,2}(\.\d{1,2})?$/', 'hold_days' => 'required|int|min:0|max:90', 'min_withdrawal' => 'required|money'],
    ];

    public static function exists(string $group): bool
    {
        return isset(self::RULES[$group]);
    }

    /**
     * Validates input for a group and returns [storageKey, mergedValue].
     * @return array{0:string,1:array}
     */
    public static function build(string $group, array $input): array
    {
        $d = Validator::make($input, self::RULES[$group])->validated();
        $d = array_map(static fn ($x) => $x ?? '', $d);
        switch ($group) {
            case 'marketplace':
                if (bccomp($d['commission_percent'], '90', 2) > 0) {
                    throw ValidationException::one('commission_percent', 'Commission can be at most 90%.');
                }
                return ['marketplace', ['applications_open' => $d['applications_open'], 'commission_percent' => $d['commission_percent'], 'hold_days' => (int) $d['hold_days'], 'min_withdrawal_minor' => (int) $d['min_withdrawal']]];
            case 'shipping':
                return ['shipping', ['free_threshold_minor' => (int) ($d['free_threshold'] ?: 0)]];
            case 'payments_cod':
                return ['payments', array_replace_recursive(Settings::get('payments', []), ['cod' => ['enabled' => $d['enabled'], 'max_order_minor' => (int) ($d['max_order'] ?: 0), 'note' => $d['note']]])];
            case 'payments_bank_transfer':
                if ($d['enabled'] && ($d['bank_name'] === '' || $d['account_title'] === '' || ($d['account_number'] === '' && $d['iban'] === ''))) {
                    throw ValidationException::one('bank_name', 'Enter the bank name, account title and an account number or IBAN before enabling bank transfer.');
                }
                $d['auto_cancel_days'] = (int) ($d['auto_cancel_days'] ?: 0);
                return ['payments', array_replace_recursive(Settings::get('payments', []), ['bank_transfer' => $d])];
            case 'payments_methods':
                $methods = [];
                $payments = Settings::get('payments', []);
                foreach (\App\Payments\Registry::all() as $code => $p) {
                    $row = (array) ($input['methods'][$code] ?? []);
                    $methods[$code] = [
                        'label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 80),
                        'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 200),
                        'sort' => max(0, min(999, (int) ($row['sort'] ?? 100))),
                    ];
                    if ($p->isImplemented()) {
                        $payments[$code]['enabled'] = !empty($row['enabled']);
                    }
                    if ($p instanceof \App\Payments\CryptoTransferProvider) {
                        $address = preg_replace('/\s+/', '', (string) ($row['address'] ?? ''));
                        if ($address !== '' && !\App\Payments\CryptoTransferProvider::validAddress($code, $address)) {
                            throw ValidationException::one('methods', 'The wallet address for ' . $p->defaultLabel() . ' is not a valid ' . $p->network() . ' address.');
                        }
                        $payments[$code]['address'] = $address;
                    }
                }
                if (!empty($payments['bank_transfer']['enabled']) && !\App\Payments\Registry::get('bank_transfer')->isConfigured()) {
                    throw ValidationException::one('methods', 'Enter the bank account details below before enabling Bank Transfer.');
                }
                $payments['methods'] = $methods;
                return ['payments', $payments];
            case 'payments_sandbox':
                return ['payments', array_replace_recursive(Settings::get('payments', []), ['sandbox' => $d])];
            case 'home':
                $as = [];
                foreach ((array) ($input['assurances'] ?? []) as $a) {
                    $t = mb_substr(trim((string) ($a['title'] ?? '')), 0, 60);
                    if ($t === '') {
                        continue;
                    }
                    $icon = in_array($a['icon'] ?? '', ['truck', 'refresh', 'shield', 'chat', 'check', 'box', 'cash', 'tag'], true) ? $a['icon'] : 'check';
                    $as[] = ['icon' => $icon, 'title' => $t, 'text' => mb_substr(trim((string) ($a['text'] ?? '')), 0, 120)];
                }
                $d['assurances'] = array_slice($as, 0, 4);
                $d['story_image'] = Settings::get('home.story_image', '');
                return ['home', $d];
            case 'navigation':
                $d['header'] = self::links((array) ($input['header'] ?? []), 10);
                if (!$d['header']) {
                    throw ValidationException::one('header', 'Keep at least one link in the header menu.');
                }
                $cols = [];
                foreach (array_slice((array) ($input['footer'] ?? []), 0, 3) as $col) {
                    $title = mb_substr(trim((string) ($col['title'] ?? '')), 0, 60);
                    $links = self::links((array) ($col['links'] ?? []), 8);
                    if ($title !== '' || $links) {
                        $cols[] = ['title' => $title, 'links' => $links];
                    }
                }
                $d['footer'] = $cols;
                return ['navigation', $d];
            case 'branding':
                return ['branding', $d + ['emblem' => Settings::get('branding.emblem', ''), 'logo' => Settings::get('branding.logo', '')]];
            case 'checkout':
                $d['tax_percent'] = (int) ($d['tax_percent'] ?: 0);
                return ['checkout', $d];
            case 'seo':
                $d['og_image'] = Settings::get('seo.og_image', '');
                return ['seo', $d];
            default:
                return [$group, $d];
        }
    }

    /**
     * Cleans a list of {label, href} rows: drops blanks, validates links
     * (internal "/path" or http(s) URL only — never javascript:).
     *
     * @return list<array{label:string, href:string}>
     */
    private static function links(array $rows, int $max): array
    {
        $out = [];
        foreach ($rows as $row) {
            $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 60);
            $href = trim((string) ($row['href'] ?? ''));
            if ($label === '' && $href === '') {
                continue;
            }
            if ($label === '' || !(preg_match('#^/(?!/)[^\s]*$#', $href) || preg_match('#^https?://[^\s]+$#i', $href))) {
                throw ValidationException::one('links', "Each menu link needs a label and a link starting with / or https:// (check \"" . ($label ?: $href) . "\").");
            }
            $out[] = ['label' => $label, 'href' => mb_substr($href, 0, 255)];
        }
        return array_slice($out, 0, $max);
    }
}
