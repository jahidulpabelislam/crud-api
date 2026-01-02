# CRUD API Framework

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api)
[![Latest Stable Version](https://poser.pugx.org/jpi/crud-api/v/stable)](https://packagist.org/packages/jpi/crud-api)
[![Total Downloads](https://poser.pugx.org/jpi/crud-api/downloads)](https://packagist.org/packages/jpi/crud-api)
[![Latest Unstable Version](https://poser.pugx.org/jpi/crud-api/v/unstable)](https://packagist.org/packages/jpi/crud-api)
[![Licence](https://poser.pugx.org/jpi/crud-api/license)](https://packagist.org/packages/jpi/crud-api)
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
- [jpi/http](https://packagist.org/packages/jpi/http) v1
- [jpi/orm](https://packagist.org/packages/jpi/orm) v2
- [jpi/utils](https://packagist.org/packages/jpi/utils) v1

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require jpi/crud-api 
```

## Basic Usage

### 1. Create an Entity

**AbstractEntity** is the base entity class that extends the ORM Entity and provides API-specific functionality.

**Key Methods:**
- `getAPIURL()`: Returns the API URL for the entity (must be implemented by child classes)
- `getAPIResponse()`: Generates the JSON-serialisable response for the entity
- `getAPILinks()`: Returns HATEOAS links for the entity
- `getCrudService()`: Returns the associated CRUD service instance
- `getDisplayName()`: Returns the human-readable display name (singular)
- `getPluralDisplayName()`: Returns the human-readable display name (plural)

**Entity Traits:**
- **Searchable**: Adds search functionality - define searchable columns via `$searchableColumns` property with multi-word search support
- **Filterable**: Adds filtering functionality - define filterable columns via `$filterableColumns` property with equality-based filtering

```php
<?php

class Project extends \JPI\CRUD\API\AbstractEntity implements \JPI\CRUD\API\Entity\SearchableInterface {
    use \JPI\CRUD\API\Entity\Searchable;

    protected static string $table = "projects";
    
    protected static array $searchableColumns = ["name", "description"];
    
    protected static array $dataMapping = [
        "name" => ["type" => "string"],
        "description" => ["type" => "string"],
        "created_at" => ["type" => "date_time"],
    ];

    public function getAPIURL(): \JPI\Utils\URL {
        return new \JPI\Utils\URL("https://api.example.com/projects/{$this->getId()}");
    }
}
```

### 2. Create a Controller

**AbstractController** is the base controller class providing standard CRUD endpoints with authentication support.

**Standard Actions:**
- `index()`: GET - List all entities (with pagination, search, and filters)
- `create()`: POST - Create a new entity
- `read($id)`: GET - Retrieve a specific entity
- `update($id)`: PUT - Update a specific entity
- `delete($id)`: DELETE - Delete a specific entity

The **Responder** trait provides standardised response methods for controllers including item responses with proper status codes, collection responses with pagination metadata, and error responses.

```php
<?php

class ProjectController extends \JPI\CRUD\API\AbstractController {
    
    protected string $entityClass = Project::class;
    
    // Define which actions are publicly accessible (no authentication required)
    protected array $publicActions = ["index", "read"];
}
```

### 3. Set Up Routes

**Router** is an extended HTTP router with built-in 404 and 405 error handlers.

```php
<?php

$request = \JPI\HTTP\Request::createFromGlobals();
$router = new \JPI\CRUD\API\Router($request);

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

**CrudService** is the service layer handling CRUD operations and data validation.

**Key Features:**
- Automatic request data validation
- Support for required fields
- Search and filter integration
- Pagination support

```php
<?php

class ProjectService extends \JPI\CRUD\API\CrudService {
    
    protected bool $paginated = true;
    protected int $perPage = 20;
    
    protected static array $requiredColumns = ["name"];
}
```

Then reference it in your entity's `crudService` property.

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

Enable search on your entities by implementing `SearchableInterface` and using the `Searchable` trait, then adding `searchableColumns` property to your Entity.

Then use the search query parameter:

```
GET /projects?search=web+development
```

### Filtering

Enable filtering by implementing `FilterableInterface` and using the `Filterable` trait, then adding `filterableColumns` property to your Entity.

Then use the filters query parameter:

```
GET /projects?filters[status]=active&filters[category]=web
```

### Authentication

The framework checks for an `is_authenticated` request attribute. Integrate with your authentication system by setting this attribute.

Endpoints not listed in `$publicActions` will return a 401 response if the user is not authenticated.

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/crud-api/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licensed under the General Public Licence - see the [licence](LICENSE.md) file for details.
