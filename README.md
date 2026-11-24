# Base Repository for Laravel

A lightweight base repository for Laravel Eloquent models with full CRUD, bulk
write, aggregate and soft-delete support. No Criteria pattern, no magic — just a
clean wrapper around Eloquent that makes your data access layer consistent,
testable, and free of boilerplate.

## Installation

```bash
composer require robertgdev/laravel-base-repository
```

## Requirements

- PHP 8.3+
- Laravel 13.x

## Quick Start

### 1. Create a repository

Declare the model once, in `model()`:

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
    public function model(): string
    {
        return User::class;
    }
}
```

That is the whole class — no constructor boilerplate. If you prefer explicit
injection, a constructor is still supported and wins over `model()`:

```php
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

Laravel's container resolves the repository either way. Just type-hint it:

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

## Two families of writes

The write API is split deliberately. Pick the family that matches what you need:

| Family | Methods | Events / observers | Casts & mutators | Refused write |
|---|---|---|---|---|
| Model writes | `create`, `update`, `firstOrCreate`, `updateOrCreate` | fire | applied | throws `ModelNotSavedException` |
| Bulk writes | `insert`, `insertOrIgnore`, `insertGetId`, `upsert`, `updateWhere`, `updateOrInsert`, `deleteWhere`, `deleteMany` | **not fired** | **not applied** | reports affected rows |

A model write never reports success for a write that did not happen: if a
`saving`/`creating`/`updating` listener returns `false`, you get a
`RobertGDev\BaseRepository\Exceptions\ModelNotSavedException`, not a model that
only *looks* updated.

Bulk writes are single statements. Pass raw database values (encode JSON
columns yourself), and remember that observers, model events and global cache
invalidation keyed off events will not run.

## Available Methods

### Retrieval

| Method | Description |
|--------|-------------|
| `find($id)` | Find by primary key, returns model or null |
| `findOrFail($id)` | Find by primary key, throws `ModelNotFoundException` |
| `findOr($id, $callback)` | Find by id, or invoke a no-argument callback |
| `findOrNew($id, $columns)` | Find by id or return a new unsaved instance |
| `findWithTrashed($id)` | Find by id including soft-deleted rows |
| `findMany($ids)` | Find multiple models by id array |
| `findManyOrFail($ids)` | Same, but throws when any id is missing |
| `first($columns)` | First record or null |
| `firstOrFail($columns)` | First record or throws |
| `firstOr($callback, $columns)` | First record, or invoke the callback (callback first, so no columns argument is needed) |
| `firstWhere($column, $operator, $value)` | First match of a single condition, or an array of conditions |
| `get($columns)` | All matching records as an Eloquent Collection |
| `query()` | Fresh Eloquent Builder for custom queries |
| `count($where, $columns)` | Count records, optionally filtered or over one column |
| `exists($where)` | Whether any matching record exists |
| `doesntExist($where)` | Whether no record matches |
| `existsById($id)` | Existence check without hydrating a model |

### Pagination and iteration

| Method | Description |
|--------|-------------|
| `paginate($perPage, ...)` | Length-aware paginator |
| `simplePaginate($perPage, ...)` | Simple (next/previous) paginator |
| `cursorPaginate($perPage, ...)` | Cursor paginator |
| `chunk($count, $callback)` | Offset based chunks — do not mutate the driving columns |
| `chunkById($count, $callback, ...)` | Keyset chunks — safe while the callback writes |
| `lazy($chunkSize)` | Lazy collection over offset pages |
| `lazyById($chunkSize, ...)` | Lazy collection by key — safe while writing |
| `cursor()` | Lazy collection streamed from a single cursor |

An invalid `$perPage` (null, `0`, negative) falls back to the model's own
`$perPage` instead of producing broken SQL.

### Aggregates

| Method | Description |
|--------|-------------|
| `pluck($column, $key)` | Values (or keyed values) as a support Collection |
| `value($column)` | First value of a column |
| `sum($column)` | Sum, `0` when empty |
| `avg($column)` | Average, `null` when empty |
| `max($column)` / `min($column)` | Maximum / minimum value |

### Model writes

| Method | Description |
|--------|-------------|
| `create($attributes)` | Create and persist a model |
| `make($attributes)` | New unsaved instance |
| `update($id, $attributes)` | Update by id; `null` when missing, throws when refused |
| `updateOrCreate($attributes, $values)` | Update existing or create |
| `firstOrCreate($attributes, $values)` | Find first match or create |
| `firstOrNew($attributes, $values)` | Find first match or new instance |

### Bulk writes

| Method | Description |
|--------|-------------|
| `insert($values)` | Insert one or many rows in one statement |
| `insertOrIgnore($values)` | Insert, ignoring unique conflicts; returns inserted count |
| `insertGetId($attributes, $sequence)` | Insert one row, return its generated id |
| `upsert($values, $uniqueBy, $update)` | Insert rows, update the conflicting ones |
| `updateWhere($where, $values)` | Bulk update matching rows; returns affected count |
| `updateOrInsert($attributesOrClosure, $values)` | Insert unless it exists, else update |
| `deleteWhere($where)` | Bulk delete matching rows; returns affected count |
| `deleteMany($ids)` | Bulk delete by primary keys |

#### `upsert()`

```php
$repo->upsert(
    [
        ['email' => 'a@example.com', 'name' => 'A', 'status' => 'active'],
        ['email' => 'b@example.com', 'name' => 'B', 'status' => 'active'],
    ],
    uniqueBy: ['email'],
    update: ['name', 'status'],   // null = update every column given
);
```

- Timestamps are filled automatically.
- Model events, observers, casts and mutators are **not** applied.
- `$uniqueBy` must be backed by a real unique index. On engines whose conflict
  target is advisory (MySQL), a missing index inserts duplicates instead of
  updating them.
- Pass an `id` in the values to update a specific row rather than match on a
  natural key.

### Deletes

| Method | Description |
|--------|-------------|
| `delete($id)` | Delete by id; `null` when missing, `false` when a `deleting` listener vetoes, `true` on success |
| `forceDelete($id)` | Hard delete; `null` when missing, `false` when a `forceDeleting` listener vetoes, `true` on success |
| `deleteWhere($where)` | Bulk delete; soft-deletable models are still soft-deleted |
| `deleteMany($ids)` | Bulk delete by ids |

An empty `$where` on `deleteWhere()` deletes every row (global scopes still
apply) — guard the caller.

### Soft deletes

| Method | Description |
|--------|-------------|
| `restore($id)` | Restore one row, firing `restoring`/`restored`; `null` when missing or not soft-deletable, `false` when `restoring` vetoes, `true` on success |
| `restoreMany($ids)` | Restore many rows in one bulk update (no events) |
| `trashed($id)` | Whether the row exists and is soft-deleted |
| `findWithTrashed($id)` | Find including soft-deleted rows |
| `withTrashed()` | Builder including soft-deleted rows |
| `onlyTrashed()` | Builder matching soft-deleted rows only |

`withTrashed()`/`onlyTrashed()` work with any Eloquent builder method, so scoping
reads is a one-liner:

```php
$repo->onlyTrashed()->where('status', 'draft')->paginate(20);
```

Models that do not use `SoftDeletes` are handled explicitly rather than fatally:
`restore()` returns `false`, `restoreMany()` returns `0`, `withTrashed()` is the
plain query and `onlyTrashed()` matches nothing. A custom `DELETED_AT` constant
is honoured.

### Utilities

| Method | Description |
|--------|-------------|
| `getModel()` | The model instance the repository works with |
| `setModel($model)` | Swap it (e.g. read replica, differently configured subclass) |
| `increment($id, $column, $amount, $extra)` | Atomic increment; no events, keeps `updated_at` |
| `decrement($id, $column, $amount, $extra)` | Atomic decrement |
| `transaction($callback)` | Run on the model's connection, returns the callback result |
| `truncate()` | Empty the table; bypasses events and scopes |

## Filter arrays and closures

Every `$where` parameter accepts an array **or** a closure:

```php
$repo->count(['status' => 'active']);                        // where status = 'active'
$repo->count(['status' => ['active', 'archived']]);          // whereIn — not "first value only"
$repo->count(['deleted_at' => null]);                        // whereNull
$repo->count(fn (Builder $q) => $q->where('views', '>', 10)); // anything else
```

Array values are translated to `whereIn()`, so a list can never silently degrade
into a match on its first element. Use the closure form for OR groups, subqueries
and raw conditions.

## Adding custom queries

`query()` returns a fresh builder, so repository-specific queries stay readable
and fully typed:

```php
/**
 * @extends BaseRepository<Order>
 */
class OrderRepository extends BaseRepository
{
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
}
```

## A note on `all()`

There is no `all()`: it was a duplicate of `get()`. Use `get()` for "every
record".

## Static analysis

The repository is generic, so `@extends BaseRepository<User>` gives you full
type inference — `findOrFail()` returns `User`, `get()` returns
`Collection<int, User>`, and so on. The package itself is analysed with
[Larastan](https://github.com/larastan/larastan) at PHPStan level 9.

## Testing

Mock the repository when you only need a collaborator:

```php
$mock = Mockery::mock(UserRepository::class);
$mock->shouldReceive('findOrFail')->with(1)->andReturn(new User(['id' => 1]));

app()->instance(UserRepository::class, $mock);
```

Or use the repository itself in integration tests — it is a thin wrapper, so a
real database is usually the more valuable test.

## Package development

```bash
composer install
composer test      # Pest (SQLite in-memory, 151 tests)
composer analyse   # PHPStan level 9 + Larastan
composer check     # both
```

## License

MIT. See [LICENSE](LICENSE).
