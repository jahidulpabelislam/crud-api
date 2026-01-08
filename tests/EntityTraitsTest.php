<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\ORM\Entity\QueryBuilder;
use PHPUnit\Framework\TestCase;

class EntityTraitsTest extends TestCase {

    public function testSearchableGetSearchableColumns(): void {
        $columns = TestEntity::getSearchableColumns();
        
        $this->assertIsArray($columns);
        $this->assertContains("name", $columns);
        $this->assertContains("description", $columns);
    }

    public function testSearchableAddSearchToQuery(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        // Expect params to be set
        $query->expects($this->once())
            ->method('params')
            ->with($this->callback(function ($params) {
                return isset($params['search']) && isset($params['searchReversed']);
            }));
        
        // Expect newOrCondition to be called
        $orCondition = $this->createMock(QueryBuilder::class);
        $orCondition->method('where')->willReturnSelf();
        
        $query->expects($this->once())
            ->method('newOrCondition')
            ->willReturn($orCondition);
        
        // Expect where to be called with the OR condition
        $query->expects($this->once())
            ->method('where')
            ->with($orCondition);
        
        TestEntity::addSearchToQuery($query, "test search");
    }

    public function testSearchableHandlesMultiWordSearch(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        // Capture the params
        $capturedParams = null;
        $query->expects($this->once())
            ->method('params')
            ->willReturnCallback(function ($params) use (&$capturedParams) {
                $capturedParams = $params;
            });
        
        $orCondition = $this->createMock(QueryBuilder::class);
        $orCondition->method('where')->willReturnSelf();
        $query->method('newOrCondition')->willReturn($orCondition);
        $query->method('where');
        
        TestEntity::addSearchToQuery($query, "hello world");
        
        // Verify params contain wildcards and word order
        $this->assertNotNull($capturedParams);
        $this->assertArrayHasKey('search', $capturedParams);
        $this->assertArrayHasKey('searchReversed', $capturedParams);
        $this->assertEquals("%hello%world%", $capturedParams['search']);
        $this->assertEquals("%world%hello%", $capturedParams['searchReversed']);
    }

    public function testFilterableGetFilterableColumns(): void {
        $columns = TestEntity::getFilterableColumns();
        
        $this->assertIsArray($columns);
        $this->assertContains("status", $columns);
        $this->assertContains("category", $columns);
    }

    public function testFilterableAddFiltersToQuery(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $filters = [
            "status" => "active",
            "category" => "test",
        ];
        
        // Expect where to be called twice (once for each filter)
        $query->expects($this->exactly(2))
            ->method('where')
            ->willReturnSelf();
        
        TestEntity::addFiltersToQuery($query, $filters);
    }

    public function testFilterableIgnoresNonFilterableColumns(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $filters = [
            "status" => "active",
            "nonexistent_column" => "value", // Should be ignored
        ];
        
        // Expect where to be called only once (for status)
        $query->expects($this->once())
            ->method('where')
            ->with("status", "=", "active")
            ->willReturnSelf();
        
        TestEntity::addFiltersToQuery($query, $filters);
    }

    public function testSortableGetSortableColumns(): void {
        $columns = TestEntity::getSortableColumns();
        
        $this->assertIsArray($columns);
        $this->assertContains("name", $columns);
        $this->assertContains("created_at", $columns);
        $this->assertContains("status", $columns);
    }

    public function testSortableAddSortToQueryWithAscending(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $query->expects($this->once())
            ->method('orderBy')
            ->with("name", true); // true = ascending
        
        TestEntity::addSortToQuery($query, ["name:asc"]);
    }

    public function testSortableAddSortToQueryWithDescending(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $query->expects($this->once())
            ->method('orderBy')
            ->with("created_at", false); // false = descending
        
        TestEntity::addSortToQuery($query, ["created_at:desc"]);
    }

    public function testSortableAddSortToQueryDefaultsToAscending(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $query->expects($this->once())
            ->method('orderBy')
            ->with("name", true); // true = ascending (default)
        
        TestEntity::addSortToQuery($query, ["name"]); // No direction specified
    }

    public function testSortableAddSortToQueryWithMultipleColumns(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        // Expect orderBy to be called multiple times
        $query->expects($this->exactly(3))
            ->method('orderBy')
            ->willReturnSelf();
        
        TestEntity::addSortToQuery($query, ["name:asc", "created_at:desc", "status"]);
    }

    public function testSortableIgnoresNonSortableColumns(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        // Should not call orderBy for non-sortable column
        $query->expects($this->never())
            ->method('orderBy');
        
        TestEntity::addSortToQuery($query, ["nonexistent_column:asc"]);
    }

    public function testSortableHandlesCaseInsensitiveDirection(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $query->expects($this->once())
            ->method('orderBy')
            ->with("name", false); // DESC
        
        TestEntity::addSortToQuery($query, ["name:DESC"]);
    }

    public function testSortableHandlesWhitespaceInDirective(): void {
        $query = $this->createMock(QueryBuilder::class);
        
        $query->expects($this->once())
            ->method('orderBy')
            ->with("name", false); // DESC
        
        TestEntity::addSortToQuery($query, ["name : desc"]); // Spaces around colon
    }
}
