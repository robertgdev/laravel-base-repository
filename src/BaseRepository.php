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
     * @var TModel
     */
    protected Model $model;

    /**
     * @param  TModel|null  $model
     */
    public function __construct(?Model $model = null)
    {
        /** @var TModel $instance */
        $instance = $model ?? $this->newModel();

        $this->model = $instance;
    }

    /**
     * The model class this repository works with — the single source of
     * truth for the repository's model.
     *
     * @return class-string<TModel>
     */
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

    /**
     * Invoke $callback (with no arguments, matching Eloquent) when the
     * row does not exist.
     *
     * @param  Closure(): mixed  $callback
     */
    public function findOr(int|string $id, Closure $callback): mixed
    {
        return $this->newQuery()->find($id) ?? $callback();
    }

    /**
     * @param  array<int, string>|string  $columns
     */
    public function findOrNew(int|string $id, array|string $columns = ['*']): Model
    {
        return $this->newQuery()->findOrNew($id, $columns);
    }

    public function findMany(array $ids): Collection
    {
        return $this->newQuery()->findMany($ids);
    }

    /**
     * @param  array<int, string>|string  $columns
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return $this->newQuery()->first($columns);
    }

    /**
     * @param  array<int, string>|string  $columns
     */
    public function firstOrFail(array|string $columns = ['*']): Model
    {
        return $this->newQuery()->firstOrFail($columns);
    }

    /**
     * Return the first row, or the result of $callback when the set is
     * empty.
     *
     * @param  Closure(): mixed  $callback
     * @param  array<int, string>|string  $columns
     */
    public function firstOr(Closure $callback, array|string $columns = ['*']): mixed
    {
        return $this->newQuery()->first($columns) ?? $callback();
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

    public function get(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function count(Closure|array $where = [], string $columns = '*'): int
    {
        return $this->applyWhere($this->newQuery(), $where)->count($columns);
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function exists(Closure|array $where = []): bool
    {
        return $this->applyWhere($this->newQuery(), $where)->exists();
    }

    /**
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     */
    public function doesntExist(Closure|array $where = []): bool
    {
        return ! $this->exists($where);
    }

    public function paginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
    ): LengthAwarePaginator {
        return $this->newQuery()->paginate(
            $this->resolvePerPage($perPage),
            $columns,
            $pageName,
            $page,
        );
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

    /**
     * Delete a row by primary key.
     *
     * @return bool|null null when the row does not exist; false when a
     *                   deleting listener vetoed the delete (the row is
     *                   still there); true when it was deleted.
     */
    public function delete(int|string $id): ?bool
    {
        $model = $this->newQuery()->find($id);

        if ($model === null) {
            return null;
        }

        return $model->delete() !== false;
    }

    /**
     * Hard-delete a row, bypassing SoftDeletes.
     *
     * @return bool|null null when the row does not exist; false when a
     *                   forceDeleting listener vetoed the delete (only
     *                   possible on soft-deletable models); true when it
     *                   was hard-deleted.
     */
    public function forceDelete(int|string $id): ?bool
    {
        $model = $this->newQuery()->find($id);

        if ($model === null) {
            return null;
        }

        return $model->forceDelete() !== false;
    }

    /**
     * Restore a single soft-deleted row, firing the restoring/restored
     * model events.
     *
     * @return bool|null null when the row does not exist or the model is
     *                   not soft-deletable; false when a restoring
     *                   listener vetoed the restore; true when it was
     *                   restored.
     */
    public function restore(int|string $id): ?bool
    {
        // withTrashed() is a builder macro registered by SoftDeletingScope,
        // not a model method: method_exists() can never see it, so detect
        // soft-deletability via the trait instead.
        if (! in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            return null;
        }

        $model = $this->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($id)
            ->first();

        if ($model === null || ! method_exists($model, 'restore')) {
            return null;
        }

        return $model->restore() !== false;
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Swap the model instance — e.g. to point the repository at a read
     * replica connection or at a differently configured subclass.
     *
     * @param  TModel  $model
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
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
     * Never trust a caller-supplied page size: null, zero and negative
     * values all fall back to the model's own per-page setting.
     */
    private function resolvePerPage(?int $perPage): int
    {
        return $perPage !== null && $perPage > 0
            ? $perPage
            : $this->model->getPerPage();
    }

    /**
     * @return Builder<TModel>
     */
    private function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @return TModel
     */
    private function newModel(): Model
    {
        $class = $this->model();

        /** @var TModel $instance */
        $instance = new $class;

        return $instance;
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
