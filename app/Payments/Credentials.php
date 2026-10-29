<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

/**
 * Payment gateway credentials entered in Admin → Settings → Payments.
 *
 * Values are encrypted with AES-256-GCM using a key derived from APP_KEY and
 * stored in site_settings ("payment_credentials"). They are never sent back to
 * the browser — the admin only sees whether each key is set. A real environment
 * variable / .env value with the same name takes precedence.
 */
final class Credentials
{
    private const SETTING = 'payment_credentials';

    private static function key(): string
    {
        $appKey = (string) Config::get('key');
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('APP_KEY must be set (32+ characters) to store payment credentials.');
        }
        return hash_hkdf('sha256', $appKey, 32, 'uravex-payment-credentials');
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): ?string
    {
        if (!str_starts_with($stored, 'v1:')) {
            return null;
        }
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    /** @return array<string,string> name => ciphertext */
    private static function stored(): array
    {
        $v = Database::get()->value('SELECT value FROM site_settings WHERE `key` = ?', [self::SETTING]);
        $d = $v ? json_decode((string) $v, true) : [];
        return is_array($d) ? $d : [];
    }

    /** Credential value: environment first, then the encrypted admin value. */
    public static function get(string $name): ?string
    {
        $env = (string) Env::get($name, '');
        if ($env !== '') {
            return $env;
        }
        $c = self::stored()[$name] ?? null;
        return is_string($c) ? self::decrypt($c) : null;
    }

    public static function source(string $name): ?string
    {
        if ((string) Env::get($name, '') !== '') {
            return 'env';
        }
        return isset(self::stored()[$name]) ? 'admin' : null;
    }

    /**
     * Saves submitted values. Blank fields keep the existing value; names in
     * $clear are removed. Only names in $allowed are accepted.
     *
     * @param array<string,string> $values
     * @param list<string> $allowed
     * @param list<string> $clear
     * @return list<string> names that changed
     */
    public static function save(array $values, array $allowed, array $clear, ?int $actorId): array
    {
        $data = self::stored();
        $changed = [];
        foreach ($allowed as $name) {
            if (in_array($name, $clear, true) && isset($data[$name])) {
                unset($data[$name]);
                $changed[] = $name;
                continue;
            }
            $v = trim((string) ($values[$name] ?? ''));
            if ($v !== '') {
                if (strlen($v) > 2000 || preg_match('/[\x00-\x1F]/', $v)) {
                    throw \App\Core\ValidationException::one('credentials', "The value for {$name} is not valid.");
                }
                $data[$name] = self::encrypt($v);
                $changed[] = $name;
            }
        }
        Database::get()->exec(
            'INSERT INTO site_settings (`key`, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
            [self::SETTING, json_encode($data, JSON_UNESCAPED_SLASHES), $actorId]
        );
        \App\Services\Settings::flush();
        return $changed;
    }
}
