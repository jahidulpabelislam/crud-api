<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\CRUD\API\Entity\FilterableInterface;
use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\HTTP\Request;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\InvalidValueException;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;

class CrudService {

    protected bool $paginated = true;
    protected int $perPage = 10;

    protected static array $requiredColumns = [];

    public function __construct(protected string $entityClass) {
    }

    public function getEntityInstance(): AbstractEntity {
        return new $this->entityClass();
    }

    public function getEntityFromRequest(Request $request): ?AbstractEntity {
        $id = $request->getAttribute("route_params")["id"];
        if (!is_numeric($id)) {
            return null;
        }

        return $this->getEntityInstance()
            ->getById((int)$request->getAttribute("route_params")["id"])
        ;
    }

    public function index(Request $request): EntityCollection {
        $entity = $this->getEntityInstance();

        $query = $entity::newQuery();

        if ($entity instanceof FilterableInterface) {
            $filters = $request->getQueryParam("filters");
            if ($filters) {
                $entity::addFiltersToQuery($query, $filters);
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
     * Checks the data in the request + sets entity values from valid data.
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
