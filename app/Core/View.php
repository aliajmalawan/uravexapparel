<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templates. Templates must escape output with e(); raw HTML is only
 * printed for values that went through Sanitizer::html().
 */
final class View
{
    /** @var array<string,mixed> Shared with every template. */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/store'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        $vars = self::$shared + $data;
        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($file, $vars);
    }
}
