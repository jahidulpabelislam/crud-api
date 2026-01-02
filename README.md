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
- [jpi/http](https://packagist.org/packages/jpi/http) v1
- [jpi/orm](https://packagist.org/packages/jpi/orm) v2
- [jpi/utils](https://packagist.org/packages/jpi/utils) v1

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require jpi/crud
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

```php
<?php

class ProjectController extends \JPI\CRUD\API\AbstractController {
    
    protected string $entityClass = Project::class;
    
    // Define which actions are publicly accessible (no authentication required)
    protected array $publicActions = ["index", "read"];
}
```

### 3. Set Up Routes

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
