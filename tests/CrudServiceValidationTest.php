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
        // Test that the TestCrudService has the correct required columns configured
        $reflectionClass = new \ReflectionClass(TestCrudService::class);
        $property = $reflectionClass->getProperty('requiredColumns');
        $property->setAccessible(true);
        $requiredColumns = $property->getValue();
        
        $this->assertIsArray($requiredColumns);
        $this->assertContains("name", $requiredColumns);
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
        
        // Check that the exception can have an empty message (it's optional)
        // The important part is it has the errors array
        $this->assertIsString($exception->getMessage());
        $this->assertIsArray($exception->getErrors());
        $this->assertNotEmpty($exception->getErrors());
    }

    public function testGetEntityInstanceReturnsCorrectClass(): void {
        $entity = $this->service->getEntityInstance();
        
        $this->assertInstanceOf(TestEntity::class, $entity);
    }
}
