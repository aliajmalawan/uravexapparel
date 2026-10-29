<?php

declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('debug') && PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /** Converts any throwable into a user-safe response. */
    public static function toResponse(\Throwable $e, Request $request): Response
    {
        if ($e instanceof RedirectException) {
            return Response::redirect($e->to);
        }
        if ($e instanceof ValidationException) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors], 422);
            }
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());
            Session::flash('old', self::safeOld($request->post));
            return Response::redirect(self::back($request));
        }

        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status >= 500) {
            $ref = substr(bin2hex(random_bytes(6)), 0, 10);
            Logger::error('Unhandled exception', [
                'ref' => $ref,
                'type' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path,
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
            ]);
        }
        $message = $e instanceof HttpException ? $e->getMessage() : 'Something went wrong on our side. Please try again.';

        if ($request->wantsJson()) {
            $r = Response::json(['ok' => false, 'message' => $message], $status);
        } else {
            $view = in_array($status, [403, 404, 419, 429], true) ? 'errors/' . $status : 'errors/500';
            try {
                $layout = str_starts_with($request->path, '/admin') && $status !== 404 ? 'layouts/minimal' : 'layouts/store';
                $html = View::render($view, [
                    'title' => HttpException::defaultMessage($status),
                    'message' => $message,
                    'debug' => Config::get('debug') && !($e instanceof HttpException) ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : null,
                    'ref' => $ref ?? null,
                    'noindex' => true,
                ], $layout);
            } catch (\Throwable $inner) {
                Logger::error('Error page failed', ['message' => $inner->getMessage(), 'file' => $inner->getFile() . ':' . $inner->getLine()]);
                $html = '<!doctype html><meta charset="utf-8"><title>Error</title><p style="font-family:sans-serif;padding:2rem">' . e($message) . '</p>';
            }
            $r = Response::html($html, $status);
        }
        if ($e instanceof HttpException) {
            foreach ($e->headers as $k => $v) {
                $r->header($k, $v);
            }
        }
        return $r;
    }

    private static function back(Request $request): string
    {
        $ref = (string) $request->header('Referer');
        $base = (string) Config::get('url');
        if ($ref !== '' && str_starts_with($ref, $base)) {
            return $ref;
        }
        return $request->path;
    }

    /** Never re-populate secrets into forms. */
    private static function safeOld(array $post): array
    {
        unset($post['_token'], $post['password'], $post['password_confirmation'], $post['current_password']);
        return $post;
    }
}
