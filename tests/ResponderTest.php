<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestController;
use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\HTTP\Request;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;
use JPI\Utils\URL;
use PHPUnit\Framework\TestCase;

class ResponderTest extends TestCase {

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

    public function testGetEntitiesResponseWithEmptyCollection(): void {
        $entities = new EntityCollection([]);
        
        $response = $this->controller->getEntitiesResponse($this->request, $entities);
        
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_links", $body);
        $this->assertArrayHasKey("message", $body);
        $this->assertIsArray($body["data"]);
        $this->assertEmpty($body["data"]);
        $this->assertEquals("No Test Entities found.", $body["message"]);
        $this->assertArrayHasKey("self", $body["_links"]);
    }

    public function testGetEntitiesResponseWithEntities(): void {
        // Create mock entities
        $entity1 = $this->createMockEntity(1, "Test 1");
        $entity2 = $this->createMockEntity(2, "Test 2");
        
        $entities = new EntityCollection([$entity1, $entity2]);
        
        $response = $this->controller->getEntitiesResponse($this->request, $entities);
        
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_links", $body);
        $this->assertArrayNotHasKey("message", $body);
        $this->assertCount(2, $body["data"]);
        
        // Check first entity
        $this->assertArrayHasKey("id", $body["data"][0]);
        $this->assertArrayHasKey("_links", $body["data"][0]);
        $this->assertEquals(1, $body["data"][0]["id"]);
        
        // Check second entity
        $this->assertArrayHasKey("id", $body["data"][1]);
        $this->assertArrayHasKey("_links", $body["data"][1]);
        $this->assertEquals(2, $body["data"][1]["id"]);
    }

    public function testGetPaginatedEntitiesResponseStructure(): void {
        $entity1 = $this->createMockEntity(1, "Test 1");
        $entity2 = $this->createMockEntity(2, "Test 2");
        
        $collection = new PaginatedEntityCollection(
            [$entity1, $entity2],
            25, // total count
            10, // limit
            1   // page
        );
        
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        
        // Check required pagination metadata
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_total_count", $body);
        $this->assertArrayHasKey("_total_pages", $body);
        $this->assertArrayHasKey("_links", $body);
        
        $this->assertEquals(25, $body["_total_count"]);
        $this->assertEquals(3, $body["_total_pages"]); // 25 / 10 = 3 pages
        
        // Check links
        $this->assertArrayHasKey("self", $body["_links"]);
        $this->assertArrayHasKey("next_page", $body["_links"]);
        $this->assertArrayNotHasKey("previous_page", $body["_links"]); // First page
    }

    public function testGetPaginatedEntitiesResponseLastPage(): void {
        $entity = $this->createMockEntity(1, "Test");
        
        $collection = new PaginatedEntityCollection(
            [$entity],
            25, // total count
            10, // limit
            3   // page 3 (last page)
        );
        
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        
        $body = json_decode($response->getBody(), true);
        
        // Should have previous_page link but not next_page
        $this->assertArrayHasKey("previous_page", $body["_links"]);
        $this->assertArrayNotHasKey("next_page", $body["_links"]);
    }

    public function testGetPaginatedEntitiesResponseMiddlePage(): void {
        $entity = $this->createMockEntity(1, "Test");
        
        $collection = new PaginatedEntityCollection(
            [$entity],
            30, // total count
            10, // limit
            2   // page 2 (middle page)
        );
        
        $response = $this->controller->getPaginatedEntitiesResponse($this->request, $collection);
        
        $body = json_decode($response->getBody(), true);
        
        // Should have both previous_page and next_page links
        $this->assertArrayHasKey("previous_page", $body["_links"]);
        $this->assertArrayHasKey("next_page", $body["_links"]);
    }

    public function testGetEntityNotFoundResponse(): void {
        $response = $this->controller->getEntityNotFoundResponse($this->request, 123);
        
        $this->assertEquals(404, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("123", $body["message"]);
        $this->assertStringContainsString("Test Entity", $body["message"]);
    }

    public function testGetEntityResponseWhenFound(): void {
        $entity = $this->createMockEntity(1, "Test Entity");
        
        $this->request->setAttribute("route_params", ["id" => "1"]);
        
        $response = $this->controller->getEntityResponse($this->request, $entity, 1);
        
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_links", $body);
        $this->assertEquals(1, $body["data"]["id"]);
    }

    public function testGetEntityResponseWhenNotFound(): void {
        $response = $this->controller->getEntityResponse($this->request, null, 999);
        
        $this->assertEquals(404, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("999", $body["message"]);
    }

    public function testGetEntityCreateResponseSuccess(): void {
        $entity = $this->createMockEntity(1, "New Entity");
        
        $response = $this->controller->getEntityCreateResponse($this->request, $entity);
        
        $this->assertEquals(201, $response->getStatus());
        $this->assertTrue($response->hasHeader("Location"));
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_links", $body);
    }

    public function testGetEntityCreateResponseFailure(): void {
        // Pass null to simulate creation failure
        $response = $this->controller->getEntityCreateResponse($this->request, null);
        
        $this->assertEquals(500, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("Failed to create", $body["message"]);
    }

    public function testGetEntityUpdateResponseSuccess(): void {
        $entity = $this->createMockEntity(1, "Updated Entity");
        
        $response = $this->controller->getEntityUpdateResponse($this->request, $entity, 1);
        
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("data", $body);
        $this->assertArrayHasKey("_links", $body);
    }

    public function testGetEntityUpdateResponseNotFound(): void {
        $response = $this->controller->getEntityUpdateResponse($this->request, null, 999);
        
        $this->assertEquals(404, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("999", $body["message"]);
    }

    public function testGetEntityUpdateResponseFailure(): void {
        // Create a mock entity that's loaded but with wrong ID
        $entity = $this->createMockEntity(2, "Entity");
        
        $response = $this->controller->getEntityUpdateResponse($this->request, $entity, 1);
        
        $this->assertEquals(500, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("Failed to update", $body["message"]);
    }

    public function testGetEntityDeleteResponseSuccess(): void {
        $entity = $this->createMockEntity(1, "Entity");
        $entity->method('isDeleted')->willReturn(true);
        
        $response = $this->controller->getEntityDeleteResponse($this->request, $entity, 1);
        
        $this->assertEquals(204, $response->getStatus());
        $this->assertEmpty($response->getBody());
    }

    public function testGetEntityDeleteResponseNotFound(): void {
        $response = $this->controller->getEntityDeleteResponse($this->request, null, 999);
        
        $this->assertEquals(404, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
    }

    public function testGetEntityDeleteResponseFailure(): void {
        $entity = $this->createMockEntity(1, "Entity");
        $entity->method('isDeleted')->willReturn(false);
        
        $response = $this->controller->getEntityDeleteResponse($this->request, $entity, 1);
        
        $this->assertEquals(500, $response->getStatus());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey("message", $body);
        $this->assertStringContainsString("Failed to delete", $body["message"]);
    }

    private function createMockEntity(int $id, string $name) {
        $entity = $this->createMock(TestEntity::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('isLoaded')->willReturn(true);
        $entity->method('getAPIResponse')->willReturn([
            "id" => $id,
            "name" => $name,
        ]);
        $entity->method('getAPILinks')->willReturn([
            "self" => new URL("https://api.example.com/test-entities/{$id}/"),
        ]);
        $entity->method('isDeleted')->willReturn(false);
        
        return $entity;
    }
}
