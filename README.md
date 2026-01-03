# CRUD API Framework

[![CodeFactor](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api/badge)](https://www.codefactor.io/repository/github/jahidulpabelislam/crud-api)
[![Latest Stable Version](https://poser.pugx.org/jpi/crud-api/v/stable)](https://packagist.org/packages/jpi/crud-api)
[![Total Downloads](https://poser.pugx.org/jpi/crud-api/downloads)](https://packagist.org/packages/jpi/crud-api)
[![Latest Unstable Version](https://poser.pugx.org/jpi/crud-api/v/unstable)](https://packagist.org/packages/jpi/crud-api)
[![Licence](https://poser.pugx.org/jpi/crud-api/license)](https://packagist.org/packages/jpi/crud-api)
![GitHub last commit (branch)](https://img.shields.io/github/last-commit/jahidulpabelislam/crud-api/1.x.svg?label=last%20activity)

A lightweight PHP framework for building RESTful CRUD APIs with built-in support for pagination, search, filtering, and authentication.

## Features

- **RESTful CRUD Operations**: Out-of-the-box support for List, Create, Read, Update and Delete operations
- **Pagination**: Built-in pagination support with configurable page size
- **Search & Filtering**: Flexible search and filter functionality for listing endpoints
- **Authentication**: Built-in authentication checks for protected endpoints
- **JSON Responses**: Standardised JSON response format with HATEOAS links
- **Validation**: Comprehensive data validation with detailed error messages
- **Extensible**: Easy to extend for custom business logic

## Dependencies

- PHP 8.0+
- Composer
- [jpi/utils](https://packagist.org/packages/jpi/utils) v1
- [jpi/orm](https://packagist.org/packages/jpi/orm) v2
- [jpi/http](https://github.com/jahidulpabelislam/http) v1

## Installation

Use [Composer](https://getcomposer.org/)

```bash
$ composer require jpi/crud-api 
```

## Basic Usage

### 1. Create an Entity

**AbstractEntity** is the base entity class that you'll need to extend, it builds on top of **jpi/orm**. See [jpi/orm](https://packagist.org/packages/jpi/orm) for more details on setting up entities.

**Extra Set Up:**

- `getAPIURL()`: Returns the API URL for the entity - MUST be implemented
- `getDisplayName(): string`: Used in error messages - uses `$displayName` property if set otherwise returns the class name
- `getPluralDisplayName(): string`: Used in error messages - simply appends an `s` to the display name (can be overridden for irregular plurals)

### 2. Create a Controller

**AbstractController** is the base controller class providing standard CRUD endpoints with authentication support. This handles the responses and delegates the business logic to the **CrudService** (see next), therefore should be rare to add much here.

**Standard Actions:**

- `index()`: GET - List all entities (with pagination, search, and filters)
- `create()`: POST - Create a new entity
- `read($id)`: GET - Retrieve a specific entity
- `update($id)`: PUT - Update a specific entity
- `delete($id)`: DELETE - Delete a specific entity

All you need to do is extend the abstract controller, specify the entity class & define any public actions (if any):

```php
final class ProjectController extends \JPI\CRUD\API\AbstractController {

    protected string $entityClass = Project::class;

    // Define which actions are publicly accessible (no authentication required)
    protected array $publicActions = ["index", "read"];
}
```

### 3. Customise CRUD Service (Optional)

**CrudService** is the service layer handling CRUD operations and data validation.

**Key Features:**

- Automatic request data validation
- Support for required fields
- Search and filter integration
- Pagination support

By default:

- paginated with 10 items per page - you can disable (`$paginated`) or change number of items per page (`$perPage`):
- no columns are required - you can define using the static `$requiredColumns` property

```php
final class ProjectService extends \JPI\CRUD\API\CrudService {

    protected bool $paginated = true;
    protected int $perPage = 20;

    protected static array $requiredColumns = ["name"];
}
```

Then reference it in your entity's `crudService` property.

### 4. Request Handling

We use [jpi/http](https://github.com/jahidulpabelislam/http) for the request handling, though we extend **App** & **Router** to provide built-in 404 and 405 error handlers, and easy CRUD route setup.

Set up the app as per `jpi/http`:

```php
$request = \JPI\HTTP\Request::fromGlobals();
$router = new \JPI\CRUD\API\Router($request);
$app = new \JPI\CRUD\API\App($router);
```

You can set up CRUD routes manually or use the convenient `addCRUDRoutes()` method:

```php
// Option 1: Use addCRUDRoutes() helper (recommended)
// This creates 5 routes:
//   GET    /projects/     -> ProjectController::index
//   POST   /projects/     -> ProjectController::create
//   GET    /projects/{id}/ -> ProjectController::read
//   PUT    /projects/{id}/ -> ProjectController::update
//   DELETE /projects/{id}/ -> ProjectController::delete
// Optional third parameter: route name for the read action
$app->addCRUDRoutes("/projects/", ProjectController::class);
// Or with a named route: $app->addCRUDRoutes("/projects/", ProjectController::class, "project");

// Option 2: Define routes manually
$app->addRoute("/projects/", "GET", ProjectController::class . "::index", "project");
$app->addRoute("/projects/", "POST", ProjectController::class . "::create");
$app->addRoute("/projects/{id}/", "GET", ProjectController::class . "::read");
$app->addRoute("/projects/{id}/", "PUT", ProjectController::class . "::update");
$app->addRoute("/projects/{id}/", "DELETE", ProjectController::class . "::delete");
```

Handle the request and send the response:

```php
$response = $app->handle();
$response->send();
```

See [jpi/http](https://github.com/jahidulpabelislam/http) for more details on routing, request and response handling.

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
        "self": "https://api.example.com/projects/1/"
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
            "description": "A sample project",
            "created_at": "2024-01-15 10:30:00 UTC",
            "_links": {
                "self": "https://api.example.com/projects/1/"
            }
        },
        {
            "id": 2,
            "name": "Project Two",
            "description": "A sample project",
            "created_at": "2025-01-15 10:30:00 UTC",
            "_links": {
                "self": "https://api.example.com/projects/2/"
            }
        }
    ],
    "_total_count": 25,
    "_total_pages": 3,
    "_links": {
        "self": "https://api.example.com/projects/",
        "next_page": "https://api.example.com/projects/?page=2"
    }
}
```

**getAPIResponse** on the Entity generates the API response for the entity.
**getAPILinks()** on the Entity returns HATEOAS links for the entity for the API response. Returns a `self` link out the box.


### Error Response

```json
{
    "message": "The necessary data was not provided and/or invalid.",
    "errors": {
        "name": "`name` is required.",
        "created_at": " must be instance of \DateTime or valid format for creation or null."
    }
}
```

## Advanced Features

### Authentication

The framework checks for an `is_authenticated` request attribute. Integrate with your authentication system by setting this attribute - would recommend a custom middleware.

Endpoints not listed in `$publicActions` will return a 401 response if the attribute is not set.

### Search

Adds search functionality on the list/index endpoint.

Enable search on your entities by implementing `\JPI\CRUD\API\Entity\SearchableInterface` and using the `\JPI\CRUD\API\Entity\Searchable` trait. Be default all columns are searchable but likely you'd want to define this using the `searchableColumns` property on your Entity.

Then a search query parameter can be used:

```
GET /projects/?search=web+development
```

### Filtering

Adds equality-based filtering on the list/index endpoint.

Enable filtering by implementing `\JPI\CRUD\API\Entity\FilterableInterface` and using the `\JPI\CRUD\API\Entity\Filterable` trait. By default all columns are filterable but likely you'd want to define this using the `filterableColumns` property on your Entity.

Then filters can be added as query parameter(s):

```
GET /projects/?filters[status]=active&filters[category]=web
```

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/crud-api/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licensed under the General Public Licence - see the [licence](LICENSE.md) file for details.
