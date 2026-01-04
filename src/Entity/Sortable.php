<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Standard implementation of `SortableInterface`.
 *
 * Supports sorting by multiple columns with ascending or descending order.
 * Prefix column name with '-' for DESC order, no prefix for ASC order.
 */
trait Sortable {

    public static function getSortableColumns(): array {
        return static::$sortableColumns ?? static::getColumns();
    }

    public static function addSortToQuery(QueryBuilder $query, iterable $sort): void {
        foreach ($sort as $sortColumn) {
            $direction = "ASC";
            $column = $sortColumn;

            // Check if column starts with '-' for DESC order
            if (is_string($sortColumn) && str_starts_with($sortColumn, "-")) {
                $direction = "DESC";
                $column = substr($sortColumn, 1);
            }

            // Only add sort if column is in sortable columns list
            if (in_array($column, static::getSortableColumns())) {
                $query->orderBy($column, $direction);
            }
        }
    }
}
