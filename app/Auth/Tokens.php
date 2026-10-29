<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;

/**
 * Expiring, single-use tokens for email verification and password reset.
 * Only the SHA-256 of the token is stored; the raw token exists only in the
 * emailed link. Tokens are never logged.
 */
final class Tokens
{
    public const VERIFY_TTL = 60 * 60 * 24; // 24h
    public const RESET_TTL = 60 * 60;       // 60 min

    public static function issue(int $userId, string $type): string
    {
        $db = Database::get();
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ttl = $type === 'reset_password' ? self::RESET_TTL : self::VERIFY_TTL;
        // A new token supersedes any outstanding token of the same type.
        $db->exec('UPDATE user_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND type = ? AND used_at IS NULL', [$userId, $type]);
        $db->insert(
            'INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $type, hash('sha256', $raw), $ttl]
        );
        return $raw;
    }

    /** Returns the token row if valid and unused (does not consume). */
    public static function peek(string $raw, string $type): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/', $raw)) {
            return null;
        }
        return Database::get()->one(
            'SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $raw), $type]
        );
    }

    /**
     * Atomically consumes a token; returns the user id or null. The conditional
     * UPDATE guarantees single use even under concurrent requests.
     */
    public static function consume(string $raw, string $type): ?int
    {
        $row = self::peek($raw, $type);
        if (!$row) {
            return null;
        }
        $n = Database::get()->exec(
            'UPDATE user_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$row['id']]
        );
        return $n === 1 ? (int) $row['user_id'] : null;
    }
}
