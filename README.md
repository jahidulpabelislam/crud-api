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
- **Sorting**: Sort results by multiple columns in ascending or descending order
- **Field Selection**: Choose which fields to return in API responses to reduce payload size
- **Relationship Inclusion**: Eager load related entities to avoid N+1 query problems
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

- `getDisplayName(): string`: Used in error messages - uses `$displayName` property if set otherwise returns the class name
- `getPluralDisplayName(): string`: Used in error messages - simply appends an `s` to the display name (can be overridden for irregular plurals)
- `getAPIURL()`: Returns the API URL for the entity - basic version implemented assuming the format is `/{pluralName}/{id}` - you can override if needed.

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
final class ProjectsController extends \JPI\CRUD\API\AbstractController {

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

- paginated with 10 items per page - you can change using `$perPage` property or disable pagination setting to null
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
//   GET    /projects/      -> ProjectsController::index
//   POST   /projects/      -> ProjectsController::create
//   GET    /projects/{id}/ -> ProjectsController::read
//   PUT    /projects/{id}/ -> ProjectsController::update
//   DELETE /projects/{id}/ -> ProjectsController::delete
// Optional third parameter: route name for the read action
$app->addCRUDRoutes("/projects/", ProjectsController::class);
// Or with a named route: $app->addCRUDRoutes("/projects/", ProjectsController::class, "project");

// Option 2: Define routes manually
$app->addRoute("/projects/", "GET", ProjectsController::class . "::index", "project");
$app->addRoute("/projects/", "POST", ProjectsController::class . "::create");
$app->addRoute("/projects/{id}/", "GET", ProjectsController::class . "::read");
$app->addRoute("/projects/{id}/", "PUT", ProjectsController::class . "::update");
$app->addRoute("/projects/{id}/", "DELETE", ProjectsController::class . "::delete");
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
        "created_at": "`created_at` must be instance of \\DateTime or valid format for creation or null."
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

### Sorting

Adds sorting functionality on the list/index endpoint.

Enable sorting by implementing `\JPI\CRUD\API\Entity\SortableInterface` and using the `\JPI\CRUD\API\Entity\Sortable` trait. By default all columns are sortable but likely you'd want to define this using the `sortableColumns` property on your Entity.

Then sorting can be applied using the `sort` query parameter. Use `{column}:{direction}` syntax where direction can be `asc` or `desc`. If no direction is specified, it defaults to ascending order. Multiple sort criteria can be specified by separating them with commas:

```
GET /projects/?sort=created_at                      # Sort by created_at ascending (default)
GET /projects/?sort=created_at:asc                  # Sort by created_at ascending (explicit)
GET /projects/?sort=created_at:desc                 # Sort by created_at descending
GET /projects/?sort=status:asc,created_at:desc      # Sort by status ASC, then created_at DESC
```

### Field Selection

You can choose which fields to include in the API response for list/index and read/single endpoints using the `fields` query parameter. This helps reduce payload size and improve performance by only returning the data you need.

The `id` field is always included in the response.

Use comma-separated field names:

```
GET /projects/?fields=name,created_at
```

```json
{
    "data": {
        "id": 1,
        "name": "Example Project",
        "created_at": "2024-01-15 10:30:00 UTC"
    },
    "_links": {
        "self": "https://api.example.com/projects/1/"
    }
}
```

### Including Relationships

You can include related entities in API responses using the `include` query parameter. This uses eager loading to avoid N+1 query problems and efficiently load all relationships in a single or minimal number of queries.

Use comma-separated relationship names that match the property names defined in your entity's data mapping:

```
GET /projects/1/?include=author,category
GET /projects/?include=author,comments
```

**Example:** If your `Project` entity has an `author` relationship (defined as a `belongs_to` in the ORM), you can include it:

```json
{
    "data": {
        "id": 1,
        "name": "Example Project",
        "description": "A sample project",
        "author": {
            "id": 5,
            "name": "John Doe",
            "_links": {
                "self": "https://api.example.com/users/5/"
            }
        },
        "created_at": "2024-01-15 10:30:00 UTC"
    },
    "_links": {
        "self": "https://api.example.com/projects/1/"
    }
}
```

**Benefits:**
- Reduces the number of SQL queries (avoids N+1 problem)
- Improves API performance when related data is needed
- Works with both single entity and collection endpoints
- Can be combined with other query parameters (fields, filters, sorting, etc.)

## Support

If you found this library interesting or useful please spread the word about this library: share on your socials, star on GitHub, etc.

If you find any issues or have any feature requests, you can open a [issue](https://github.com/jahidulpabelislam/crud-api/issues) or email [me @ jahidulpabelislam.com](mailto:me@jahidulpabelislam.com) :smirk:.

## Authors

- [Jahidul Pabel Islam](https://jahidulpabelislam.com/) [<me@jahidulpabelislam.com>](mailto:me@jahidulpabelislam.com)

## Licence

This module is licensed under the General Public Licence - see the [licence](LICENSE.md) file for details.
