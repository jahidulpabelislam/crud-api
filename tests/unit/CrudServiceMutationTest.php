<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Unit;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;

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
final class CrudServiceMutationTest extends AbstractCrudServiceTestCase {

    public function testCreateSuccess(): void {
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

        $request = $this->createRequest(body: [
            "name" => "Test Entity",
            "description" => "Test Description",
            "status" => "active",
            "category" => "test",
            "created_at" => "2026-01-01 12:00:00",
        ]);

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->create($request);

        $this->assertSame(TestEntity::class, $entity::class);
        $this->assertSame("Test Entity", $entity->name);
    }

    public function testCreateWithMissingRequiredFields(): void {
        $request = $this->createRequest(body: [
            "description" => "Test Description",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        }
        catch (InvalidDataException $e) {
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
        $request = $this->createRequest(body: [
            "name" => "",
            "description" => "Test Description",
            "created_at" => "",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException to be thrown");
        }
        catch (InvalidDataException $e) {
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
        $request = $this->createRequest(body: [
            "name" => "Test Entity",
            "age" => "not-a-number",
            "created_at" => "not-a-date",
        ]);

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->create($request);
            $this->fail("Expected InvalidDataException was not thrown");
        }
        catch (InvalidDataException $e) {
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
        $database
            ->expects($this->exactly(2))
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
            body: [
                "name" => "New Name",
                "description" => "New Description",
            ],
            attributes: ["route_params" => ["id" => 2]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertSame(TestEntity::class, $entity::class);
        $this->assertSame("New Name", $entity->name);
    }

    public function testUpdateWithMissingRequiredFields(): void {
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
            body: [
                // name & created_at not provided but ok for update
                "description" => "New Description",
            ],
            attributes: ["route_params" => ["id" => 3]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->update($request);

        $this->assertSame(TestEntity::class, $entity::class);
        $this->assertSame("New Description", $entity->description);
    }

    public function testUpdateWithEmptyRequiredFields(): void {
        // Mock getById to return existing entity
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->willReturn([
                "id" => 4,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createRequest(
            body: [
                "name" => "",
                "description" => "Test Description",
                "created_at" => "",
            ],
            attributes: ["route_params" => ["id" => 4]]
        );

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->update($request);
            $this->fail("Expected InvalidDataException to be thrown");
        }
        catch (InvalidDataException $e) {
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
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->willReturn([
                "id" => 5,
                "name" => "Existing Name",
            ])
        ;

        $request = $this->createRequest(
            body: [
                "name" => "Updated Name",
                "age" => "not-a-number",
                "created_at" => "not-a-date",
            ],
            attributes: ["route_params" => ["id" => 5]]
        );

        $service = new TestCrudService(TestEntity::class);

        try {
            $service->update($request);
            $this->fail("Expected InvalidDataException to be thrown");
        }
        catch (InvalidDataException $e) {
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
