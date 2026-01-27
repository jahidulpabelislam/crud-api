<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

use JPI\CRUD\API\AbstractEntity;

/**
 * A related test entity for testing relationship functionality.
 * This can represent related entities like authors, categories, etc.
 */
class TestRelatedEntity extends AbstractEntity {

    protected static string $displayName = "Related Test Entity";
    protected static string $table = "related_test_entities";

    private static ?\JPI\Database $database = null;

    public static function getPluralDisplayName(): string {
        return "Related Test Entities";
    }

    protected static array $dataMapping = [
        "name" => [
            "type" => "string",
        ],
        "description" => [
            "type" => "string",
        ],
    ];

    public static function setDatabase(\JPI\Database $database): void {
        self::$database = $database;
    }

    public static function getDatabase(): \JPI\Database {
        if (self::$database === null) {
            throw new \RuntimeException("Database not configured for tests");
        }
        return self::$database;
    }
}
