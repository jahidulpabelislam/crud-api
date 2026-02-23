<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\AbstractEntity;
use JPI\CRUD\API\Tests\Fixtures\TestController;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\CRUD\API\Tests\Fixtures\TestRelatedEntity;
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

    private function createEntity(int $id, string $name, string $entity = TestEntity::class): AbstractEntity {
        return $entity::loadFromDatabaseRow([
            "id" => $id,
            "name" => $name,
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

    public function testEntitiesWithFields(): void {
        $this->request->setAttribute("fields", ["name", "status"]);
        $entities = new EntityCollection([$this->createEntity(1, "Test 1")]);
        $response = $this->controller->getEntitiesResponse($this->request, $entities);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                "data" => [
                    [
                        "id" => 1,
                        "name" => "Test 1",
                        "status" => null,
                        "_links" => ["self" => "https://api.example.com/test-entities/1/"],
                    ],
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/"],
            ],
            $body
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

    public function testEntityWithFields(): void {
        $this->request->setAttribute("fields", ["name", "status"]);
        $response = $this->controller->getEntityResponse($this->request, $this->createEntity(1, "Test 1"), 1);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "data" => [
                    "id" => 1,
                    "name" => "Test 1",
                    "status" => null,
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

    public function testEntityWithBelongsTo(): void {
        $entity = $this->createEntity(2, "Main Entity");
        $entity->related = $this->createEntity(20, "Related Entity Name", TestRelatedEntity::class);
        $entity->related->parent = null;

        $response = $this->controller->getEntityResponse($this->request, $entity, 2);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "data" => [
                    "id" => 2,
                    "name" => "Main Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "related" => [
                        "id" => 20,
                        "name" => "Related Entity Name",
                        "description" => null,
                        '_links' => [
                            'self' => 'https://api.example.com/related-test-entities/20/',
                        ],
                    ],
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/2/"],
            ],
            $body
        );
    }

    public function testEntityWithHasOne(): void {
        $entity = $this->createEntity(3, "Parent Entity");
        $entity->child = $this->createEntity(30, "Child Entity", TestRelatedEntity::class);

        $response = $this->controller->getEntityResponse($this->request, $entity, 3);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "data" => [
                    "id" => 3,
                    "name" => "Parent Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "child" => [
                        "id" => 30,
                        "name" => "Child Entity",
                        "description" => null,
                        '_links' => [
                            'self' => 'https://api.example.com/related-test-entities/30/',
                        ],
                    ],
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/3/"],
            ],
            $body
        );
    }

    public function testEntityWithHasMany(): void {
        $entity = $this->createEntity(4, "Parent Entity");
        $entity->children = [
            $this->createEntity(40, "Child 1", TestRelatedEntity::class),
            $this->createEntity(41, "Child 2", TestRelatedEntity::class),
        ];

        $response = $this->controller->getEntityResponse($this->request, $entity, 4);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "data" => [
                    "id" => 4,
                    "name" => "Parent Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "children" => [
                        [
                            "id" => 40,
                            "name" => "Child 1",
                            "description" => null,
                            '_links' => [
                                'self' => 'https://api.example.com/related-test-entities/40/',
                            ],
                        ],
                        [
                            "id" => 41,
                            "name" => "Child 2",
                            "description" => null,
                            '_links' => [
                                'self' => 'https://api.example.com/related-test-entities/41/',
                            ],
                        ],
                    ],
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/4/"],
            ],
            $body
        );
    }

    public function testEntityWithMultipleIncludes(): void {
        $entity = $this->createEntity(5, "Parent Entity");
        $entity->related = $this->createEntity(50, "Related Entity Name", TestRelatedEntity::class);
        $entity->child = $this->createEntity(51, "Child Entity", TestRelatedEntity::class);
        $entity->children = [
            $this->createEntity(52, "Child 1", TestRelatedEntity::class),
            $this->createEntity(53, "Child 2", TestRelatedEntity::class),
        ];

        $response = $this->controller->getEntityResponse($this->request, $entity, 5);
        $body = json_decode($response->getBody(), true);

        $this->assertSame(
            [
                "data" => [
                    "id" => 5,
                    "name" => "Parent Entity",
                    "description" => null,
                    "status" => null,
                    "category" => null,
                    "age" => null,
                    "related" => [
                        "id" => 50,
                        "name" => "Related Entity Name",
                        "description" => null,
                        '_links' => [
                            'self' => 'https://api.example.com/related-test-entities/50/',
                        ],
                    ],
                    "child" => [
                        "id" => 51,
                        "name" => "Child Entity",
                        "description" => null,
                        '_links' => [
                            'self' => 'https://api.example.com/related-test-entities/51/',
                        ],
                    ],
                    "children" => [
                        [
                            "id" => 52,
                            "name" => "Child 1",
                            "description" => null,
                            '_links' => [
                                'self' => 'https://api.example.com/related-test-entities/52/',
                            ],
                        ],
                        [
                            "id" => 53,
                            "name" => "Child 2",
                            "description" => null,
                            '_links' => [
                                'self' => 'https://api.example.com/related-test-entities/53/',
                            ],
                        ],
                    ],
                    "created_at" => null,
                ],
                "_links" => ["self" => "https://api.example.com/test-entities/5/"],
            ],
            $body
        );
    }
}
