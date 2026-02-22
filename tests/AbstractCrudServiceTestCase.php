<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests;

use JPI\CRUD\API\Tests\Fixtures\TestEntity;
use JPI\Database;
use JPI\HTTP\Input;
use JPI\HTTP\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

abstract class AbstractCrudServiceTestCase extends TestCase {

    protected function createDatabase(string $entity = TestEntity::class): Database&MockObject {
        $database = $this->createMock(Database::class);
        $entity::setDatabase($database);
        return $database;
    }

    protected function createRequest(array $body = [], array $queryParams = [], array $attributes = []): Request&Stub {
        $request = $this->createStub(Request::class);

        $request->method("getArrayFromBody")->willReturn(new Input($body));

        $queryParams = new Input($queryParams);

        $request->method("getQueryParam")
            ->willReturnCallback(function (string $key) use ($queryParams) {
                return $queryParams[$key] ?? null;
            })
        ;

        $request->method("hasQueryParam")
            ->willReturnCallback(function (string $key) use ($queryParams) {
                return isset($queryParams[$key]);
            })
        ;

        $request->method("getAttribute")
            ->willReturnCallback(function (string $key) use ($attributes) {
                return $attributes[$key] ?? null;
            })
        ;

        return $request;
    }
}
