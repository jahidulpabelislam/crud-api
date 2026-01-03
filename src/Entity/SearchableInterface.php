<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Interface for entities that support search functionality.
 *
 * @see \JPI\CRUD\API\Entity\Searchable
 */
interface SearchableInterface {

    public static function getSearchableColumns(): array;

    public static function addSearchToQuery(QueryBuilder $query, string $value): void;
}
