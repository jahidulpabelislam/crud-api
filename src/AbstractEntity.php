<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use DateTime;
use JPI\HTTP\Request;
use JPI\ORM\Entity as BaseEntity;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\Utils\URL;
use ReflectionClass;

/**
 * Base entity class for CRUD API with support for generating API responses and HATEOAS links.
 */
abstract class AbstractEntity extends BaseEntity {

    /** @var class-string<CrudService> */
    protected static string $crudService = CrudService::class;

    public static function getDisplayName(): string {
        if (isset(static::$displayName)) {
            return static::$displayName;
        }

        return trim(preg_replace("/(?<!\s)[A-Z]/", " $0", (new ReflectionClass(static::class))->getShortName()), " ");
    }

    public static function getPluralDisplayName(): string {
        return static::getDisplayName() . "s";
    }

    public static function getCrudService(): CrudService {
        return new static::$crudService(static::class);
    }

    public function getAPIBasePath(): string
    {
        // Convert display name to lowercase and replace spaces with hyphens
        $basePath = strtolower(static::getPluralDisplayName());
        $basePath = str_replace(" ", "-", $basePath);
        return $basePath;
    }

    /**
     * Returns the API URL for this entity instance.
     */
    public function getAPIURL(?Request $request = null): URL
    {
        $baseURL = "/" . static::getAPIBasePath() . "/" . $this->getId() . "/";
        if (!$request) {
            return new URL($baseURL);
        }
        return $request->makeURL($baseURL);
    }

    /**
     * @param AbstractEntity|null $parentEntity Parent entity to detect circular references
     */
    public function getAPIResponse(Request $request, ?AbstractEntity $parentEntity = null): array {
        $response = [
            "id" => $this->getId(),
        ];

        $mapping = static::getDataMapping();

        $fields = $request->getAttribute("fields");
        $fields = $fields ? array_intersect(array_keys($mapping), $fields) : null;

        foreach ($this->data as $key => $value) {
            if (!array_key_exists("value", $value) || ($fields && !in_array($key, $fields))) {
                continue;
            }

            $value = $value["value"];

            if ($value instanceof self) {
                if ($parentEntity === $value) {
                    continue;
                }

                $value = $value->getAPIResponse($request, $this);
            }
            else if ($value instanceof EntityCollection) {
                if ($parentEntity && $mapping[$key]["entity"] === $parentEntity::class) {
                    continue;
                }

                $entities = $value;
                $value = [];
                /** @var AbstractEntity $entity */
                foreach ($entities as $entity) {
                    $entityResponse = $entity->getAPIResponse($request, $this);
                    $entityResponse["_links"] = $entity->getAPILinks($request);
                    $value[] = $entityResponse;
                }
            }
            else if ($value instanceof DateTime) {
                if ($mapping[$key]["type"] === "date_time") {
                    $value = $value->format("Y-m-d H:i:s e");
                }
                else {
                    $value = $value->format("Y-m-d");
                }
            }

            $response[$key] = $value;
        }

        return $response;
    }

    public function getAPILinks(Request $request): array {
        return [
            "self" => $this->getAPIURL($request),
        ];
    }
}
