<?php

declare(strict_types=1);

use JPI\CRUD\API\Tests\Fixtures\TestCrudService;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\CRUD\API\Tests\Fixtures\TestRelatedEntity;
use JPI\Database;
use JPI\HTTP\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Test CrudService read operations with relationship includes.
 *
 * Tests verify that the include parameter correctly loads relationships
 * and that the API response structure properly includes related entities.
 *
 * @covers \JPI\CRUD\API\CrudService::read
 */
final class CrudServiceReadTest extends TestCase {

    private function createDatabase(string $entity = TestEntity::class): Database&MockObject {
        $database = $this->createMock(Database::class);
        $entity::setDatabase($database);
        return $database;
    }

    private function createRequest(array $queryParams = [], array $attributes = []): Request&Stub {
        $request = $this->createStub(Request::class);

        $queryParams = new \JPI\HTTP\Input($queryParams);

        $request->method("getQueryParam")
            ->willReturnCallback(function (string $key) use ($queryParams) {
                return $queryParams[$key] ?? null;
            })
        ;

        $request->method("hasQueryParam")
            ->willReturnCallback(function (string $key) use ($queryParams) {
                return isset($queryParams[$key]);
            })
        ;

        $request->method("getAttribute")
            ->willReturnCallback(function (string $key) use ($attributes) {
                return $attributes[$key] ?? null;
            })
        ;

        return $request;
    }

    public function testIncludeBelongsToRelationship(): void {
        $this->createDatabase()->expects($this->once())
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

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
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

        $request = $this->createRequest(["include" => "related"], ["route_params" => ["id" => 7]]);

        $service = new TestCrudService(TestEntity::class);
        $result = $service->read($request);

        $this->assertTrue(isset($result->related));
        $this->assertInstanceOf(TestRelatedEntity::class, $result->related);
        $this->assertSame(70, $result->related->getId());
    }

    public function testIncludeHasOneRelationship(): void {
        $this->createDatabase()->expects($this->once())
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

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
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

        $request = $this->createRequest(["include" => "child"], ["route_params" => ["id" => 8]]);

        $service = new TestCrudService(TestEntity::class);
        $result = $service->read($request);

        $this->assertTrue(isset($result->child));
        $this->assertInstanceOf(TestRelatedEntity::class, $result->child);
        $this->assertSame(80, $result->child->getId());
    }

    public function testIncludeHasManyRelationship(): void {
        $this->createDatabase()->expects($this->once())
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

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
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

        $request = $this->createRequest(["include" => "children"], ["route_params" => ["id" => 9]]);

        $service = new TestCrudService(TestEntity::class);
        $result = $service->read($request);

        $this->assertTrue(isset($result->children));
    }

    public function testMultipleInclude(): void {
        $this->createDatabase()->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 6])
            )
            ->willReturn([
                "id" => 6,
                "name" => "Test Entity",
                "related_id" => 60,
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 60])
            )
            ->willReturn([
                "id" => 60,
                "name" => "Related Entity",
            ])
        ;

        $request = $this->createRequest(["include" => "related,child,children"], ["route_params" => ["id" => 6]]);

        $service = new TestCrudService(TestEntity::class);
        $result = $service->read($request);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(6, $result->getId());

        $this->assertTrue(isset($result->related));
        $this->assertInstanceOf(TestRelatedEntity::class, $result->related);
        $this->assertSame(60, $result->related->getId());
    }
}
