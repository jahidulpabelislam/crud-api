<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Standard implementation of `FilterableInterface`.
 *
 * Currently, supports only exact matches.
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
