<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestController;
use JPI\HTTP\Input;
use JPI\HTTP\Request;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JPI\CRUD\API\AbstractController::parseFieldsAttribute
 */
final class ControllerTest extends TestCase {

    private TestController $controller;
    private Request $request;

    protected function setUp(): void {
        $this->controller = new TestController();
        $this->request = new Request([], [], [], []);
        $this->controller->setRequest($this->request);
    }

    public function testActionsRequireAuthentication(): void {
        $controller = $this->controller;

        // Make all protected
        $reflectionClass = new \ReflectionClass($controller);
        $property = $reflectionClass->getProperty("publicActions");
        $property->setValue($controller, []);

        $this->assertSame(401, $controller->index()->getStatusCode());
        $this->assertSame(401, $controller->create()->getStatusCode());
        $this->assertSame(401, $controller->read(1)->getStatusCode());
        $this->assertSame(401, $controller->update(1)->getStatusCode());
        $this->assertSame(401, $controller->delete(1)->getStatusCode());
    }

    public function testParseFields(): void {
        $this->request->setQueryParams(new Input(["fields" => "name, description ,status"]));

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod("parseFieldsAttribute");
        $method->invoke($this->controller);

        $fields = $this->request->getAttribute("fields");
        $this->assertSame(["name", "description", "status"], $fields);
    }

    public function testParseFieldsEmptyString(): void {
        $this->request->setQueryParams(new Input(["fields" => ""]));

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod("parseFieldsAttribute");
        $method->invoke($this->controller);

        $fields = $this->request->getAttribute("fields");
        $this->assertNull($fields);
    }

    public function testParseFieldsArray(): void {
        $this->request->setQueryParams(new Input(["fields" => ["name", "description"]]));

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod("parseFieldsAttribute");
        $method->invoke($this->controller);

        $fields = $this->request->getAttribute("fields");
        $this->assertNull($fields);
    }
}
