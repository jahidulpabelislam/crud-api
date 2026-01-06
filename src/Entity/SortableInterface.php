<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Interface for entities that support sorting functionality.
 *
 * @see \JPI\CRUD\API\Entity\Sortable
 */
interface SortableInterface {

    public static function getSortableColumns(): array;

    public static function addSortToQuery(QueryBuilder $query, iterable $sort): void;
}
