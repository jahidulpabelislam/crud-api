<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

trait Filterable {

    public static function getFilterableColumns(): array {
        return static::$filterableColumns ?? static::getColumns();
    }

    public static function addFiltersToQuery(QueryBuilder $query, \ArrayAccess|array $filters): void {
        foreach (static::getFilterableColumns() as $column) {
            if (isset($filters[$column])) {
                $query->where($column, "=", $filters[$column]);
            }
        }
    }
}
