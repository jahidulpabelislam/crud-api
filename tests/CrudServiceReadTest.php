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
 * TODO
 *
 * @covers \JPI\CRUD\API\CrudService::read
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
                "author_id" => "2",
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
            "include" => "author,category",
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

        $this->assertInstanceOf(TestRelatedEntity::class, $result->author);
        $this->assertSame(2, $result->author->getId());
    }
}
