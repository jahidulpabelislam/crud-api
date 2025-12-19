# CRUD API Framework

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api)
[![Latest Stable Version](https://poser.pugx.org/jpi/crud/v/stable)](https://packagist.org/packages/jpi/crud)
[![Total Downloads](https://poser.pugx.org/jpi/crud/downloads)](https://packagist.org/packages/jpi/crud)
[![Latest Unstable Version](https://poser.pugx.org/jpi/crud/v/unstable)](https://packagist.org/packages/jpi/crud)
[![Licence](https://poser.pugx.org/jpi/crud/license)](https://packagist.org/packages/jpi/crud)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/crud-api/1.x.svg?label=last%20activity)

A lightweight PHP framework for building RESTful CRUD APIs with built-in support for pagination, search, filtering, and authentication.

## Features

- **RESTful CRUD Operations**: Out-of-the-box support for Create, Read, Update, and Delete operations
- **Pagination**: Built-in pagination support with configurable page size
- **Search & Filtering**: Flexible search and filter functionality using traits
- **Authentication**: Built-in authentication checks for protected endpoints
- **Type Safety**: Full PHP 8+ type declarations for improved code quality
- **JSON Responses**: Standardised JSON response format with HATEOAS links
- **Validation**: Comprehensive data validation with detailed error messages
- **Extensible**: Easy to extend with custom business logic

## Requirements

- PHP 8.0+
- Composer
- [jpi/http](https://github.com/jahidulpabelislam/http) - HTTP request/response handling
- [jpi/orm](https://github.com/jahidulpabelislam/orm) - Database ORM layer
- [jpi/utils](https://github.com/jahidulpabelislam/utils) - Utility functions

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require jpi/crud-api
```

## Core Components

### AbstractEntity

Base entity class that extends the ORM Entity and provides API-specific functionality.

**Key Methods:**
- `getAPIURL()`: Returns the API URL for the entity (must be implemented by child classes)
- `getAPIResponse()`: Generates the JSON-serialisable response for the entity
- `getAPILinks()`: Returns HATEOAS links for the entity
- `getCrudService()`: Returns the associated CRUD service instance
- `getDisplayName()`: Returns the human-readable display name (singular)
- `getPluralDisplayName()`: Returns the human-readable display name (plural)

### AbstractController

Base controller class providing standard CRUD endpoints with authentication support.

**Standard Actions:**
- `index()`: GET - List all entities (with pagination, search, and filters)
- `create()`: POST - Create a new entity
- `read($id)`: GET - Retrieve a specific entity
- `update($id)`: PUT - Update a specific entity
- `delete($id)`: DELETE - Delete a specific entity

### CrudService

Service layer handling CRUD operations and data validation.

**Key Features:**
- Automatic request data validation
- Support for required fields
- Search and filter integration
- Pagination support

### Router

Extended HTTP router with built-in 404 and 405 error handlers.

### Entity Traits

**Searchable**: Adds search functionality to entities
- Define searchable columns via `$searchableColumns` property
- Multi-word search support

**Filterable**: Adds filtering functionality to entities
- Define filterable columns via `$filterableColumns` property
- Equality-based filtering

**Responder**: Provides standardised response methods for controllers
- Item responses with proper status codes
- Collection responses with pagination metadata
- Error responses (not found, validation errors)

## Basic Usage

### 1. Create an Entity

```php
<?php

namespace App;

use JPI\CRUD\API\AbstractEntity;
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\CRUD\API\Entity\Searchable;
use JPI\Utils\URL;

class Project extends AbstractEntity implements SearchableInterface {
    use Searchable;

    protected static string $table = "projects";
    
    protected static array $searchableColumns = ["name", "description"];
    
    protected static array $dataMapping = [
        "name" => ["type" => "string"],
        "description" => ["type" => "string"],
        "created_at" => ["type" => "date_time"],
    ];

    public function getAPIURL(): URL {
        return new URL("https://api.example.com/projects/{$this->getId()}");
    }
}
```

### 2. Create a Controller

```php
<?php

namespace App;

use JPI\CRUD\API\AbstractController;

class ProjectController extends AbstractController {
    
    protected string $entityClass = Project::class;
    
    // Define which actions are publicly accessible (no authentication required)
    protected array $publicActions = ["index", "read"];
}
```

### 3. Set Up Routes

```php
<?php

use JPI\CRUD\API\Router;
use JPI\HTTP\Request;

$request = Request::createFromGlobals();
$router = new Router($request);

$router->group("/projects", function($router) {
    $router->get("/", [ProjectController::class, "index"]);
    $router->post("/", [ProjectController::class, "create"]);
    $router->get("/{id}/", [ProjectController::class, "read"]);
    $router->put("/{id}/", [ProjectController::class, "update"]);
    $router->delete("/{id}/", [ProjectController::class, "delete"]);
});

$response = $router->dispatch($request);
$response->send();
```

### 4. Customise CRUD Service (Optional)

```php
<?php

namespace App;

use JPI\CRUD\API\CrudService;

class ProjectService extends CrudService {
    
    protected bool $paginated = true;
    protected int $perPage = 20;
    
    protected static array $requiredColumns = ["name"];
}
```

Then reference it in your entity:

```php
class Project extends AbstractEntity {
    protected static string $crudService = ProjectService::class;
    // ...
}
```

## API Response Format

### Single Entity Response

```json
{
    "data": {
        "id": 1,
        "name": "Example Project",
        "description": "A sample project",
        "created_at": "2024-01-15 10:30:00 UTC"
    },
    "_links": {
        "self": "https://api.example.com/projects/1"
    }
}
```

### Collection Response (Paginated)

```json
{
    "data": [
        {
            "id": 1,
            "name": "Project One",
            "_links": {
                "self": "https://api.example.com/projects/1"
            }
        },
        {
            "id": 2,
            "name": "Project Two",
            "_links": {
                "self": "https://api.example.com/projects/2"
            }
        }
    ],
    "_total_count": 25,
    "_total_pages": 3,
    "_links": {
        "self": "https://api.example.com/projects?page=1",
        "next_page": "https://api.example.com/projects?page=2"
    }
}
```

### Error Response

```json
{
    "message": "The necessary data was not provided and/or invalid.",
    "errors": {
        "name": "`name` is required.",
        "email": "Must be a valid email address."
    }
}
```

## Advanced Features

### Search

Enable search on your entities by implementing `SearchableInterface` and using the `Searchable` trait:

```php
use JPI\CRUD\API\Entity\SearchableInterface;
use JPI\CRUD\API\Entity\Searchable;

class Project extends AbstractEntity implements SearchableInterface {
    use Searchable;
    
    protected static array $searchableColumns = ["name", "description"];
}
```

Then use the search query parameter:
```
GET /projects?search=web+development
```

### Filtering

Enable filtering by implementing `FilterableInterface` and using the `Filterable` trait:

```php
use JPI\CRUD\API\Entity\FilterableInterface;
use JPI\CRUD\API\Entity\Filterable;

class Project extends AbstractEntity implements FilterableInterface {
    use Filterable;
    
    protected static array $filterableColumns = ["status", "category"];
}
```

Then use the filters query parameter:
```
GET /projects?filters[status]=active&filters[category]=web
```

### Authentication

The framework checks for an `is_authenticated` request attribute. Integrate with your authentication system by setting this attribute:

```php
$request = $request->withAttribute("is_authenticated", $isUserLoggedIn);
```

Endpoints not listed in `$publicActions` will return a 401 response if the user is not authenticated.

## Examples

See a real-world implementation in the [Portfolio API](https://github.com/jahidulpabelislam/portfolio-api) project.

## Support

If you found this library useful, please consider:
- ⭐ Starring the repository on GitHub
- 📢 Sharing it on your social media
- 🐛 Reporting issues or suggesting features

For issues or feature requests, please [open an issue](https://github.com/jahidulpabelislam/crud-api/issues) or email [me@jahidulpabelislam.com](mailto:me@jahidulpabelislam.com).

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) — [me@jahidulpabelislam.com](mailto:me@jahidulpabelislam.com)

## Licence

This project is licenced under the GNU General Public Licence v3.0 — see the [LICENCE](LICENSE.md) file for details.
