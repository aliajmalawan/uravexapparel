<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typed, validated configuration. Production refuses to boot with missing or
 * placeholder secrets so a misconfigured deploy fails loudly instead of running
 * insecurely.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $c = [];

    public static function boot(): void
    {
        $env = Env::get('APP_ENV', 'development');
        $url = rtrim((string) Env::get('APP_URL', 'http://localhost/uravexapparel'), '/');
        $parts = parse_url($url) ?: [];

        self::$c = [
            'env' => $env,
            'debug' => $env !== 'production' && Env::get('APP_DEBUG', 'true') === 'true',
            'url' => $url,
            'base_path' => rtrim($parts['path'] ?? '', '/'),
            'https' => ($parts['scheme'] ?? 'http') === 'https',
            'key' => (string) Env::get('APP_KEY', ''),
            'timezone' => (string) Env::get('BUSINESS_TIMEZONE', 'Asia/Karachi'),
            'currency' => (string) Env::get('DEFAULT_CURRENCY', 'PKR'),
            'trusted_proxy_hops' => (int) Env::get('TRUSTED_PROXY_HOPS', '0'),
            'db' => [
                'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
                'port' => (int) Env::get('DB_PORT', '3306'),
                'name' => (string) Env::get('DB_DATABASE', 'uravexapparel'),
                'user' => (string) Env::get('DB_USERNAME', 'root'),
                'pass' => (string) Env::get('DB_PASSWORD', ''),
            ],
            'mail' => [
                'driver' => (string) Env::get('MAIL_DRIVER', 'outbox'),
                'from_address' => (string) Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
                'from_name' => (string) Env::get('MAIL_FROM_NAME', 'URAVEX Apparel'),
                'admin_notify' => (string) Env::get('MAIL_ADMIN_NOTIFY', ''),
                'smtp_host' => (string) Env::get('SMTP_HOST', ''),
                'smtp_port' => (int) Env::get('SMTP_PORT', '587'),
                'smtp_encryption' => (string) Env::get('SMTP_ENCRYPTION', 'tls'),
                'smtp_user' => (string) Env::get('SMTP_USERNAME', ''),
                'smtp_pass' => (string) Env::get('SMTP_PASSWORD', ''),
            ],
            'payments' => [
                'mode' => (string) Env::get('PAYMENT_MODE', 'sandbox'),
                'sandbox_secret' => (string) Env::get('SANDBOX_WEBHOOK_SECRET', ''),
            ],
            'cron_secret' => (string) Env::get('CRON_SECRET', ''),
            'uploads' => [
                'max_image_bytes' => (int) Env::get('UPLOAD_MAX_IMAGE_BYTES', (string) (8 * 1024 * 1024)),
                'max_receipt_bytes' => (int) Env::get('UPLOAD_MAX_RECEIPT_BYTES', (string) (5 * 1024 * 1024)),
            ],
        ];

        if ($env === 'production') {
            self::assertProduction();
        }
    }

    private static function assertProduction(): void
    {
        $problems = [];
        if (strlen(self::$c['key']) < 32 || str_contains(self::$c['key'], 'CHANGE_ME')) {
            $problems[] = 'APP_KEY must be a random value of at least 32 characters.';
        }
        if (!self::$c['https']) {
            $problems[] = 'APP_URL must use https:// in production.';
        }
        if (self::$c['db']['user'] === 'root') {
            $problems[] = 'DB_USERNAME must be a least-privilege user, not root.';
        }
        if (self::$c['mail']['driver'] === 'outbox') {
            $problems[] = 'MAIL_DRIVER=outbox is development-only; configure smtp.';
        }
        if (strlen(self::$c['cron_secret']) < 24) {
            $problems[] = 'CRON_SECRET must be set (24+ random characters).';
        }
        if ($problems) {
            error_log('[config] ' . implode(' ', $problems));
            http_response_code(500);
            exit("Configuration error. See server log.\n");
        }
    }

    /**
     * Aligns the configured URL with how the visitor actually reached the site:
     *  - APP_URL written as http:// on an HTTPS site is upgraded to https://, otherwise forms post to
     *    http:// where the browser withholds the secure session cookie and sign-in loops back.
     *  - On the www / non-www twin of APP_URL's host, returns the canonical URL to redirect to, because
     *    the session cookie is host-only and would not reach the canonical host the forms post to.
     */
    public static function adaptTo(bool $https, string $host, string $requestUri): ?string
    {
        $parts = parse_url((string) self::$c['url']) ?: [];
        if ($https && ($parts['scheme'] ?? '') === 'http') {
            self::$c['url'] = 'https://' . substr((string) self::$c['url'], 7);
            self::$c['https'] = true;
        }
        $canonical = strtolower((string) ($parts['host'] ?? ''));
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
        if ($canonical === '' || $host === '' || $host === $canonical || !in_array($host, ['www.' . $canonical, preg_replace('/^www\./', '', $canonical)], true) || !str_contains($canonical, '.')) {
            return null;
        }
        $base = (string) self::$c['url'];
        $path = (string) self::$c['base_path'];
        $uri = '/' . ltrim($requestUri, '/');
        if ($path !== '' && str_starts_with($uri, $path)) {
            $uri = substr($uri, strlen($path)) ?: '/';
        }
        return $base . $uri;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$c;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$c;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = $ref[$segment] ?? [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function isProduction(): bool
    {
        return self::$c['env'] === 'production';
    }
}
