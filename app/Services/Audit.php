<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Logger;

/**
 * Append-only audit trail for sensitive staff actions. Values are redacted
 * (passwords, tokens, secrets never stored).
 */
final class Audit
{
    private static ?string $ip = null;
    private static ?string $ua = null;

    public static function context(string $ip, string $ua): void
    {
        self::$ip = $ip;
        self::$ua = $ua;
    }

    public static function log(string $action, string $entityType, int|string|null $entityId, ?array $before = null, ?array $after = null): void
    {
        $u = Auth::user();
        Database::get()->insert(
            'INSERT INTO admin_audit_logs (actor_id, actor_email, action, entity_type, entity_id, before_data, after_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $u['id'] ?? null,
                $u['email'] ?? 'system',
                $action,
                $entityType,
                $entityId === null ? null : (string) $entityId,
                $before === null ? null : self::encode($before),
                $after === null ? null : self::encode($after),
                self::$ip,
                self::$ua,
            ]
        );
    }

    /** Returns only the keys whose values changed (for compact before/after). */
    public static function diff(array $before, array $after): array
    {
        $b = [];
        $a = [];
        foreach ($after as $k => $v) {
            if (!array_key_exists($k, $before) || (string) json_encode($before[$k]) !== (string) json_encode($v)) {
                $b[$k] = $before[$k] ?? null;
                $a[$k] = $v;
            }
        }
        return [$b, $a];
    }

    private static function encode(array $data): string
    {
        $data = Logger::redact($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }
}
