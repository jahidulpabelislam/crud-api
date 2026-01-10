<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Entity\Responder as EntityResponder;
use JPI\HTTP\RequestAwareTrait;
use JPI\HTTP\Response;
use JPI\ORM\Entity\PaginatedCollection;

/**
 * Base controller providing standard CRUD endpoints with authentication support.
 *
 * Child classes should set the $entityClass property and can define which actions
 * are public (no authentication required) via the $publicActions property.
 *
 * Standard CRUD actions:
 * - index(): List all entities with optional search, filtering, sorting, and pagination
 * - create(): Create a new entity
 * - read($id): Retrieve a specific entity
 * - update($id): Update an existing entity
 * - delete($id): Delete an entity
 */
abstract class AbstractController {

    use RequestAwareTrait;
    use EntityResponder;

    /**
     * Actions that don't require authentication.
     */
    protected array $publicActions = [];

    /**
     * The entity class this controller manages.
     *
     * @var class-string<AbstractEntity>
     */
    protected string $entityClass;

    public function getPublicActions(): array {
        return $this->publicActions;
    }

    public function getEntityInstance(): AbstractEntity {
        return new $this->entityClass();
    }

    public function getNotAuthorisedResponse(): Response {
        return Response::json(401, [
            "message" => "You need to be logged in!",
        ]);
    }

    public function getInvalidInputResponse(array $errors): Response {
        return Response::json(400, [
            "message" => "The necessary data was not provided and/or invalid.",
            "errors" => $errors,
        ]);
    }

    /**
     * Parse fields parameter from request and set it as an attribute.
     *
     * Extracts the comma-separated fields query parameter and converts it to an array,
     * then stores it as a request attribute for consistent access throughout the request lifecycle.
     */
    protected function parseFieldsAttribute(): void {
        $request = $this->getRequest();

        $fields = $request->getQueryParam("fields");
        if (!empty($fields) && is_string($fields)) {
            $fieldsArray = array_map("trim", explode(",", $fields));
            $request->setAttribute("fields", $fieldsArray);
        }
    }

    /**
     * Retrieves all entities with optional pagination, search, filters, and sorting.
     */
    public function index(): Response {
        $request = $this->getRequest();

        if (
            !in_array("index", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        $this->parseFieldsAttribute();

        $entities = $this->getEntityInstance()::getCrudService()->index($request);

        if ($entities instanceof PaginatedCollection) {
            return $this->getPaginatedEntitiesResponse($request, $entities);
        }

        return $this->getEntitiesResponse($request, $entities);
    }

    public function create(): Response {
        $request = $this->getRequest();

        if (
            !in_array("create", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        try {
            $entity = $this->getEntityInstance()::getCrudService()->create($request);
        }
        catch (InvalidDataException $exception) {
            return $this->getInvalidInputResponse($exception->getErrors());
        }

        return $this->getEntityCreateResponse($request, $entity);
    }

    public function read(int|string|null $id): Response {
        $request = $this->getRequest();

        if (
            !in_array("read", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        $this->parseFieldsAttribute();

        $entity = $this->getEntityInstance()::getCrudService()->read($request);
        return $this->getEntityResponse($request, $entity, $id);
    }

    public function update(int|string|null $id): Response {
        $request = $this->getRequest();

        if (
            !in_array("update", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        try {
            $entity = $this->getEntityInstance()::getCrudService()->update($request);
        }
        catch (InvalidDataException $exception) {
            return $this->getInvalidInputResponse($exception->getErrors());
        }

        return $this->getEntityUpdateResponse($request, $entity, $id);
    }

    public function delete(int|string|null $id): Response {
        $request = $this->getRequest();

        if (
            !in_array("delete", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return $this->getNotAuthorisedResponse();
        }

        $entity = $this->getEntityInstance()::getCrudService()->delete($request);
        return $this->getEntityDeleteResponse($request, $entity, $id);
    }
}
