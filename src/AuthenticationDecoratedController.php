<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\HTTP\Request;
use JPI\HTTP\Response;

final class AuthenticationDecoratedController
{
    public function __construct(private string $controller, private string $action) {
    }

    public function getNotAuthorisedResponse(): Response {
        return Response::json(401, [
            "message" => "You need to be logged in!",
        ]);
    }

    public function passthrough(Request $request, ...$routeParams): Response {
        $controller = new $this->controller();
        $controller->setRequest($request);

        if (
            !in_array($this->action, $controller->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        return $controller->{$this->action}(...$routeParams);
    }
}
