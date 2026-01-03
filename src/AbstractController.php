<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use JPI\CRUD\API\Entity\InvalidDataException;
use JPI\CRUD\API\Entity\Responder as EntityResponder;
use JPI\HTTP\RequestAwareTrait;
use JPI\HTTP\Response;
use JPI\ORM\Entity\PaginatedCollection;

abstract class AbstractController {

    use RequestAwareTrait;
    use EntityResponder;

    protected array $publicActions = [];

    protected string $entityClass;

    public function getPublicActions(): array {
        return $this->publicActions;
    }

    public function getEntityInstance(): AbstractEntity {
        return new $this->entityClass();
    }

    /**
     * Response when user isn't logged in correctly
     */
    public static function getNotAuthorisedResponse(): Response {
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
     * Gets all entities but paginated (also might include search & filters)
     */
    public function index(): Response {
        $request = $this->getRequest();

        if (
            !in_array("index", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return static::getNotAuthorisedResponse();
        }

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
            return static::getNotAuthorisedResponse();
        }

        try {
            $entity = $this->getEntityInstance()::getCrudService()->create($request);
        } catch (InvalidDataException $exception) {
            return $this->getInvalidInputResponse($exception->getErrors());
        }

        return $this->getEntityInsertResponse($request, $entity);
    }

    public function read(int|string|null $id): Response {
        $request = $this->getRequest();

        if (
            !in_array("read", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return static::getNotAuthorisedResponse();
        }

        $entity = $this->getEntityInstance()::getCrudService()->read($request);
        return $this->getEntityResponse($request, $entity, $id);
    }

    public function update(int|string|null $id): Response {
        $request = $this->getRequest();

        if (
            !in_array("update", $this->getPublicActions())
            && !$request->getAttribute("is_authenticated")
        ) {
            return static::getNotAuthorisedResponse();
        }

        try {
            $entity = $this->getEntityInstance()::getCrudService()->update($request);
        } catch (InvalidDataException $exception) {
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
            return static::getNotAuthorisedResponse();
        }

        $entity = $this->getEntityInstance()::getCrudService()->delete($request);
        return $this->getEntityDeletedResponse($request, $entity, $id);
    }
}
