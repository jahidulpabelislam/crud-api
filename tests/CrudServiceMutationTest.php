<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\Database;
use JPI\HTTP\Request;
use PHPUnit\Framework\TestCase;

/**
 * Test CrudService create and update operations
 *
 * Tests verify that setValuesFromRequest correctly validates and sets
 * entity properties from request data, handling required fields,
 * validation errors, and data mapping.
 */
final class CrudServiceMutationTest extends TestCase {

    private function createMockDatabase(): Database {
        $database = $this->createMock(Database::class);
        TestEntity::setDatabase($database);
        return $database;
    }

    private function createMockRequest(array $body = [], array $routeParams = []): Request {
        $request = $this->createMock(Request::class);

        $request->method("getArrayFromBody")
            ->willReturn($body)
        ;

        $request->method("getAttribute")
            ->willReturnCallback(function ($key) use ($routeParams) {
                if ($key === "route_params") {
                    return $routeParams;
                }
                return null;
            })
        ;

        return $request;
    }

    public function testCreateWithValidDataSucceeds(): void {
        $database = $this->createMockDatabase();

        // Mock insert operation
        $database->expects($this->once())
            ->method("insert")
            ->willReturn(1)
        ;

        // Mock reload operation
        $database->expects($this->once())
            ->method("selectSingle")
            ->willReturn([
                "id" => 1,
                "name" => "Test Entity",
                "description" => "Test Description",
                "status" => "active",
                "category" => "test",
            ])
        ;

        $request = $this->createMockRequest([
            "name" => "Test Entity",
            "description" => "Test Description",
            "status" => "active",
            "category" => "test",
        ]);

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->create($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals("Test Entity", $entity->name);
    }

    public function testCreateWithMissingRequiredFieldFails(): void {
        $this->createMockDatabase();

        $request = $this->createMockRequest([
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("`name` is required");

        $service->create($request);
    }

    public function testCreateWithEmptyRequiredFieldFails(): void {
        $this->createMockDatabase();

        $request = $this->createMockRequest([
            "name" => "",
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("`name` cannot be empty");

        $service->create($request);
    }

    public function testCreateSetsOnlyProvidedFields(): void {
        $database = $this->createMockDatabase();

        // Mock insert operation
        $database->expects($this->once())
            ->method("insert")
            ->willReturn(1)
        ;

        // Mock reload operation
        $database->expects($this->once())
            ->method("selectSingle")
            ->willReturn([
                "id" => 1,
                "name" => "Test Entity",
            ])
        ;

        $request = $this->createMockRequest([
            "name" => "Test Entity",
            // description, status, category not provided
        ]);

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->create($request);

        $this->assertEquals("Test Entity", $entity->name);
    }

    public function testUpdateWithValidDataSucceeds(): void {
        $database = $this->createMockDatabase();

        // Mock getById to return existing entity
        $database->expects($this->exactly(2))
            ->method("selectSingle")
            ->willReturnOnConsecutiveCalls(
                [
                    "id" => 1,
                    "name" => "Old Name",
                    "description" => "Old Description",
                ],
                [
                    "id" => 1,
                    "name" => "New Name",
                    "description" => "New Description",
                ]
            )
        ;

        // Mock update operation
        $database->expects($this->once())
            ->method("update")
            ->willReturn(true)
        ;

        $request = $this->createMockRequest(
            [
                "name" => "New Name",
                "description" => "New Description",
            ],
            ["id" => "1"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals("New Name", $entity->name);
    }

    public function testUpdateWithInvalidIdReturnsNull(): void {
        $this->createMockDatabase();

        $request = $this->createMockRequest(
            ["name" => "New Name"],
            ["id" => "invalid"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertNull($entity);
    }

    public function testUpdateWithNonExistentIdReturnsNull(): void {
        $database = $this->createMockDatabase();

        // Mock getById to return null (entity not found)
        $database->expects($this->once())
            ->method("selectSingle")
            ->willReturn(null)
        ;

        $request = $this->createMockRequest(
            ["name" => "New Name"],
            ["id" => "999"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertNull($entity);
    }

    public function testUpdateDoesNotRequireRequiredFieldsForExistingEntity(): void {
        $database = $this->createMockDatabase();

        // Mock getById to return existing entity with name already set
        $database->expects($this->exactly(2))
            ->method("selectSingle")
            ->willReturnOnConsecutiveCalls(
                [
                    "id" => 1,
                    "name" => "Existing Name",
                    "description" => "Old Description",
                ],
                [
                    "id" => 1,
                    "name" => "Existing Name",
                    "description" => "New Description",
                ]
            )
        ;

        // Mock update operation
        $database->expects($this->once())
            ->method("update")
            ->willReturn(true)
        ;

        $request = $this->createMockRequest(
            [
                // name not provided - should be ok for update
                "description" => "New Description",
            ],
            ["id" => "1"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals("New Description", $entity->description);
    }

    public function testCreateValidatesDataMapping(): void {
        $database = $this->createMockDatabase();

        $request = $this->createMockRequest([
            "name" => "Test Entity",
            "created_at" => "invalid-date",
        ]);

        $service = new TestCrudService(TestEntity::class);

        $this->expectException(InvalidDataException::class);

        $service->create($request);
    }

    public function testUpdateValidatesDataMapping(): void {
        $database = $this->createMockDatabase();

        // Mock getById to return existing entity
        $database->expects($this->once())
            ->method("selectSingle")
            ->willReturn([
                "id" => 1,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createMockRequest(
            [
                "name" => "Updated Name",
                "created_at" => "invalid-date",
            ],
            ["id" => "1"]
        );

        $service = new TestCrudService(TestEntity::class);

        $this->expectException(InvalidDataException::class);

        $service->update($request);
    }
}
