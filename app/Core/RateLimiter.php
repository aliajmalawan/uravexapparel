<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiter persisted in MySQL so limits survive restarts and
 * apply across PHP workers.
 */
final class RateLimiter
{
    /**
     * Records a hit and throws 429 when $max hits in $windowSeconds is exceeded.
     */
    public static function hit(string $bucket, int $max, int $windowSeconds): void
    {
        if (!self::attempt($bucket, $max, $windowSeconds)) {
            $retry = $windowSeconds - (time() % $windowSeconds);
            throw new HttpException(429, 'Too many attempts. Please try again in ' . self::humanize($retry) . '.', ['Retry-After' => (string) $retry]);
        }
    }

    /** Returns false when the limit is exceeded (and records the hit either way). */
    public static function attempt(string $bucket, int $max, int $windowSeconds): bool
    {
        $db = Database::get();
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        $key = substr(hash('sha256', $bucket), 0, 40) . ':' . $windowSeconds;
        $db->exec(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$key, $window]
        );
        $hits = (int) $db->value('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$key, $window]);
        if (random_int(1, 200) === 1) {
            $db->exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400 * 2]);
        }
        return $hits <= $max;
    }

    public static function tooMany(string $bucket, int $max, int $windowSeconds): bool
    {
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        $key = substr(hash('sha256', $bucket), 0, 40) . ':' . $windowSeconds;
        $hits = (int) Database::get()->value('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$key, $window]);
        return $hits >= $max;
    }

    public static function clear(string $bucket, int $windowSeconds): void
    {
        $key = substr(hash('sha256', $bucket), 0, 40) . ':' . $windowSeconds;
        Database::get()->exec('DELETE FROM rate_limits WHERE bucket = ?', [$key]);
    }

    private static function humanize(int $seconds): string
    {
        if ($seconds < 90) {
            return $seconds . ' seconds';
        }
        return (int) ceil($seconds / 60) . ' minutes';
    }
}
