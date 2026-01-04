<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Standard implementation of `SortableInterface`.
 *
 * Supports sorting by multiple columns with ascending or descending order.
 * Use column:direction syntax (e.g., 'created_at:desc'). Direction defaults to ASC if not specified.
 */
trait Sortable {

    public static function getSortableColumns(): array {
        return static::$sortableColumns ?? static::getColumns();
    }

    public static function addSortToQuery(QueryBuilder $query, iterable $sort): void {
        foreach ($sort as $sortColumn) {
            $direction = "ASC";
            $column = $sortColumn;

            // Parse column:direction syntax (e.g., "created_at:desc")
            if (is_string($sortColumn) && str_contains($sortColumn, ":")) {
                $parts = explode(":", $sortColumn, 2);
                $column = $parts[0];
                $specifiedDirection = strtoupper($parts[1] ?? "");
                
                // Only accept valid directions
                if (in_array($specifiedDirection, ["ASC", "DESC"])) {
                    $direction = $specifiedDirection;
                }
            }

            // Only add sort if column is in sortable columns list
            if (in_array($column, static::getSortableColumns())) {
                $query->orderBy($column, $direction);
            }
        }
    }
}
