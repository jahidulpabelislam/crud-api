<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

class App extends \JPI\HTTP\App {

    public function addCRUDRoutes(string $basePath, string $controller, ?string $name = null): void {
        $this->router->addCRUDRoutes($basePath, $controller, $name);
    }
}
