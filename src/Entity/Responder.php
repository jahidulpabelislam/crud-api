<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\CRUD\API\AbstractEntity;
use JPI\HTTP\Request;
use JPI\HTTP\Response;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;

/**
 * Trait providing standardised responses.
 *
 * Handles generation of consistent JSON responses for single entity, collections,
 * pagination metadata, and error scenarios.
 */
trait Responder {

    abstract public function getEntityInstance(): AbstractEntity;

    /**
     * Get fields parameter from request attribute.
     *
     * Returns array of field names or null if no fields attribute was set.
     */
    protected function getFieldsFromRequest(Request $request): ?array {
        return $request->getAttribute("fields");
    }

    /**
     * Response when collection of entities was requested.
     *
     * Includes entity data, HATEOAS links, and a message if none were found.
     */
    public function getEntitiesResponse(
        Request $request,
        EntityCollection $entities,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();
        $fields = $this->getFieldsFromRequest($request);

        $count = count($entities);
        $data = [];

        foreach ($entities as $entity) {
            $response = $entity->getAPIResponse(null, $fields);
            $response["_links"] = $entity->getAPILinks();
            $data[] = $response;
        }

        $content = [
            "data" => $data,
            "_links" => [
                "self" => $request->getURL(),
            ],
        ];

        if (!$count) {
            $content["message"] = "No {$entityInstance::getPluralDisplayName()} found.";
        }

        return Response::json(200, $content);
    }

    /**
     * Response when collection of paged entities was requested.
     *
     * Extends getEntitiesResponse with pagination metadata including:
     * - Total count and total pages
     * - Previous/next page links
     */
    public function getPaginatedEntitiesResponse(
        Request $request,
        PaginatedEntityCollection $collection,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $params = $request->getQueryParams();

        // The entities response is the base response, and the extra meta is added below
        $response = $this->getEntitiesResponse($request, $collection, $entityInstance);

        $content = $response->getBody();

        $content = json_decode($content, true);

        unset($content["_links"]);

        $totalCount = $collection->getTotalCount();
        $content["_total_count"] = $totalCount;

        $limit = $collection->getLimit();
        $page = $collection->getPage();

        $lastPage = ceil($totalCount / $limit);
        $content["_total_pages"] = $lastPage;

        $url = $request->getURL();

        // Always update to the used value if param was passed (incase it was different)
        if (isset($params["limit"])) {
            $params["limit"] = $limit;
        }
        if (isset($params["page"])) {
            $params["page"] = $page;
        }

        $url->setQueryParams($params->toArray());

        $content["_links"] = [
            "self" => $url,
        ];

        $hasPreviousPage = ($page > 1) && ($lastPage >= ($page - 1));
        if ($hasPreviousPage) {
            $url = clone $url;
            if ($page > 2) {
                $url->setQueryParam("page", $page - 1);
            }
            else {
                $url->removeQueryParam("page");
            }

            $content["_links"]["previous_page"] = $url;
        }

        $hasNextPage = $page < $lastPage;
        if ($hasNextPage) {
            $url = clone $url;
            $url->setQueryParam("page", $page + 1);
            $content["_links"]["next_page"] = $url;
        }

        return $response->withJSON($content);
    }

    private function getEntityFoundResponse(Request $request, AbstractEntity $entity): Response {
        $fields = $this->getFieldsFromRequest($request);
        return Response::json(200, [
            "data" => $entity->getAPIResponse(null, $fields),
            "_links" => $entity->getAPILinks(),
        ]);
    }

    public function getEntityNotFoundResponse(
        Request $request,
        string|int|null $id = null,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        $id = $id ?? $request->getAttribute("route_params")["id"];

        return Response::json(404, [
            "message" => "No {$entityInstance::getDisplayName()} identified by '$id' found.",
        ]);
    }

    /**
     * Response when an entity was requested.
     *
     * Includes entity data, HATEOAS links, and a message if not found.
     */
    public function getEntityResponse(
        Request $request,
        ?AbstractEntity $entity,
        string|int|null $id = null,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        $id = $id ?? $request->getAttribute("route_params")["id"];

        if ($id && $entity && $entity->isLoaded() && $entity->getId() == $id) {
            return $this->getEntityFoundResponse($request, $entity);
        }

        return $this->getEntityNotFoundResponse($request, $id, $entityInstance);
    }

    /**
     * Response for a request to create/insert entity.
     *
     * Returns 201 on successful creation, or 500 on failure.
     */
    public function getEntityCreateResponse(
        Request $request,
        ?AbstractEntity $entity,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        if ($entity && $entity->isLoaded()) {
            return $this->getEntityFoundResponse($request, $entity)
                ->withStatus(201)
                ->withHeader("Location", $entity->getAPIURL())
            ;
        }

        return Response::json(500, [
            "message" => "Failed to create the new {$entityInstance::getDisplayName()}.",
        ]);
    }

    /**
     * Response for a request to update entity.
     *
     * Returns updated entity data, HATEOAS links on successful update, 404 if not found, or 500 on failure.
     */
    public function getEntityUpdateResponse(
        Request $request,
        ?AbstractEntity $entity,
        string|int|null $id = null,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        $id = $id ?? $request->getAttribute("route_params")["id"];

        if ($id) {
            if (!$entity) {
                return $this->getEntityNotFoundResponse($request, $id, $entityInstance);
            }

            if ($entity->isLoaded() && $entity->getId() == $id) {
                return $this->getEntityFoundResponse($request, $entity);
            }
        }

        return Response::json(500, [
            "message" => "Failed to update the {$entityInstance::getDisplayName()} identified by '$id'.",
        ]);
    }

    /**
     * Response for a request to delete entity.
     *
     * Returns 204 on successful deletion, 404 if not found, or 500 on failure.
     */
    public function getEntityDeleteResponse(
        Request $request,
        ?AbstractEntity $entity,
        string|int|null $id = null,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        $id = $id ?? $request->getAttribute("route_params")["id"];

        if (!$id || !$entity || !$entity->isLoaded() || $entity->getId() != $id) {
            return $this->getEntityNotFoundResponse($request, $id, $entityInstance);
        }

        if ($entity->isDeleted()) {
            return Response::json(204);
        }

        return Response::json(500, [
            "message" => "Failed to delete the {$entityInstance::getDisplayName()} identified by '$id'.",
        ]);
    }
}
