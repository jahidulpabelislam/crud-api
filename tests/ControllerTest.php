<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestController;
use JPI\HTTP\Request;
use JPI\HTTP\Response;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase {

    private TestController $controller;
    private Request $request;

    protected function setUp(): void {
        $this->controller = new TestController();
        $this->request = new Request([], [], [], []);
        $this->controller->setRequest($this->request);
    }

    public function testGetInvalidInputResponse(): void {
        $errors = [
            "name" => "`name` is required.",
            "email" => "`email` must be a valid email address.",
        ];

        $response = $this->controller->getInvalidInputResponse($errors);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey("message", $body);
        $this->assertArrayHasKey("errors", $body);
        $this->assertEquals("The necessary data was not provided and/or invalid.", $body["message"]);
        $this->assertEquals($errors, $body["errors"]);
    }

    public function testIndexRequiresAuthenticationForProtectedAction(): void {
        $controller = new TestController();
        $controller->setRequest($this->request);

        // Override public actions to make index protected
        $reflectionClass = new \ReflectionClass($controller);
        $property = $reflectionClass->getProperty('publicActions');
        $property->setAccessible(true);
        $property->setValue($controller, []);

        $response = $controller->index();

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateRequiresAuthenticationForProtectedAction(): void {
        $controller = new TestController();
        $controller->setRequest($this->request);

        // Override public actions to make create protected
        $reflectionClass = new \ReflectionClass($controller);
        $property = $reflectionClass->getProperty('publicActions');
        $property->setAccessible(true);
        $property->setValue($controller, []);

        $response = $controller->create();

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testReadRequiresAuthenticationForProtectedAction(): void {
        $controller = new TestController();
        $controller->setRequest($this->request);

        // Override public actions to make read protected
        $reflectionClass = new \ReflectionClass($controller);
        $property = $reflectionClass->getProperty('publicActions');
        $property->setAccessible(true);
        $property->setValue($controller, []);

        $response = $controller->read(1);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateRequiresAuthenticationForProtectedAction(): void {
        $this->request->setAttribute("route_params", ["id" => "1"]);

        $response = $this->controller->update(1);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteRequiresAuthenticationForProtectedAction(): void {
        $this->request->setAttribute("route_params", ["id" => "1"]);

        $response = $this->controller->delete(1);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testParseFieldsAttribute(): void {
        $queryParams = ["fields" => "name,description,status"];
        $request = new Request([], [], $queryParams, []);
        $this->controller->setRequest($request);

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod('parseFieldsAttribute');
        $method->setAccessible(true);
        $method->invoke($this->controller);

        $fields = $request->getAttribute("fields");
        $this->assertIsArray($fields);
        $this->assertEquals(["name", "description", "status"], $fields);
    }

    public function testParseFieldsAttributeHandlesEmptyString(): void {
        $queryParams = ["fields" => ""];
        $request = new Request([], [], $queryParams, []);
        $this->controller->setRequest($request);

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod('parseFieldsAttribute');
        $method->setAccessible(true);
        $method->invoke($this->controller);

        $fields = $request->getAttribute("fields");
        $this->assertNull($fields);
    }

    public function testParseFieldsAttributeTrimsWhitespace(): void {
        $queryParams = ["fields" => " name , description , status "];
        $request = new Request([], [], $queryParams, []);
        $this->controller->setRequest($request);

        $reflectionClass = new \ReflectionClass($this->controller);
        $method = $reflectionClass->getMethod('parseFieldsAttribute');
        $method->setAccessible(true);
        $method->invoke($this->controller);

        $fields = $request->getAttribute("fields");
        $this->assertIsArray($fields);
        $this->assertEquals(["name", "description", "status"], $fields);
    }
}
