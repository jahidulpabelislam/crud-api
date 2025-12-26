<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\Database\Query\Clause\Where\OrCondition as WhereOrCondition;
use JPI\ORM\Entity\QueryBuilder;

/**
 * Trait providing full-text search functionality for entities.
 *
 * Entities using this trait can define searchable columns via the $searchableColumns property.
 * Searches use LIKE with wildcards and support multi-word queries in both forward and reverse order.
 *
 * @see \JPI\CRUD\API\Entity\SearchableInterface
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

        $where = new WhereOrCondition($query);
        foreach (static::getSearchableColumns() as $column) {
            $where
                ->where($column, "LIKE", ":search")
                ->where($column, "LIKE", ":searchReversed")
            ;
        }

        $query->where((string)$where);
    }
}
