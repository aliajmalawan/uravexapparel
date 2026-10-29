<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Money;
use App\Core\Session;

/** HTML-escape for text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Absolute URL for an app path ("/shop" → "http://host/uravexapparel/shop"). */
function url(string $path = '/', array $query = []): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $p = '/' . ltrim($path, '/');
    $u = Config::get('url') . $p;
    if ($query) {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== []);
        if ($query) {
            $u .= '?' . http_build_query($query);
        }
    }
    return $u;
}

/** Root-relative URL (no scheme/host). */
function path_url(string $path = '/'): string
{
    return (Config::get('base_path') ?: '') . '/' . ltrim($path, '/');
}

/** Static asset with cache-busting version. */
function asset(string $path): string
{
    $file = PUBLIC_PATH . '/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return path_url($path) . '?v=' . $v;
}

/** Public upload URL for a stored image path, at a given width. */
function media(?string $path, int $width = 800): string
{
    if (!$path) {
        return asset('assets/img/placeholder.svg');
    }
    if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'assets/')) {
        return str_starts_with($path, 'assets/') ? asset($path) : $path;
    }
    $sizes = [400, 800, 1600];
    $w = 1600;
    foreach ($sizes as $s) {
        if ($width <= $s) {
            $w = $s;
            break;
        }
    }
    return path_url('uploads/' . $path . '-' . $w . '.webp');
}

function media_srcset(?string $path): string
{
    if (!$path || str_starts_with($path, 'assets/')) {
        return '';
    }
    return implode(', ', array_map(static fn ($w) => media($path, $w) . " {$w}w", [400, 800, 1600]));
}

/** Formats minor units; defaults to the store base currency. */
function money(int|string|null $minor, ?string $currency = null): string
{
    return Money::format((int) $minor, $currency);
}

/** Storefront price: a base-currency amount shown in the visitor's chosen currency. */
function price(int|string|null $baseMinor): string
{
    return App\Services\Currency::format((int) $baseMinor);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** Previously submitted value after a validation redirect. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

function field_error(string $key): ?string
{
    $errors = Session::getFlash('errors', []);
    return is_array($errors) ? ($errors[$key] ?? null) : null;
}

/** Renders aria + error markup for an input. */
function error_attrs(string $key): string
{
    return field_error($key) ? ' aria-invalid="true" aria-describedby="' . e($key) . '-error"' : '';
}

function error_text(string $key): string
{
    $msg = field_error($key);
    return $msg ? '<p class="field-error" id="' . e($key) . '-error">' . e($msg) . '</p>' : '';
}

function flash(string $key): mixed
{
    return Session::getFlash($key);
}

/** Formats a UTC datetime string in the business timezone. */
function dt(?string $utc, string $format = 'j M Y, g:i a'): string
{
    if (!$utc) {
        return '—';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone((string) Config::get('timezone'))) ->format($format);
}

function date_only(?string $utc): string
{
    return dt($utc, 'j M Y');
}

/** Converts a business-timezone "Y-m-d" or "Y-m-d\TH:i" input into a UTC SQL datetime. */
function local_to_utc(?string $local, bool $endOfDay = false): ?string
{
    if (!$local) {
        return null;
    }
    $tz = new DateTimeZone((string) Config::get('timezone'));
    $s = str_replace('T', ' ', $local);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $s .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
    }
    try {
        $d = new DateTimeImmutable($s, $tz);
    } catch (\Throwable) {
        return null;
    }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function utc_to_local_input(?string $utc): string
{
    return $utc ? dt($utc, 'Y-m-d\TH:i') : '';
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function str_limit(?string $s, int $n = 120): string
{
    $s = trim(strip_tags((string) $s));
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = (string) preg_replace('/[^a-z0-9]+/u', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
    return trim($s, '-') ?: 'item';
}

function is_active_path(string $prefix): bool
{
    $current = App\Core\View::shared('currentPath', '/');
    return $prefix === '/' ? $current === '/' : str_starts_with($current, $prefix);
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

/** Inline SVG icon from the shared sprite (aria-hidden; pair with visible/sr text). */
function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="' . e(asset('assets/img/icons.svg')) . '#' . e($name) . '"></use></svg>';
}

function setting(string $key, mixed $default = null): mixed
{
    return App\Services\Settings::get($key, $default);
}

function pluralize(int $n, string $one, ?string $many = null): string
{
    return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
}

/**
 * Outputs stored (already sanitized) rich HTML, rewriting root-relative links
 * ("/contact") to include the app base path so content works in a subfolder.
 */
function rich(?string $html): string
{
    $base = (string) Config::get('base_path');
    $html = (string) $html;
    if ($base === '') {
        return $html;
    }
    return (string) preg_replace('#\bhref="/(?!/)#', 'href="' . $base . '/', $html);
}

/** Header/footer emblem: uploaded replacement or the original brand file. */
function brand_emblem(int $size = 96): string
{
    $custom = (string) App\Services\Settings::get('branding.emblem', '');
    return $custom !== '' ? media($custom, 400) : asset('brand/uravex-emblem-' . (in_array($size, [32, 48, 96, 192, 512], true) ? $size : 96) . '.png');
}

/** Full logo for light backgrounds: uploaded replacement or the original. */
function brand_logo(): string
{
    $custom = (string) App\Services\Settings::get('branding.logo', '');
    return $custom !== '' ? media($custom, 800) : asset('brand/uravex-logo-600.webp');
}
