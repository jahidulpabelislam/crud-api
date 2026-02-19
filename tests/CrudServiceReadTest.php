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
 * @covers \JPI\CRUD\API\AbstractEntity::getAPIResponse
 */
final class CrudServiceReadTest extends TestCase {

    private function createDatabase(string $entity = TestEntity::class): Database&MockObject {
        $database = $this->createMock(Database::class);
        $entity::setDatabase($database);
        TestRelatedEntity::setDatabase($database);
        return $database;
    }

    private function createRequest(array $queryParams = []): Request&Stub {
        $request = $this->createStub(Request::class);

        $queryParams = new \JPI\HTTP\Input($queryParams);

        $request->method("getQueryParam")
            ->willReturnCallback(function ($key) use ($queryParams) {
                return $queryParams[$key] ?? null;
            })
        ;

        $request->method("hasQueryParam")
            ->willReturnCallback(function ($key) use ($queryParams) {
                return isset($queryParams[$key]);
            })
        ;

        return $request;
    }

    /**
     * Test that the include parameter works for read action.
     * Similar to testIncludeParameter but for single entity retrieval.
     */
    public function testIncludeParameter(): void {
        $this->createDatabase()->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 5])
            )
            ->willReturn([
                "id" => 5,
                "name" => "Test Entity",
                "related_id" => "2",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 2])
            )
            ->willReturn([
                "id" => 2,
                "name" => "Related Entity",
            ])
        ;

        $request = $this->createRequest([
            "include" => "related,child,children",
        ]);

        $request->method("getAttribute")
            ->willReturnCallback(function ($key) {
                if ($key === "route_params") {
                    return ["id" => "5"];
                }
                return null;
            })
        ;

        $service = new TestCrudService(TestEntity::class);
        $result = $service->read($request);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame(5, $result->getId());

        $this->assertInstanceOf(TestRelatedEntity::class, $result->related);
        $this->assertSame(2, $result->related->getId());
    }

    /**
     * Test API response format when including a belongs_to relationship.
     */
    public function testResponseWithBelongsToRelationship(): void {
        $this->createDatabase()->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 1])
            )
            ->willReturn([
                "id" => 1,
                "name" => "Main Entity",
                "related_id" => "10",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 10])
            )
            ->willReturn([
                "id" => 10,
                "name" => "Related Entity Name",
                "description" => "Related Entity Description",
            ])
        ;

        $request = $this->createRequest([
            "include" => "related",
        ]);

        $request->method("getAttribute")
            ->willReturnCallback(function ($key) {
                if ($key === "route_params") {
                    return ["id" => "1"];
                }
                return null;
            })
        ;

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        // Verify the entity and relationship are loaded
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertInstanceOf(TestRelatedEntity::class, $entity->related);

        // Test the API response structure
        $response = $entity->getAPIResponse($request);

        $this->assertIsArray($response);
        $this->assertArrayHasKey("id", $response);
        $this->assertArrayHasKey("name", $response);
        $this->assertArrayHasKey("related", $response);

        // Verify belongs_to relationship is included as a nested object
        $this->assertIsArray($response["related"]);
        $this->assertSame(10, $response["related"]["id"]);
        $this->assertSame("Related Entity Name", $response["related"]["name"]);
        $this->assertSame("Related Entity Description", $response["related"]["description"]);
    }

    /**
     * Test API response format when including a has_one relationship.
     */
    public function testResponseWithHasOneRelationship(): void {
        $this->createDatabase()->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 2])
            )
            ->willReturn([
                "id" => 2,
                "name" => "Parent Entity",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE parent = :parent
LIMIT 1;"),
                $this->equalTo(["parent" => 2])
            )
            ->willReturn([
                "id" => 20,
                "name" => "Child Entity",
                "description" => "Child Description",
            ])
        ;

        $request = $this->createRequest([
            "include" => "child",
        ]);

        $request->method("getAttribute")
            ->willReturnCallback(function ($key) {
                if ($key === "route_params") {
                    return ["id" => "2"];
                }
                return null;
            })
        ;

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        // Verify the entity and relationship are loaded
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertInstanceOf(TestRelatedEntity::class, $entity->child);

        // Test the API response structure
        $response = $entity->getAPIResponse($request);

        $this->assertIsArray($response);
        $this->assertArrayHasKey("id", $response);
        $this->assertArrayHasKey("name", $response);
        $this->assertArrayHasKey("child", $response);

        // Verify has_one relationship is included as a nested object
        $this->assertIsArray($response["child"]);
        $this->assertSame(20, $response["child"]["id"]);
        $this->assertSame("Child Entity", $response["child"]["name"]);
        $this->assertSame("Child Description", $response["child"]["description"]);
    }

    /**
     * Test API response format when including a has_many relationship.
     */
    public function testResponseWithHasManyRelationship(): void {
        $this->createDatabase()->expects($this->once())
            ->method("selectFirst")
            ->with(
                $this->equalTo("SELECT *
FROM test_entities
WHERE id = :id
LIMIT 1;"),
                $this->equalTo(["id" => 3])
            )
            ->willReturn([
                "id" => 3,
                "name" => "Parent Entity",
            ])
        ;

        $this->createDatabase(TestRelatedEntity::class)->expects($this->once())
            ->method("selectAll")
            ->with(
                $this->equalTo("SELECT *
FROM related_test_entities
WHERE parent = :parent;"),
                $this->equalTo(["parent" => 3])
            )
            ->willReturn([
                [
                    "id" => 30,
                    "name" => "Child 1",
                    "description" => "First child",
                ],
                [
                    "id" => 31,
                    "name" => "Child 2",
                    "description" => "Second child",
                ],
            ])
        ;

        $request = $this->createRequest([
            "include" => "children",
        ]);

        $request->method("getAttribute")
            ->willReturnCallback(function ($key) {
                if ($key === "route_params") {
                    return ["id" => "3"];
                }
                return null;
            })
        ;

        $service = new TestCrudService(TestEntity::class);
        $entity = $service->read($request);

        // Verify the entity is loaded
        $this->assertInstanceOf(TestEntity::class, $entity);

        // Test the API response structure
        $response = $entity->getAPIResponse($request);

        $this->assertIsArray($response);
        $this->assertArrayHasKey("id", $response);
        $this->assertArrayHasKey("name", $response);
        $this->assertArrayHasKey("children", $response);

        // Verify has_many relationship is included as an array of objects
        $this->assertIsArray($response["children"]);
        $this->assertCount(2, $response["children"]);

        // Check first child
        $this->assertIsArray($response["children"][0]);
        $this->assertSame(30, $response["children"][0]["id"]);
        $this->assertSame("Child 1", $response["children"][0]["name"]);
        $this->assertSame("First child", $response["children"][0]["description"]);
        $this->assertArrayHasKey("_links", $response["children"][0]);

        // Check second child
        $this->assertIsArray($response["children"][1]);
        $this->assertSame(31, $response["children"][1]["id"]);
        $this->assertSame("Child 2", $response["children"][1]["name"]);
        $this->assertSame("Second child", $response["children"][1]["description"]);
        $this->assertArrayHasKey("_links", $response["children"][1]);
    }
}
