<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;

/**
 * Session-backed authentication. The session stores only the user id and the
 * user's session_version; bumping session_version (password change/reset,
 * disable) logs out every existing session for that user.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('uid');
            $ver = Session::get('uver');
            if (is_int($id)) {
                $u = Database::get()->one(
                    'SELECT id, name, email, phone, role, email_verified_at, disabled, session_version, created_at FROM users WHERE id = ?',
                    [$id]
                );
                if ($u && !$u['disabled'] && (int) $u['session_version'] === $ver) {
                    self::$user = $u;
                } else {
                    Session::forget('uid');
                    Session::forget('uver');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u !== null && Permissions::isStaff($u['role']);
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        return $u !== null && Permissions::allows($u['role'], $permission);
    }

    /** Defence in depth for service-layer checks. */
    public static function authorize(string $permission): array
    {
        $u = self::user();
        if ($u === null || !Permissions::allows($u['role'], $permission)) {
            throw new HttpException(403);
        }
        return $u;
    }

    public static function login(array $user): void
    {
        $guestCart = Session::get('guest_cart_token');
        Session::regenerate();
        Session::put('uid', (int) $user['id']);
        Session::put('uver', (int) $user['session_version']);
        Database::get()->exec('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        self::$loaded = false;
        self::$user = null;
        if (is_string($guestCart)) {
            Session::put('guest_cart_token', $guestCart);
        }
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Test/CLI helper. */
    public static function actingAs(?array $user): void
    {
        self::$user = $user;
        self::$loaded = true;
        if ($user) {
            Session::put('uid', (int) $user['id']);
            Session::put('uver', (int) $user['session_version']);
        }
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($password, $algo);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Constant-ish time dummy verification so "unknown email" and "wrong
     * password" take similar time.
     */
    public static function dummyVerify(string $password): void
    {
        static $hash = null;
        $hash ??= self::hashPassword('dummy-password-for-timing');
        password_verify($password, $hash);
    }
}
