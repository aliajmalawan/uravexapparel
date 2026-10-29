<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Cart;
use App\Services\Settings;

abstract class Controller
{
    /**
     * Renders a storefront page. $meta keys: title, description, canonical,
     * image, type, noindex, jsonld (list of arrays).
     */
    protected function view(string $template, array $data = [], array $meta = [], string $layout = 'layouts/store', int $status = 200): Response
    {
        $this->shareChrome();
        $seo = Settings::get('seo', []);
        $title = $meta['title'] ?? null;
        $meta += [
            'full_title' => $title ? $title . ' | URAVEX Apparel' : ($seo['default_title'] ?? 'URAVEX Apparel'),
            'description' => $seo['default_description'] ?? '',
            'canonical' => null,
            'image' => !empty($seo['og_image']) ? media($seo['og_image'], 1600) : url('/brand/uravex-logo-1200.png'),
            'type' => 'website',
            'noindex' => false,
            'jsonld' => [],
        ];
        if ($meta['image'] && !preg_match('#^https?://#', $meta['image'])) {
            $meta['image'] = rtrim((string) Config::get('url'), '/') . substr($meta['image'], strlen((string) Config::get('base_path')));
        }
        $meta['description'] = str_limit((string) $meta['description'], 160);
        return Response::html(View::render($template, $data + ['meta' => $meta], $layout), $status);
    }

    protected function shareChrome(): void
    {
        try {
            \App\Services\ExchangeRates::refreshIfStale(true); // shoppers: only when no rates exist yet
        } catch (\Throwable) {
        }
        View::share('user', Auth::user());
        View::share('cartCount', Cart::count());
        View::share('settings', Settings::all());
    }

    protected function back(string $fallback = '/'): Response
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $base = (string) Config::get('url');
        return Response::redirect($ref !== '' && str_starts_with($ref, $base) ? $ref : $fallback);
    }

    protected function redirect(string $to, ?string $success = null, ?string $error = null): Response
    {
        if ($success) {
            Session::flash('success', $success);
        }
        if ($error) {
            Session::flash('error', $error);
        }
        return Response::redirect($to);
    }

    /** Whitelists an internal redirect target (prevents open redirects). */
    protected function safeNext(?string $next, string $fallback): string
    {
        if (!$next || !preg_match('#^/[A-Za-z0-9/_\-?=&.%]*$#', $next) || str_starts_with($next, '//')) {
            return $fallback;
        }
        return $next;
    }
}
