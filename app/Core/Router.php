<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small router with per-route middleware.
 *
 * Middleware strings:
 *   auth            – signed-in customer or staff
 *   guest           – only when signed out
 *   verified        – signed in with verified email
 *   perm:<name>     – staff with the named permission (see Auth\Permissions)
 *   nocsrf          – skip CSRF check (signed webhooks only)
 */
final class Router
{
    /** @var list<array{method:string,regex:string,keys:list<string>,handler:array|callable,mw:list<string>}> */
    private array $routes = [];
    private array $groupMw = [];
    private string $groupPrefix = '';

    public function get(string $path, array|callable $handler, array $mw = []): void
    {
        $this->add('GET', $path, $handler, $mw);
    }

    public function post(string $path, array|callable $handler, array $mw = []): void
    {
        $this->add('POST', $path, $handler, $mw);
    }

    public function group(string $prefix, array $mw, callable $define): void
    {
        [$p, $m] = [$this->groupPrefix, $this->groupMw];
        $this->groupPrefix = $p . $prefix;
        $this->groupMw = array_merge($m, $mw);
        $define($this);
        [$this->groupPrefix, $this->groupMw] = [$p, $m];
    }

    private function add(string $method, string $path, array|callable $handler, array $mw): void
    {
        $full = '/' . trim($this->groupPrefix . $path, '/');
        $keys = [];
        // Parameter patterns may contain regex quantifiers such as {24} or {2,5}.
        $regex = preg_replace_callback('#\{([a-z_]+)(?::((?:[^{}]|\{\d+(?:,\d*)?\})+))?\}#', static function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '(' . ($m[2] ?? '[^/]+') . ')';
        }, $full);
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'keys' => $keys,
            'handler' => $handler,
            'mw' => array_merge($this->groupMw, $mw),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method && !($route['method'] === 'GET' && $request->method === 'HEAD')) {
                $allowed[] = $route['method'];
                continue;
            }
            array_shift($m);
            $request->params = array_combine($route['keys'], array_map('strval', $m)) ?: [];

            Middleware::run($route['mw'], $request);

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $result = (new $class())->$action($request);
            } else {
                $result = $handler($request);
            }
            return $result instanceof Response ? $result : Response::html((string) $result);
        }
        if ($allowed) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        $lower = strtolower($request->path);
        if ($request->method === 'GET' && $lower !== $request->path && preg_match('#^/[a-z0-9/_-]*$#', $lower)) {
            throw new RedirectException($lower);
        }
        throw new HttpException(404);
    }
}
