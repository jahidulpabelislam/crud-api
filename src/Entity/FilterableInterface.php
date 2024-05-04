<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\ORM\Entity\QueryBuilder;

interface FilterableInterface {

    public static function getFilterableColumns(): array;

    public static function addFiltersToQuery(QueryBuilder $query, array $filters): void;
}
