<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use RobertGDev\BaseRepository\Exceptions\ModelNotSavedException;

/**
 * A lightweight base repository that wraps Eloquent model operations
 * without Criteria overhead. Extend this class and provide your model
 * in the constructor to get full CRUD and query support out of the box.
 *
 * ```php
 * class UserRepository extends BaseRepository
 * {
 *     public function __construct(User $model)
 *     {
 *         parent::__construct($model);
 *     }
 *
 *     public function model(): string
 *     {
 *         return User::class;
 *     }
 *
 *     public function findActiveByEmail(string $email): ?User
 *     {
 *         return $this->query()->where('active', true)->where('email', $email)->first();
 *     }
 * }
 * ```
 *
 * @template TModel of Model
 *
 * @implements BaseRepositoryInterface<TModel>
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @param  TModel  $model
     */
    public function __construct(
        protected readonly Model $model,
    ) {}

    abstract public function model(): string;

    public function find(int|string $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        /** @var TModel|null $result */
        $result = $this->newQuery()->find($id);

        if ($result === null) {
            throw (new ModelNotFoundException)->setModel($this->model::class, $id);
        }

        return $result;
    }

    public function findOr(int|string $id, Closure $callback): mixed
    {
        return $this->newQuery()->find($id) ?? $callback($id);
    }

    public function findOrNew(int|string $id): Model
    {
        return $this->newQuery()->find($id) ?? $this->newModelInstance([]);
    }

    public function findMany(array $ids): Collection
    {
        return $this->newQuery()->findMany($ids);
    }

    public function first(array $columns = ['*']): ?Model
    {
        return $this->newQuery()->first($columns);
    }

    public function firstOrFail(array $columns = ['*']): Model
    {
        return $this->newQuery()->firstOrFail($columns);
    }

    public function firstOr(array $columns, Closure $callback): mixed
    {
        /** @var TModel|null $result */
        $result = $this->newQuery()->first($columns);

        return $result ?? $callback();
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $model = $this->newQuery()->firstOrCreate($attributes, $values);

        if (! $model->exists) {
            throw ModelNotSavedException::for($model, 'create');
        }

        return $model;
    }

    public function firstOrNew(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->firstOrNew($attributes, $values);
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    public function get(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function count(Closure|array $where = []): int
    {
        return $this->applyWhere($this->newQuery(), $where)->count();
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function exists(Closure|array $where): bool
    {
        return $this->applyWhere($this->newQuery(), $where)->exists();
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function doesntExist(Closure|array $where): bool
    {
        return ! $this->exists($where);
    }

    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
    ): LengthAwarePaginator {
        $perPage ??= $this->model->getPerPage();

        return $this->newQuery()->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Offset based chunking. Never mutate the columns that drive the
     * result set (or its ordering) inside the callback: the next page is
     * read by offset, so rows would be skipped. Use chunkById() or
     * lazyById() when the callback writes.
     *
     * @param  callable  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        return $this->newQuery()->chunk($count, $callback);
    }

    /**
     * Keyset based chunking — safe when the callback mutates the rows.
     *
     * @param  callable  $callback
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        return $this->newQuery()->chunkById($count, $callback, $column, $alias);
    }

    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        return $this->newQuery()->lazy($chunkSize);
    }

    /**
     * @return LazyCollection<int, TModel>
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null, ?string $alias = null): LazyCollection
    {
        return $this->newQuery()->lazyById($chunkSize, $column, $alias);
    }

    public function create(array $attributes): Model
    {
        return $this->saveOrFail($this->newModelInstance($attributes), 'create');
    }

    public function make(array $attributes): Model
    {
        return $this->newModelInstance($attributes);
    }

    public function update(int|string $id, array $attributes): ?Model
    {
        $model = $this->newQuery()->find($id);

        if ($model === null) {
            return null;
        }

        if ($model->update($attributes) === false) {
            throw ModelNotSavedException::for($model, 'update');
        }

        return $model;
    }

    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $model = $this->newQuery()->firstOrNew($attributes);

        return $this->saveOrFail($model->fill($values), 'save');
    }

    public function delete(int|string $id): bool
    {
        $model = $this->newQuery()->find($id);

        return $model !== null && $model->delete() !== false;
    }

    public function forceDelete(int|string $id): bool
    {
        $model = $this->newQuery()->find($id);

        return $model !== null && $model->forceDelete() !== false;
    }

    public function restore(int|string $id): bool
    {
        // withTrashed() is a builder macro registered by SoftDeletingScope,
        // not a model method: method_exists() can never see it, so detect
        // soft-deletability via the trait instead.
        if (! in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            return false;
        }

        $model = $this->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($id)
            ->first();

        if ($model === null || ! method_exists($model, 'restore')) {
            return false;
        }

        return $model->restore() !== false;
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->newQuery();
    }

    /**
     * @param  Builder<TModel>  $query
     * @param  Closure|array<string, mixed>  $where
     * @return Builder<TModel>
     */
    private function applyWhere(Builder $query, Closure|array $where): Builder
    {
        if ($where instanceof Closure) {
            return $query->where($where);
        }

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $query->whereIn((string) $column, $value);

                continue;
            }

            $query->where((string) $column, '=', $value);
        }

        return $query;
    }

    /**
     * @return Builder<TModel>
     */
    private function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function newModelInstance(array $attributes): Model
    {
        /** @var TModel $instance */
        $instance = $this->model->newInstance($attributes);

        return $instance;
    }

    /**
     * Persist the model, turning a refused write into a hard failure
     * instead of a silent no-op.
     *
     * @param  TModel  $model
     * @return TModel
     *
     * @throws ModelNotSavedException
     */
    private function saveOrFail(Model $model, string $operation): Model
    {
        if ($model->save() === false) {
            throw ModelNotSavedException::for($model, $operation);
        }

        return $model;
    }
}
