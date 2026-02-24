<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

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
    protected static array $sortableColumns = ["id", "name", "created_at", "status"];

    protected static ?\JPI\Database $database = null;

    public static function getPluralDisplayName(): string {
        return "Test Entities";
    }

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
        "age" => [
            "type" => "int",
        ],
        "related" => [
            "type" => "belongs_to",
            "entity" => TestRelatedEntity::class,
        ],
        "child" => [
            "type" => "has_one",
            "entity" => TestRelatedEntity::class,
            "column" => "parent",
        ],
        "children" => [
            "type" => "has_many",
            "entity" => TestRelatedEntity::class,
            "column" => "parent",
        ],
        "created_at" => [
            "type" => "date_time",
        ],
    ];
}
