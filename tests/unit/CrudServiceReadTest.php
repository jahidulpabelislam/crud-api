<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Unit;

use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\CRUD\API\Tests\Fixtures\TestRelatedEntity;
use JPI\ORM\Entity\Collection;

/**
 * Test CrudService read operations with relationship includes.
 *
 * Tests verify that the include parameter correctly loads relationships
 * and that the API response structure properly includes related entities.
 *
 * @covers \JPI\CRUD\API\CrudService::read
 */
final class CrudServiceReadTest extends AbstractCrudServiceTestCase {

    public function testIncludeBelongsTo(): void {
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 7])
            )
            ->willReturn([
                "id" => 7,
                "name" => "Main Entity",
                "related_id" => 70,
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)
            ->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 70])
            )
            ->willReturn([
                "id" => 70,
                "name" => "Related Entity Name",
                "description" => "Related Entity Description",
            ])
        ;

        $request = $this->createRequest(
            queryParams: ["include" => "related"],
            attributes: ["route_params" => ["id" => 7]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        $this->assertTrue(isset($entity->related));
        $this->assertSame(TestRelatedEntity::class, $entity->related::class);
        $this->assertSame(70, $entity->related->getId());
    }

    public function testIncludeHasOne(): void {
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 8])
            )
            ->willReturn([
                "id" => 8,
                "name" => "Parent Entity",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)
            ->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE parent_id = :parent_id
ORDER BY id ASC;"),
                $this->equalTo(["parent_id" => 8])
            )
            ->willReturn([[
                "id" => 80,
                "name" => "Child Entity",
                "description" => "Child Description",
                "parent_id" => 8,
            ]])
        ;

        $request = $this->createRequest(
            queryParams: ["include" => "child"],
            attributes: ["route_params" => ["id" => 8]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        $this->assertTrue(isset($entity->child));
        $this->assertSame(TestRelatedEntity::class, $entity->child::class);
        $this->assertSame(80, $entity->child->getId());
    }

    public function testIncludeHasMany(): void {
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 9])
            )
            ->willReturn([
                "id" => 9,
                "name" => "Parent Entity",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)
            ->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE parent_id = :parent_id
ORDER BY id ASC;"),
                $this->equalTo(["parent_id" => 9])
            )
            ->willReturn([
                [
                    "id" => 90,
                    "name" => "Child 1",
                    "description" => "First child",
                    "parent_id" => 9,
                ],
                [
                    "id" => 91,
                    "name" => "Child 2",
                    "description" => "Second child",
                    "parent_id" => 9,
                ],
            ])
        ;

        $request = $this->createRequest(
            queryParams: ["include" => "children"],
            attributes: ["route_params" => ["id" => 9]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        $this->assertTrue(isset($entity->children));
        $this->assertSame(Collection::class, $entity->children::class);
        $this->assertSame(2, count($entity->children));
        $this->assertSame(TestRelatedEntity::class, $entity->children[0]::class);
    }

    public function testMultipleInclude(): void {
        $this->createDatabase()
            ->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 10])
            )
            ->willReturn([
                "id" => 10,
                "name" => "Test Entity",
                "related_id" => 100,
            ])
        ;

        $db = $this->createDatabase(TestRelatedEntity::class);

        $db->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 100])
            )
            ->willReturn([
                "id" => 100,
                "name" => "Related Entity",
                "parent_id" => 10,
            ])
        ;

        $db->expects($this->exactly(2))
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE parent_id = :parent_id
ORDER BY id ASC;"),
                $this->equalTo(["parent_id" => 10])
            )
            ->willReturn([
                [
                    "id" => 101,
                    "name" => "Child 1",
                    "description" => "First child",
                    "parent_id" => 10,
                ],
                [
                    "id" => 102,
                    "name" => "Child 2",
                    "description" => "Second child",
                    "parent_id" => 10,
                ],
            ])
        ;

        $request = $this->createRequest(
            queryParams: ["include" => "related,child,children"],
            attributes: ["route_params" => ["id" => 10]]
        );

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        $this->assertTrue(isset($entity->related));
        $this->assertSame(TestRelatedEntity::class, $entity->related::class);
        $this->assertSame(100, $entity->related->getId());

        $this->assertTrue(isset($entity->child));
        $this->assertSame(TestRelatedEntity::class, $entity->child::class);
        $this->assertSame(101, $entity->child->getId());

        $this->assertTrue(isset($entity->children));
        $this->assertSame(Collection::class, $entity->children::class);
        $this->assertSame(2, count($entity->children));
        $this->assertSame(TestRelatedEntity::class, $entity->children[0]::class);
    }
}
