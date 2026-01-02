<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use DateTime;
use JPI\ORM\Entity as BaseEntity;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\Utils\URL;
use ReflectionClass;

/**
 * Base entity class for CRUD API with support for generating API responses and HATEOAS links.
 *
 * Child classes must implement getAPIURL() and can customise the CRUD service used
 * by setting the $crudService property.
 */
abstract class AbstractEntity extends BaseEntity {

    /** @var class-string<CrudService> */
    protected static string $crudService = CrudService::class;

    /**
     * Returns the API URL for this entity instance.
     *
     * This must be implemented by child classes to provide the canonical URL.
     */
    abstract public function getAPIURL(): URL;

    public static function getDisplayName(): string {
        if (isset(static::$displayName)) {
            return static::$displayName;
        }

        return (new ReflectionClass(static::class))->getShortName();
    }

    public static function getPluralDisplayName(): string {
        return static::getDisplayName() . "s";
    }

    public static function getCrudService(): CrudService {
        return new static::$crudService(static::class);
    }

    /**
     * Generates the API response representation of this entity.
     *
     * Handles nested entities up to a maximum depth of 3 to prevent circular references.
     * DateTime objects are formatted according to their type (date or date_time).
     *
     * @param int $depth Current recursion depth
     * @param AbstractEntity|null $parentEntity Parent entity to detect circular references
     */
    public function getAPIResponse(int $depth = 1, ?AbstractEntity $parentEntity = null): array {
        $response = [
            "id" => $this->getId(),
        ];

        $mapping = static::getDataMapping();

        foreach ($this->data as $column => $value) {
            if (!array_key_exists("value", $value)) {
                continue;
            }

            $value = $value["value"];

            if ($value instanceof self) {
                if ($depth > 2 || $parentEntity === $value) {
                    continue;
                }

                $value = $value->getAPIResponse($depth + 1, $this);
            }
            else if ($value instanceof EntityCollection) {
                if ($depth > 2) {
                    continue;
                }

                $items = $value;
                $value = [];
                foreach ($items as $item) {
                    $itemResponse = $item->getAPIResponse($depth + 1, $this);
                    $itemResponse["_links"] = $item->getAPILinks();
                    $value[] = $itemResponse;
                }
            }
            else if ($value instanceof DateTime) {
                if ($mapping[$column]["type"] === "date_time") {
                    $value = $value->format("Y-m-d H:i:s e");
                }
                else {
                    $value = $value->format("Y-m-d");
                }
            }

            $response[$column] = $value;
        }

        return $response;
    }

    public function getAPILinks(): array {
        return [
            "self" => (string)$this->getAPIURL(),
        ];
    }
}
