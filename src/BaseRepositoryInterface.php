<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function findOr(int|string $id, Closure $callback): mixed;

    /**
     * @return TModel
     */
    public function findOrNew(int|string $id): Model;

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, TModel>
     */
    public function findMany(array $ids): Collection;

    /**
     * @param  array<int, string>  $columns
     * @return TModel|null
     */
    public function first(array $columns = ['*']): ?Model;

    /**
     * @param  array<int, string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function firstOrFail(array $columns = ['*']): Model;

    /**
     * @param  array<int, string>  $columns
     */
    public function firstOr(array $columns, Closure $callback): mixed;

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
    public function all(array $columns = ['*']): Collection;

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    public function get(array $columns = ['*']): Collection;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function count(Closure|array $where = []): int;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function exists(Closure|array $where): bool;

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function doesntExist(Closure|array $where): bool;

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
     * @return bool|null null when the row does not exist or the model is
     *                   not soft-deletable; false when a restoring
     *                   listener vetoed the restore; true when restored.
     */
    public function restore(int|string $id): ?bool;

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
