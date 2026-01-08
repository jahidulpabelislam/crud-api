<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

use JPI\CRUD\API\AbstractEntity;
use JPI\CRUD\API\Entity\Filterable;
use JPI\CRUD\API\Entity\FilterableInterface;
use JPI\CRUD\API\Entity\Searchable;
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\CRUD\API\Entity\Sortable;
use JPI\CRUD\API\Entity\SortableInterface;

class TestEntity extends AbstractEntity implements SearchableInterface, FilterableInterface, SortableInterface {

    use Searchable;
    use Filterable;
    use Sortable;

    protected static string $displayName = "Test Entity";
    protected static string $table = "test_entities";
    protected static string $crudService = TestCrudService::class;

    protected static array $searchableColumns = ["name", "description"];
    protected static array $filterableColumns = ["status", "category"];
    protected static array $sortableColumns = ["name", "created_at", "status"];

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "description" => [
            "type" => "string",
        ],
        "status" => [
            "type" => "string",
        ],
        "category" => [
            "type" => "string",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];

    public static function getDatabase(): \JPI\Database {
        // Return a mock database for testing
        // In real tests, this would be mocked
        throw new \RuntimeException("Database not configured for tests");
    }
}
