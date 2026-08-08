<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\HTTP\Request;
use JPI\HTTP\Response;
use JPI\HTTP\Router as BaseRouter;

/**
 * Extended router with default error handling for 404 & 405.
 */
class Router extends BaseRouter {

    public function __construct(protected Request $request) {
        $this->notFoundHandler = function (Request $request) {
            return Response::json(404, [
                "message" => "Unrecognised URI ({$request->getPath()}).",
            ]);
        };
        $this->methodNotAllowedHandler = function (Request $request) {
            return Response::json(405, [
                "message" => "Method {$request->getMethod()} not allowed on {$request->getPath()}.",
            ]);
        };
    }

    public function addCRUDRoutes(string $basePath, string $controller, ?string $name = null): void {
        $basePath = trim($basePath, "/");

        $this->addRoute("/$basePath/", "GET", [new AuthenticationDecoratedController($controller, "index"), "passthrough"]);
        $this->addRoute("/$basePath/", "POST", [new AuthenticationDecoratedController($controller, "create"), "passthrough"]);
        $this->addRoute("/$basePath/{id}/", "GET", [new AuthenticationDecoratedController($controller, "read"), "passthrough"], $name);
        $this->addRoute("/$basePath/{id}/", "PUT", [new AuthenticationDecoratedController($controller, "update"), "passthrough"]);
        $this->addRoute("/$basePath/{id}/", "DELETE", [new AuthenticationDecoratedController($controller, "delete"), "passthrough"]);
    }
}
