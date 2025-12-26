<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Interface for entities that support filtering functionality.
 *
 * Implement this interface and use the Filterable trait to enable filtering on your entities.
 *
 * @see Filterable
 */
interface FilterableInterface {

    public static function getFilterableColumns(): array;

    public static function addFiltersToQuery(QueryBuilder $query, array $filters): void;
}
