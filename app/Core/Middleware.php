<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use App\Auth\Permissions;

final class Middleware
{
    /** @param list<string> $names */
    public static function run(array $names, Request $request): void
    {
        if ($request->method === 'POST' && !in_array('nocsrf', $names, true)) {
            Csrf::verify($request);
        }
        foreach ($names as $name) {
            [$kind, $arg] = array_pad(explode(':', $name, 2), 2, null);
            match ($kind) {
                'auth' => self::auth($request),
                'verified' => self::verified($request),
                'guest' => self::guest(),
                'staff' => self::staff($request),
                'perm' => self::perm($request, (string) $arg),
                'nocsrf' => null,
                default => throw new \LogicException("Unknown middleware {$name}"),
            };
        }
    }

    private static function auth(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->method === 'GET') {
                Session::put('intended', $request->path . ($request->query ? '?' . http_build_query($request->query) : ''));
            }
            throw new RedirectException(match (true) {
                $request->path === '/admin' || str_starts_with($request->path, '/admin/') => '/admin/login',
                $request->path === '/seller' || str_starts_with($request->path, '/seller/') => '/seller/login',
                default => '/login',
            });
        }
    }

    private static function verified(Request $request): void
    {
        self::auth($request);
        if (!Auth::user()['email_verified_at']) {
            Session::flash('warning', 'Please verify your email address to continue.');
            throw new RedirectException('/account');
        }
    }

    private static function guest(): void
    {
        if (Auth::check()) {
            throw new RedirectException(Auth::isStaff() ? '/admin' : '/account');
        }
    }

    private static function staff(Request $request): void
    {
        self::auth($request);
        if (!Auth::isStaff()) {
            if ($request->method === 'GET' && ($request->path === '/admin' || str_starts_with($request->path, '/admin/'))) {
                Session::flash('warning', 'You are signed in as a customer or seller. Sign in with an admin account to open the dashboard.');
                throw new RedirectException('/admin/login');
            }
            throw new HttpException(403);
        }
    }

    private static function perm(Request $request, string $permission): void
    {
        self::staff($request);
        if (!Permissions::allows(Auth::user()['role'], $permission)) {
            throw new HttpException(403);
        }
    }
}
