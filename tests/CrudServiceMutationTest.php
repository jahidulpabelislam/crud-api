<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\Database;
use JPI\HTTP\Input;
use JPI\HTTP\Request;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Test CrudService create and update operations
 *
 * Tests verify that setValuesFromRequest correctly validates and sets
 * entity properties from request data, handling required fields,
 * validation errors, and data mapping.
 *
 * @covers \JPI\CRUD\API\CrudService::create
 * @covers \JPI\CRUD\API\CrudService::update
 * @covers \JPI\CRUD\API\CrudService::setValuesFromRequest
 */
final class CrudServiceMutationTest extends TestCase {

    private function createDatabase(): Database&Stub {
        $database = $this->createStub(Database::class);
        TestEntity::setDatabase($database);
        return $database;
    }

    private function createRequest(array $body = [], array $routeParams = []): Request&Stub {
        $request = $this->createStub(Request::class);

        $request->method("getArrayFromBody")->willReturn(new Input($body));

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

    public function testCreateSuccess(): void {
        $database = $this->createDatabase();

        // Mock insert operation
        $database->method("exec")->willReturn(1);
        $database->method("getLastInsertedId")->willReturn(1);

        // Mock reload operation
        $database->method("selectFirst")
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
            "created_at" => "2026-01-01 12:00:00",
        ]);

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->create($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame("Test Entity", $entity->name);
    }

    public function testCreateWithMissingRequiredFields(): void {
        $request = $this->createRequest([
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertSame(
                [
                    "name" => "`name` is required.",
                    "created_at" => "`created_at` is required.",
                ],
                $e->getErrors()
            );
        }
    }

    public function testCreateWithEmptyRequiredFields(): void {
        $request = $this->createRequest([
            "name" => "",
            "description" => "Test Description",
            "created_at" => "",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertSame(
                [
                    "name" => "`name` cannot be empty.",
                    "created_at" => "`created_at` cannot be empty.",
                ],
                $e->getErrors()
            );
        }
    }

    public function testCreateWithInvalidValues(): void {
        $request = $this->createRequest([
            "name" => "Test Entity",
            "age" => "not-a-number",
            "created_at" => "not-a-date",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException was not thrown");
        } catch (InvalidDataException $e) {
            $this->assertSame(
                [
                    "age" => "`age` must be an integer or null.",
                    // Cos its required it doesn't say `or null`
                    "created_at" => "`created_at` must be instance of \DateTime or valid format for creation.",
                ],
                $e->getErrors()
            );
        }
    }

    public function testUpdateSuccess(): void {
        $database = $this->createDatabase();

        // Mock getById to return existing entity
        $database->method("selectFirst")
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
        $database->method("exec")->willReturn(1);

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
        $this->assertSame("New Name", $entity->name);
    }

    public function testUpdateWithMissingRequiredFields(): void {
        $database = $this->createDatabase();

        // Mock getById to return existing entity with name already set
        $database->method("selectFirst")
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
        $database->method("exec")->willReturn(1);

        $request = $this->createRequest(
            [
                // name & created_at not provided but ok for update
                "description" => "New Description",
            ],
            ["id" => "3"]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame("New Description", $entity->description);
    }

    public function testUpdateWithEmptyRequiredFields(): void {
        // Mock getById to return existing entity
        $this->createDatabase()->method("selectFirst")
            ->willReturn([
                "id" => 4,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createRequest(
            [
                "name" => "",
                "description" => "Test Description",
                "created_at" => "",
            ],
            ["id" => "4"]
        );

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->update($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertSame(
                [
                    "name" => "`name` cannot be empty.",
                    "created_at" => "`created_at` cannot be empty.",
                ],
                $e->getErrors()
            );
        }
    }

    public function testUpdateWithInvalidValues(): void {
        // Mock getById to return existing entity
        $this->createDatabase()->method("selectFirst")
            ->willReturn([
                "id" => 5,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createRequest(
            [
                "name" => "Updated Name",
                "age" => "not-a-number",
                "created_at" => "not-a-date",
            ],
            ["id" => "5"]
        );

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->update($request);
            $this->fail("Expected InvalidDataException to be thrown");
        } catch (InvalidDataException $e) {
            $this->assertSame(
                [
                    "age" => "`age` must be an integer or null.",
                    // Cos its required it doesn't say `or null`
                    "created_at" => "`created_at` must be instance of \DateTime or valid format for creation.",
                ],
                $e->getErrors()
            );
        }
    }
}
