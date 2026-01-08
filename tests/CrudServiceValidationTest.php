<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\HTTP\Request;
use PHPUnit\Framework\TestCase;

class CrudServiceValidationTest extends TestCase {

    private TestCrudService $service;
    private Request $request;

    protected function setUp(): void {
        $this->service = new TestCrudService(TestEntity::class);
        $this->request = new Request([], [], [], []);
    }

    public function testSetValuesFromRequestValidatesRequiredFields(): void {
        // TestCrudService has "name" as a required field
        $entity = $this->createMock(TestEntity::class);
        $entity->method('isLoaded')->willReturn(false);
        $entity->method('getColumns')->willReturn(["name", "description"]);
        
        // Mock static method
        TestEntity::setDataMapping([
            "name" => ["type" => "string"],
            "description" => ["type" => "string"],
        ]);
        
        // Empty body - should trigger validation error for required field
        $this->request->setBody("");
        
        $reflectionClass = new \ReflectionClass($this->service);
        $method = $reflectionClass->getMethod('setValuesFromRequest');
        $method->setAccessible(true);
        
        $this->expectException(InvalidDataException::class);
        $method->invoke($this->service, $entity, $this->request);
    }

    public function testInvalidDataExceptionContainsErrors(): void {
        $errors = [
            "name" => "`name` is required.",
            "email" => "`email` must be valid.",
        ];
        
        $exception = new InvalidDataException($errors);
        
        $this->assertInstanceOf(InvalidDataException::class, $exception);
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertIsArray($exception->getErrors());
        $this->assertArrayHasKey("name", $exception->getErrors());
        $this->assertArrayHasKey("email", $exception->getErrors());
    }

    public function testInvalidDataExceptionMessage(): void {
        $errors = [
            "field1" => "Error 1",
            "field2" => "Error 2",
        ];
        
        $exception = new InvalidDataException($errors);
        
        // Check that the exception has a message
        $this->assertNotEmpty($exception->getMessage());
    }

    public function testGetEntityInstanceReturnsCorrectClass(): void {
        $entity = $this->service->getEntityInstance();
        
        $this->assertInstanceOf(TestEntity::class, $entity);
    }
}
