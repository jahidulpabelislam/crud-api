<?php

declare(strict_types=1);

namespace JPI\CRUD\API\Tests\Fixtures;

use JPI\CRUD\API\CrudService;

class TestCrudService extends CrudService {

    protected static array $requiredColumns = ["name", "created_at"];
}
