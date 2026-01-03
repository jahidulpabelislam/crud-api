<?php

declare(strict_types=1);

namespace JPI\CRUD\API;

use DateTime;
use JPI\ORM\Entity as BaseEntity;
use JPI\ORM\Entity\Collection as EntityCollection;
use JPI\Utils\URL;
use ReflectionClass;

abstract class AbstractEntity extends BaseEntity {

    protected static string $crudService = CrudService::class;

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

    public function getAPIResponse(?AbstractEntity $parentEntity = null): array {
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
                if ($parentEntity === $value) {
                    continue;
                }

                $value = $value->getAPIResponse($parentEntity ?: $this);
            }
            else if ($value instanceof EntityCollection) {
                if ($value[0] instanceof $parentEntity) {
                    continue;
                }

                $items = $value;
                $value = [];
                foreach ($items as $item) {
                    $itemResponse = $item->getAPIResponse($parentEntity ?: $this);
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
            "self" => $this->getAPIURL(),
        ];
    }
}
