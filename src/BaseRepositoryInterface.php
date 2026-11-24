<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;
use RobertGDev\BaseRepository\Exceptions\ModelNotSavedException;

/**
 * @template TModel of Model
 */
interface BaseRepositoryInterface
{
    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model;

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id): Model;

    /**
     * Invoke $callback (with no arguments, matching Eloquent) when the
     * row does not exist.
     *
     * @param  Closure(): mixed  $callback
     */
    public function findOr(int|string $id, Closure $callback): mixed;

    /**
     * @return TModel
     */
    /**
     * @param  array<int, string>|string  $columns
     * @return TModel
     */
    public function findOrNew(int|string $id, array|string $columns = ['*']): Model;

    /**
     * Find a row by primary key, including soft-deleted rows.
     *
     * @return TModel|null
     */
    public function findWithTrashed(int|string $id): ?Model;

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, TModel>
     */
    public function findMany(array $ids): Collection;

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, TModel>
     *
     * @throws ModelNotFoundException
     */
    public function findManyOrFail(array $ids): Collection;

    /**
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    /**
     * @param  array<int, string>|string  $columns
     * @return TModel|null
     */
    public function first(array|string $columns = ['*']): ?Model;

    /**
     * @param  array<int, string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    /**
     * @param  array<int, string>|string  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function firstOrFail(array|string $columns = ['*']): Model;

    /**
     * @param  array<int, string>  $columns
     */
    /**
     * @param  Closure(): mixed  $callback
     * @param  array<int, string>|string  $columns
     */
    public function firstOr(Closure $callback, array|string $columns = ['*']): mixed;

    /**
     * @param  array<string, mixed>|string  $column
     * @return TModel|null
     */
    public function firstWhere(array|string $column, mixed $operator = null, mixed $value = null): ?Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     *
     * @throws ModelNotSavedException
     */
    public function firstOrCreate(array $attributes, array $values = []): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function firstOrNew(array $attributes, array $values = []): Model;

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function get(array $columns = ['*']): Collection;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function count(Closure|array $where = [], string $columns = '*'): int;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function exists(Closure|array $where = []): bool;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function doesntExist(Closure|array $where = []): bool;

    public function existsById(int|string $id): bool;

    /**
     * @return SupportCollection<array-key, mixed>
     */
    public function pluck(string $column, ?string $key = null): SupportCollection;

    public function value(string $column): mixed;

    public function sum(string $column): int|float;

    public function avg(string $column): ?float;

    public function max(string $column): mixed;

    public function min(string $column): mixed;

    /**
     * @param  array<int, string>  $columns
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
    ): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $columns
     * @return Paginator<int, TModel>
     */
    public function simplePaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
    ): Paginator;

    /**
     * @param  array<int, string>  $columns
     * @return CursorPaginator<int, TModel>
     */
    public function cursorPaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null,
    ): CursorPaginator;

    /**
     * Offset based chunking — do not mutate the driving columns in the
     * callback, use chunkById() instead.
     *
     * @param  callable  $callback
     */
    public function chunk(int $count, callable $callback): bool;

    /**
     * @param  callable  $callback
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool;

    /**
     * @return LazyCollection<int, TModel>
     */
    public function lazy(int $chunkSize = 1000): LazyCollection;

    /**
     * @return LazyCollection<int, TModel>
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null, ?string $alias = null): LazyCollection;

    /**
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     *
     * @throws ModelNotSavedException
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function make(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel|null
     *
     * @throws ModelNotSavedException
     */
    public function update(int|string $id, array $attributes): ?Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     *
     * @throws ModelNotSavedException
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     * @param  array<string, mixed>  $values
     * @return int number of affected rows
     */
    public function updateWhere(Closure|array $where, array $values): int;

    /**
     * @param  array<int, array<string, mixed>>  $values
     */
    public function insert(array $values): bool;

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @return int number of inserted rows
     */
    public function insertOrIgnore(array $values): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertGetId(array $attributes, ?string $sequence = null): int;

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>|null  $update
     * @return int number of inserted/updated rows
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int;

    /**
     * @param  Closure|array<string, mixed>  $attributes  Array values are treated as whereIn().
     * @param  array<string, mixed>  $values
     */
    public function updateOrInsert(Closure|array $attributes, array $values = []): bool;

    /**
     * @return bool|null null when the row does not exist; false when a
     *                   deleting listener vetoed the delete; true when
     *                   it was deleted.
     */
    public function delete(int|string $id): ?bool;

    /**
     * @return bool|null null when the row does not exist; false when a
     *                   forceDeleting listener vetoed the delete; true
     *                   when it was hard-deleted.
     */
    public function forceDelete(int|string $id): ?bool;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     * @return int number of affected rows
     */
    public function deleteWhere(Closure|array $where): int;

    /**
     * @param  array<int, int|string>  $ids
     * @return int number of affected rows
     */
    public function deleteMany(array $ids): int;

    /**
     * @return bool|null null when the row does not exist or the model is
     *                   not soft-deletable; false when a restoring
     *                   listener vetoed the restore; true when restored.
     */
    public function restore(int|string $id): ?bool;

    /**
     * @param  array<int, int|string>  $ids
     * @return int number of restored rows
     */
    public function restoreMany(array $ids): int;

    public function trashed(int|string $id): bool;

    /**
     * @return Builder<TModel>
     */
    public function withTrashed(): Builder;

    /**
     * @return Builder<TModel>
     */
    public function onlyTrashed(): Builder;

    /**
     * @return TModel
     */
    public function getModel(): Model;

    /**
     * @param  TModel  $model
     */
    public function setModel(Model $model): static;

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder;

    /**
     * @return class-string<TModel>
     */
    public function model(): string;
}
