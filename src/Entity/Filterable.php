<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Trait providing filtering functionality for entities.
 *
 * Entities using this trait can define filterable columns via the $filterableColumns property.
 * Filters are applied as equality conditions on the specified columns.
 *
 * @see FilterableInterface
 */
trait Filterable {

    public static function getFilterableColumns(): array {
        return static::$filterableColumns ?? static::getColumns();
    }

    public static function addFiltersToQuery(QueryBuilder $query, array $filters): void {
        foreach (static::getFilterableColumns() as $column) {
            if (array_key_exists($column, $filters)) {
                $query->where($column, "=", $filters[$column]);
            }
        }
    }
}
