<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use DateTime;
use Exception;
use JPI\CRUD\API\Entity\FilterableInterface;
use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\HTTP\Request;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\InvalidValueException;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;

/**
 * Service layer for handling CRUD operations on entities.
 *
 * Provides standardised methods for creating, reading, updating, and deleting entities
 * with built-in validation, pagination, search, and filtering support.
 */
class CrudService {

    protected bool $paginated = true;
    protected int $perPage = 10;

    /**
     * Columns that must be present and non-empty when creating entities.
     */
    protected static array $requiredColumns = [];

    public function __construct(protected string $entityClass) {
    }

    /**
     * Returns a new instance of the entity managed by this service.
     */
    public function getEntityInstance(): AbstractEntity {
        return new $this->entityClass();
    }

    /**
     * Retrieves an entity by ID from the request route parameters.
     *
     * Returns null if the ID is not numeric or the entity is not found.
     */
    public function getEntityFromRequest(Request $request): ?AbstractEntity {
        $id = $request->getAttribute("route_params")["id"];
        if (!is_numeric($id)) {
            return null;
        }

        return $this->getEntityInstance()
            ->getById((int)$request->getAttribute("route_params")["id"])
        ;
    }

    /**
     * Retrieves a collection of entities with optional search, filtering, and pagination.
     *
     * Supports query parameters:
     * - search: Text search across searchable columns (if entity implements SearchableInterface)
     * - filters: Key-value pairs for filtering (if entity implements FilterableInterface)
     * - page: Page number for pagination (default: 1)
     * - limit: Results per page (default: configured perPage value)
     */
    public function index(Request $request): EntityCollection {
        $entity = $this->getEntityInstance();

        $query = $entity::newQuery();

        if ($entity instanceof FilterableInterface) {
            $filters = $request->getQueryParam("filters");
            if ($filters) {
                $entity::addFiltersToQuery($query, $filters->toArray());
            }
        }

        if ($entity instanceof SearchableInterface) {
            $search = $request->getQueryParam("search");
            if ($search) {
                $entity::addSearchToQuery($query, $search);
            }
        }

        if (!$this->paginated) {
            return $query->select();
        }

        $limit = (int)$request->getQueryParam("limit");
        if (!$limit) {
            $limit = $this->perPage;
        }

        $page = $request->hasQueryParam("page") ? $request->getQueryParam("page") : 1;

        if (is_numeric($page)) {
            $page = (int)$page;
        }

        // If invalid use page 1
        if (!$page || $page < 1) {
            $page = 1;
        }

        $query->limit($limit, $page);

        $entities = $query->select();

        // Handle where limit is 1
        if ($entities instanceof $this->entityClass) {
            $entities = new PaginatedEntityCollection([$entities], $query->count(), $limit, $page);
        }

        return $entities;
    }

    /**
     * Validates and sets entity values from request data.
     *
     * @throws InvalidDataException If validation fails, with detailed error messages
     */
    protected function setValuesFromRequest(AbstractEntity $entity, Request $request): void {
        $errors = [];

        $requiredColumns = static::$requiredColumns;

        $data = $request->getArrayFromBody()->toArray();

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
     * Creates a new entity from request data.
     *
     * @throws InvalidDataException If validation fails
     */
    public function create(Request $request): AbstractEntity {
        $entity = $this->getEntityInstance();
        $this->setValuesFromRequest($entity, $request);
        $entity->save();
        $entity->reload();

        return $entity;
    }

    /**
     * Retrieves an entity by ID from the request.
     *
     * Returns null if not found.
     */
    public function read(Request $request): ?AbstractEntity {
        return $this->getEntityFromRequest($request);
    }

    /**
     * Updates an existing entity with request data.
     *
     * Returns null if the entity is not found.
     *
     * @throws InvalidDataException If validation fails
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

    /**
     * Deletes an entity by ID from the request.
     *
     * Returns the deleted entity or null if not found.
     */
    public function delete(Request $request): ?AbstractEntity {
        $entity = $this->getEntityFromRequest($request);

        if ($entity) {
            $entity->delete();
        }

        return $entity;
    }
}
