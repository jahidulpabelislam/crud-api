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
        if (property_exists(static::class, 'sortableColumns')) {
            return static::$sortableColumns;
        }
        $columns = static::getColumns();
        $columns[] = "id"; // Allow sorting by ID too
        return $columns;
    }

    public static function addSortToQuery(QueryBuilder $query, iterable $sort): void {
        foreach ($sort as $entry) {
            $column = $entry;
            $ascDirection = true;

            // Parse column:direction syntax (e.g., "created_at:desc")
            if (is_string($column) && str_contains($column, ":")) {
                $parts = explode(":", $column, 2);
                $column = trim($parts[0]);

                // Check if direction part exists and is valid
                if (isset($parts[1]) && strtoupper(trim($parts[1])) === "DESC") {
                    $ascDirection = false;
                }
            }

            // Only add sort if column is in sortable columns list
            if (in_array($column, static::getSortableColumns())) {
                $query->orderBy($column, $ascDirection);
            }
        }
    }
}
