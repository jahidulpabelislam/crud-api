<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Entity;

use JPI\CRUD\API\AbstractEntity;
use JPI\HTTP\Request;
use JPI\HTTP\Response;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\ORM\Entity\PaginatedCollection as PaginatedEntityCollection;

trait Responder {

    abstract public function getEntityInstance(): AbstractEntity;

    /**
     * Return a response when items were requested,
     * so check if some found return the items (with necessary meta)
     * else if not found return necessary meta
     */
    public function getEntitiesResponse(
        Request $request,
        EntityCollection $entities,
        ?AbstractEntity $entityInstance = null
    ): Response {
        $entityInstance = $entityInstance ?? $this->getEntityInstance();

        $count = count($entities);
        $data = [];

        foreach ($entities as $entity) {
            $response = $entity->getAPIResponse();
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
     * Return a response when items request was a search request,
     * so check if some found return the items (with necessary meta)
     * else if not found return necessary meta
     *
     * Use getItemsResponse function as the base response, then just adds additional meta data
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
        return Response::json(200, [
            "data" => $entity->getAPIResponse(),
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
     * Return a response when an item was requested,
     * so check if found return the item (with necessary meta)
     * else if not found return necessary meta
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

    public function getEntityInsertResponse(
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
            "message" => "Failed to insert the new {$entityInstance::getDisplayName()}.",
        ]);
    }

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
     * Return the response when an item was attempted to be deleted
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
