<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

use JPI\CRUD\API\AbstractEntity as BaseAbstractEntity;

abstract class AbstractEntity extends BaseAbstractEntity {

    protected static array $registry = [];

    public static function setDatabase(\JPI\Database $database): void {
        static::$database = $database;
    }

    public static function getDatabase(): \JPI\Database {
        if (static::$database === null) {
            throw new \RuntimeException("Database not configured for tests");
        }
        return static::$database;
    }

    /**
     * Try ignore registry.
     */
    public static function getById(int $id): ?static {
        static::$registry = [];
        return parent::getById($id);
    }

    /**
     * Try ignore registry.
     */
    public static function loadFromDatabaseRow(array $row): static {
        static::$registry = [];
        return parent::loadFromDatabaseRow($row);
    }
}
