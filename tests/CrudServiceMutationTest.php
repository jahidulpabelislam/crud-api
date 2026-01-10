<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\Database;
use JPI\HTTP\Input;
use JPI\HTTP\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test CrudService create and update operations
 *
 * Tests verify that setValuesFromRequest correctly validates and sets
 * entity properties from request data, handling required fields,
 * validation errors, and data mapping.
 */
final class CrudServiceMutationTest extends TestCase {

    private function createDatabase(): Database&MockObject {
        $database = $this->createMock(Database::class);
        TestEntity::setDatabase($database);
        return $database;
    }

    private function createRequest(array $body = [], array $routeParams = []): Request {
        $request = $this->createStub(Request::class);

        $request->method("getArrayFromBody")
            ->willReturn(new Input($body))
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
        $database = $this->createDatabase();

        // Mock insert operation
        $database->expects($this->once())
            ->method("exec")
            ->willReturn(1)
        ;

        $database->expects($this->once())
            ->method("getLastInsertedId")
            ->willReturn(1)
        ;

        // Mock reload operation
        $database->expects($this->once())
            ->method("selectFirst")
            ->willReturn([
                "id" => 1,
                "name" => "Test Entity",
                "description" => "Test Description",
                "status" => "active",
                "category" => "test",
            ])
        ;

        $request = $this->createRequest([
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
        $request = $this->createRequest([
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertEquals(["name" => "`name` is required."], $e->getErrors());
        }
    }

    public function testCreateWithEmptyRequiredFieldFails(): void {
        $request = $this->createRequest([
            "name" => "",
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertEquals(["name" => "`name` cannot be empty."], $e->getErrors());
        }
    }

    public function testCreateSetsOnlyProvidedFields(): void {
        $database = $this->createDatabase();

        // Mock insert operation
        $database->expects($this->once())
            ->method("exec")
            ->willReturn(1)
        ;
        $database->expects($this->once())
            ->method("getLastInsertedId")
            ->willReturn(1)
        ;

        // Mock reload operation
        $database->expects($this->once())
            ->method("selectFirst")
            ->willReturn([
                "id" => 1,
                "name" => "Test Entity",
            ])
        ;

        $request = $this->createRequest([
            "name" => "Test Entity",
            // description, status, category not provided
        ]);

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->create($request);

        $this->assertEquals("Test Entity", $entity->name);
    }

    public function testUpdateWithValidDataSucceeds(): void {
        $database = $this->createDatabase();

        // Mock getById to return existing entity
        $database->expects($this->exactly(2))
            ->method("selectFirst")
            ->willReturnOnConsecutiveCalls(
                [
                    "id" => 2,
                    "name" => "Old Name",
                    "description" => "Old Description",
                ],
                [
                    "id" => 2,
                    "name" => "New Name",
                    "description" => "New Description",
                ]
            )
        ;

        // Mock update operation
        $database->expects($this->once())
            ->method("exec")
            ->willReturn(1)
        ;

        $request = $this->createRequest(
            [
                "name" => "New Name",
                "description" => "New Description",
            ],
            ["id" => "2"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals("New Name", $entity->name);
    }

    public function testUpdateWithInvalidIdReturnsNull(): void {
        $request = $this->createRequest(
            ["name" => "New Name"],
            ["id" => "invalid"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertNull($entity);
    }

    public function testUpdateWithNonExistentIdReturnsNull(): void {
        $database = $this->createDatabase();

        // Mock getById to return null (entity not found)
        $database->expects($this->once())
            ->method("selectFirst")
            ->willReturn(null)
        ;

        $request = $this->createRequest(
            ["name" => "New Name"],
            ["id" => "999"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertNull($entity);
    }

    public function testUpdateDoesNotRequireRequiredFieldsForExistingEntity(): void {
        $database = $this->createDatabase();

        // Mock getById to return existing entity with name already set
        $database->expects($this->exactly(2))
            ->method("selectFirst")
            ->willReturnOnConsecutiveCalls(
                [
                    "id" => 3,
                    "name" => "Existing Name",
                    "description" => "Old Description",
                ],
                [
                    "id" => 3,
                    "name" => "Existing Name",
                    "description" => "New Description",
                ]
            )
        ;

        // Mock update operation
        $database->expects($this->once())
            ->method("exec")
            ->willReturn(1)
        ;

        $request = $this->createRequest(
            [
                // name not provided - should be ok for update
                "description" => "New Description",
            ],
            ["id" => "3"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals("New Description", $entity->description);
    }

    public function testCreateValidatesDataMapping(): void {
        $request = $this->createRequest([
            "name" => "Test Entity",
            "age" => "not-a-number",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException was not thrown");
        } catch (InvalidDataException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey("age", $errors);
            $this->assertEquals("age must be a valid integer", $errors["age"]);
        }
    }

    public function testUpdateValidatesDataMapping(): void {
        $database = $this->createDatabase();

        // Mock getById to return existing entity
        $database->expects($this->once())
            ->method("selectFirst")
            ->willReturn([
                "id" => 4,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createRequest(
            [
                "name" => "Updated Name",
                "age" => "not-a-number",
            ],
            ["id" => "4"]
        );

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->update($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertEquals(["age" => "`age` must be a valid integer."], $e->getErrors());
        }
    }
}
