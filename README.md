# Base Repository for Laravel

A lightweight base repository for Laravel Eloquent models with full CRUD and query
support. No Criteria pattern, no magic — just a clean wrapper around Eloquent that
makes your data access layer consistent, testable, and free of boilerplate.

## Installation

```bash
composer require robertgdev/laravel-base-repository
```

## Quick Start

### 1. Create a repository

```php
<?php

namespace App\Repositories;

use App\Models\User;
use RobertGDev\BaseRepository\BaseRepository;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return User::class;
    }
}
```

### 2. Inject and use

Laravel's container auto-resolves your repository. Just type-hint it:

```php
class UserController
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function show(string $id)
    {
        return $this->users->findOrFail($id);
    }
}
```

## Available Methods

All methods mirror Eloquent conventions and work identically to their Eloquent
counterparts:

### Retrieval

| Method | Description |
|--------|-------------|
| `find($id)` | Find by primary key, returns model or null |
| `findOrFail($id)` | Find by primary key, throws `ModelNotFoundException` |
| `findOr($id, $callback)` | Find by id or invoke callback |
| `findOrNew($id)` | Find by id or return new unsaved instance |
| `findMany($ids)` | Find multiple models by id array |
| `first($columns)` | First record or null |
| `firstOrFail($columns)` | First record or throws |
| `firstOr($columns, $callback)` | First record or invoke callback |
| `firstOrCreate($attributes, $values)` | Find first match or create |
| `firstOrNew($attributes, $values)` | Find first match or new instance |
| `all($columns)` | All records as a Collection |
| `get($columns)` | Fresh query result as a Collection |
| `paginate($perPage, ...)` | Paginated results |
| `chunk($count, $callback)` | Process records in chunks |
| `lazy($chunkSize)` | Lazy collection for memory-efficient iteration |
| `count($where)` | Count records, optionally filtered |
| `exists($where)` | Check if matching records exist |
| `doesntExist($where)` | Check if no matching records exist |
| `query()` | Raw Eloquent Builder for custom queries |

### Mutation

| Method | Description |
|--------|-------------|
| `create($attributes)` | Create and persist a model |
| `make($attributes)` | New unsaved instance |
| `update($id, $attributes)` | Update by id, returns updated model or null |
| `updateOrCreate($attributes, $values)` | Update existing or create |
| `delete($id)` | Delete by id (respects SoftDeletes) |
| `forceDelete($id)` | Hard delete regardless of SoftDeletes |
| `restore($id)` | Restore a soft-deleted record |

## Adding Custom Queries

Use the `query()` escape hatch to compose any Eloquent query directly:

```php
class OrderRepository extends BaseRepository
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return Order::class;
    }

    public function findPendingForUser(int $userId): Collection
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function totalRevenueForMonth(Carbon $date): string
    {
        return $this->query()
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->sum('total');
    }
}
```

## Testing

Mock the repository in your tests — no database needed:

```php
$mock = Mockery::mock(UserRepository::class);
$mock->shouldReceive('findOrFail')->with(1)->andReturn(new User(['id' => 1]));

app()->instance(UserRepository::class, $mock);
```

Or use the repository itself in integration tests. The package ships with
its own test suite (Pest, SQLite in-memory) that exercises every method.

## Package Tests

```bash
composer install
vendor/bin/pest
```

## Requirements

- PHP 8.3+
- Laravel 11.x / 12.x / 13.x

## License

MIT. See [LICENSE](LICENSE).
