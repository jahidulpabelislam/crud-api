<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Interface for entities that support filtering functionality.
 *
 * Implement this interface and use the Filterable trait to enable filtering on your entities.
 */
interface FilterableInterface {

    /**
     * Returns the columns that can be filtered.
     */
    public static function getFilterableColumns(): array;

    /**
     * Adds filter conditions to a query builder.
     */
    public static function addFiltersToQuery(QueryBuilder $query, array $filters): void;
}
