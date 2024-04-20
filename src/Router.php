<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\HTTP\Router as BaseRouter;
use JPI\HTTP\Request;
use JPI\HTTP\Response;

/**
 * Extended router with default error handling for 404 & 405.
 */
class Router extends BaseRouter {

    protected function __construct(protected Request $request) {
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
}
