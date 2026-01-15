<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestController;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\HTTP\Input;
use JPI\HTTP\Request;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JPI\CRUD\API\Entity\Responder
 */
final class ResponderTest extends TestCase {

    private TestController $controller;
    private Request $request;

    protected function setUp(): void {
        $this->controller = new TestController();
        $serverParams = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/test-entities/",
            "HTTP_HOST" => "api.example.com",
            "REQUEST_SCHEME" => "https",
        ];
        $this->request = new Request($serverParams, [], [], []);
        $this->controller->setRequest($this->request);
    }

    private function createEntity(int $id, string $name): TestEntity {
        return TestEntity::loadFromDatabaseRow([
            "id" => $id,
            "name" => $name,
            "description" => null,
            "status" => null,
            "category" => null,
            "age" => null,
            "created_at" => null,
        ]);
    }

    public function testEmptyCollection(): void {
        $response = $this->controller->getEntitiesResponse($this->request, new EntityCollection([]));
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                "data" => [],
                "_links" => ["self" => "https://api.example.com/test-entities/"],
                "message" => "No Test Entities found.",
            ],
            $body
        );
    }

    public function testEntities(): void {
        $entities = new EntityCollection([$this->createEntity(1, "Test 1"), $this->createEntity(2, "Test 2")]);
        $response = $this->controller->getEntitiesResponse($this->request, $entities);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                "data" => [
                    [
                        "id" => 1,
                        "name" => "Test 1",
                        "description" => null,
                        "status" => null,
                        "category" => null,
                        "age" => null,
                        "created_at" => null,
                        "_links" => ["self" => "https://api.example.com/test-entities/1/"],
                    ],
                    [
                        "id" => 2,
                        "name" => "Test 2",
                        "description" => null,
                        "status" => null,
                        "category" => null,
                        "age" => null,
                        "created_at" => null,
                        "_links" => ["self" => "https://api.example.com/test-entities/2/"],
                    ],
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/"],
            ],
            $body
        );
    }

    public function testPaginatedEntities(): void {
        $collection = new PaginatedEntityCollection(
            items: [$this->createEntity(1, "Test 1"), $this->createEntity(2, "Test 2")],
            totalCount: 25,
            limit: 2,
            page: 1
        );
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey("data", $body);
        $this->assertSame(25, $body["_total_count"]);
        $this->assertSame(13, $body["_total_pages"]); // 25 / 2 = 13 pages
        $this->assertSame(
            [
                "self" => "https://api.example.com/test-entities/",
                "next_page" => "https://api.example.com/test-entities/?page=2",
            ],
            $body["_links"]
        );
    }

    public function testPaginatedEntitiesLastPage(): void {
        $collection = new PaginatedEntityCollection(
            items: [$this->createEntity(1, "Test")],
            totalCount: 25,
            limit: 2,
            page: 13 // last page
        );
        $this->request->setQueryParams(new Input(["page" => 13]));
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "self" => "https://api.example.com/test-entities/?page=13",
                "previous_page" => "https://api.example.com/test-entities/?page=12",
            ],
            $body["_links"]
        );
    }

    public function testPaginatedEntitiesMiddlePage(): void {
        $collection = new PaginatedEntityCollection(
            items: [$this->createEntity(1, "Test")],
            totalCount: 30,
            limit: 2,
            page: 6 // middle page
        );
        $this->request->setQueryParams(new Input(["page" => 6]));
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "self" => "https://api.example.com/test-entities/?page=6",
                "previous_page" => "https://api.example.com/test-entities/?page=5",
                "next_page" => "https://api.example.com/test-entities/?page=7",
            ],
            $body["_links"]
        );
    }

    public function testEntityNotFound(): void {
        $response = $this->controller->getEntityNotFoundResponse($this->request, 123);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(["message" => "No Test Entity identified by `123` found."], $body);
    }

    public function testEntityFound(): void {
        $response = $this->controller->getEntityResponse($this->request, $this->createEntity(1, "Test Entity"), 1);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                "data" => [
                    "id" => 1,
                    "name" => "Test Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/1/"],
            ],
            $body
        );
    }

    public function testCreateSuccess(): void {
        $response = $this->controller->getEntityCreateResponse($this->request, $this->createEntity(1, "New Entity"));
        $body = json_decode($response->getBody(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame("https://api.example.com/test-entities/1/", $response->getHeaderString("Location"));
        $this->assertSame(
            [
                "data" => [
                    "id" => 1,
                    "name" => "New Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/1/"],
            ],
            $body
        );
    }

    public function testCreateFailure(): void {
        // Pass null to simulate creation failure
        $response = $this->controller->getEntityCreateResponse($this->request, null);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            [
                "message" => "Failed to create the new Test Entity.",
            ],
            $body
        );
    }

    public function testUpdateSuccess(): void {
        $response = $this->controller->getEntityUpdateResponse($this->request, $this->createEntity(1, "Updated Entity"), 1);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                "data" => [
                    "id" => 1,
                    "name" => "Updated Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/1/"],
            ],
            $body
        );
    }

    public function testUpdateFailure(): void {
        // Create a mock entity that"s loaded but with wrong ID
        $response = $this->controller->getEntityUpdateResponse($this->request, $this->createEntity(2, "Entity"), 1);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            [
                "message" => "Failed to update the Test Entity identified by `1`.",
            ],
            $body
        );
    }

    public function testDeleteSuccess(): void {
        $entity = $this->createStub(TestEntity::class);
        $entity->method("getId")->willReturn(1);
        $entity->method("isLoaded")->willReturn(true);
        $entity->method("isDeleted")->willReturn(true);

        $response = $this->controller->getEntityDeleteResponse($this->request, $entity, 1);

        $this->assertSame(204, $response->getStatusCode());
        // 204 response typically has empty array body which becomes "{}" in JSON
        $body = $response->getBody();
        $this->assertEmpty(json_decode($body, true));
    }

    public function testDeleteFailure(): void {
        $entity = $this->createEntity(1, "");

        $response = $this->controller->getEntityDeleteResponse($this->request, $entity, 1);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            [
                "message" => "Failed to delete the Test Entity identified by `1`.",
            ],
            $body
        );
    }
}
