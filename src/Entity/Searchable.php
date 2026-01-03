<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

/**
 * Standard implementation of `SearchableInterface`.
 *
 * Searches use LIKE with wildcards and support multi-word queries in both forward and reverse order.
 */
trait Searchable {

    public static function getSearchableColumns(): array {
        return static::$searchableColumns ?? static::getColumns();
    }

    public static function addSearchToQuery(QueryBuilder $query, string $value): void {
        $words = explode(" ", $value);

        $query->params([
            "search" => "%" . implode("%", $words) . "%",
            "searchReversed" => "%" . implode("%", array_reverse($words)) . "%",
        ]);

        $where = $query->newOrCondition();
        foreach (static::getSearchableColumns() as $column) {
            $where
                ->where($column, "LIKE", ":search")
                ->where($column, "LIKE", ":searchReversed")
            ;
        }

        $query->where($where);
    }
}
