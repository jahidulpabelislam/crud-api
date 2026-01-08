<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

use JPI\CRUD\API\AbstractController;

class TestController extends AbstractController {

    protected string $entityClass = TestEntity::class;

    protected array $publicActions = ["index", "read"];
}
