<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Interface for entities that support filtering functionality.
 *
 * @see \JPI\CRUD\API\Entity\Filterable
 */
interface FilterableInterface {

    public static function getFilterableColumns(): array;

    public static function addFiltersToQuery(QueryBuilder $query, array $filters): void;
}
