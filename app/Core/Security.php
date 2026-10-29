<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    /** Extra origins allowed as form-action targets (payment provider pages). */
    public const PAYMENT_FORM_ORIGINS = [];

    public static function headers(Response $response, Request $request): void
    {
        $formAction = implode(' ', array_merge(["'self'"], self::PAYMENT_FORM_ORIGINS));
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action {$formAction}",
            "object-src 'none'",
        ]);
        $defaults = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isHttps()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        $private = str_starts_with($request->path, '/admin') || str_starts_with($request->path, '/account')
            || str_starts_with($request->path, '/checkout') || str_starts_with($request->path, '/cart');
        if ($private) {
            $defaults['Cache-Control'] = 'no-store, private';
            $defaults['X-Robots-Tag'] = 'noindex, nofollow';
        }
        foreach ($defaults as $k => $v) {
            if (!isset($response->headers[$k])) {
                $response->headers[$k] = $v;
            }
        }
        header_remove('X-Powered-By');
    }
}
