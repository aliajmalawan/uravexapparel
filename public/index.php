<?php

declare(strict_types=1);

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Services\Audit;

require dirname(__DIR__) . '/app/bootstrap.php';

$request = Request::capture();

try {
    Session::start($request);
    Audit::context($request->ip(), $request->userAgent());
    View::share('currentPath', $request->path);
    View::share('request', $request);

    $router = new Router();
    require APP_PATH . '/routes.php';
    $response = $router->dispatch($request);
} catch (\Throwable $e) {
    $response = ErrorHandler::toResponse($e, $request);
}

Security::headers($response, $request);
$response->send();
