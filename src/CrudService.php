<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use ArrayAccess;
use JPI\CRUD\API\Entity\FilterableInterface;
use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\CRUD\API\Entity\SortableInterface;
use JPI\HTTP\Request;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\InvalidValueException;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;

/**
 * Service layer for handling CRUD operations on entities.
 *
 * Provides standardised methods for creating, reading, updating, and deleting entities
 * with built-in validation, pagination, search, filtering, and sorting support.
 */
class CrudService {

    protected ?int $perPage = 10;

    /**
     * Columns that must be present and non-empty when creating entities.
     */
    protected static array $requiredColumns = [];

    /** @param class-string<CrudService> $entityClass */
    public function __construct(protected string $entityClass) {
    }

    public function getEntityInstance(): AbstractEntity {
        return new $this->entityClass();
    }

    /**
     * Parse and return relationships to eager load from the request's include parameter.
     *
     * @return string[]
     */
    private function getRelationsFromRequest(Request $request): array {
        $include = $request->getQueryParam("include");
        if (!is_string($include) || empty($include)) {
            return [];
        }

        return array_filter(array_map("trim", explode(",", $include)));
    }

    public function getEntityFromRequest(Request $request): ?AbstractEntity {
        $id = $request->getAttribute("route_params")["id"];
        if (!is_numeric($id)) {
            return null;
        }

        $relations = $this->getRelationsFromRequest($request);
        if (!empty($relations)) {
            // Use query builder with eager loading when relationships are requested
            return $this->getEntityInstance()::newQuery()
                ->where("id", "=", (int)$id)
                ->with(...$relations)
                ->select();
        }

        return $this->getEntityInstance()->getById((int)$id);
    }

    /**
     * Retrieves a collection of entities with optional search, filtering, sorting, and pagination.
     *
     * Processes search, filters, and sort query parameters based on entity interfaces implemented,
     * then applies pagination if enabled. See README for query parameter details.
     */
    public function index(Request $request): EntityCollection {
        $entity = $this->getEntityInstance();

        $query = $entity::newQuery();

        if ($entity instanceof FilterableInterface) {
            $filters = $request->getQueryParam("filters");
            if (is_array($filters) || $filters instanceof ArrayAccess) {
                $entity::addFiltersToQuery($query, $filters);
            }
        }

        if ($entity instanceof SearchableInterface) {
            $search = $request->getQueryParam("search");
            if (is_string($search)) {
                $entity::addSearchToQuery($query, $search);
            }
        }

        if ($entity instanceof SortableInterface) {
            $sort = $request->getQueryParam("sort");
            if (is_string($sort)) {
                // Comma-separated values
                $sort = array_filter(array_map("trim", explode(",", $sort)));
                $entity::addSortToQuery($query, $sort);
            }
        }

        $relations = $this->getRelationsFromRequest($request);
        if (!empty($relations)) {
            $query->with(...$relations);
        }

        if ($this->perPage === null) {
            return $query->select();
        }

        $limit = $request->getQueryParam("limit");
        if (!$limit || !is_numeric($limit) || $limit < 1) {
            $limit = $this->perPage;
        }

        $page = $request->hasQueryParam("page") ? $request->getQueryParam("page") : 1;

        // If invalid use page 1
        if (!$page || !is_numeric($page) || $page < 1) {
            $page = 1;
        }

        $query->limit((int)$limit, (int)$page);

        $entities = $query->select();

        // Handle where limit is 1
        if ($entities instanceof $this->entityClass) {
            $entities = new PaginatedEntityCollection([$entities], $query->count(), $limit, $page);
        }

        return $entities;
    }

    /**
     * @throws InvalidDataException If validation fails, with detailed error messages
     */
    protected function setValuesFromRequest(AbstractEntity $entity, Request $request): void {
        $errors = [];

        $requiredColumns = static::$requiredColumns;

        $data = $request->getArrayFromBody();

        $mapping = $entity::getDataMapping();

        // Make sure data submitted is all valid.
        foreach ($entity::getColumns() as $column) {
            if (!isset($data[$column])) {
                if (!$entity->isLoaded() && in_array($column, $requiredColumns)) {
                    $errors[$column] = "`$column` is required.";
                }
                continue;
            }

            $value = $data[$column];

            if (empty($value) && in_array($column, $requiredColumns)) {
                $errors[$column] = "`$column` cannot be empty.";
                continue;
            }

            try {
                $entity->$column = $value;
            }
            catch (InvalidValueException $exception) {
                $errorMessage = $exception->getMessage();
                if (in_array($column, $requiredColumns)) {
                    $errorMessage = str_replace(" or null", "", $errorMessage);
                }

                $errors[$column] = $errorMessage;
            }
        }

        if (!empty($errors)) {
            throw new InvalidDataException($errors);
        }
    }

    /**
     * @throws InvalidDataException
     */
    public function create(Request $request): AbstractEntity {
        $entity = $this->getEntityInstance();
        $this->setValuesFromRequest($entity, $request);
        $entity->save();
        $entity->reload();

        return $entity;
    }

    public function read(Request $request): ?AbstractEntity {
        return $this->getEntityFromRequest($request);
    }

    /**
     * @throws InvalidDataException
     */
    public function update(Request $request): ?AbstractEntity {
        $entity = $this->getEntityFromRequest($request);

        if (!$entity) {
            return null;
        }

        $this->setValuesFromRequest($entity, $request);

        $entity->save();
        $entity->reload();

        return $entity;
    }

    public function delete(Request $request): ?AbstractEntity {
        $entity = $this->getEntityFromRequest($request);

        if ($entity) {
            $entity->delete();
        }

        return $entity;
    }
}
