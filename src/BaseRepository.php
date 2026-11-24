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
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as SupportCollection;
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

    /**
     * Find a row by primary key, including soft-deleted rows.
     */
    public function findWithTrashed(int|string $id): ?Model
    {
        return $this->withTrashed()->find($id);
    }

    public function findMany(array $ids): Collection
    {
        return $this->newQuery()->findMany($ids);
    }

    /**
     * @param  array<int, int|string>  $ids
     *
     * @throws ModelNotFoundException
     */
    public function findManyOrFail(array $ids): Collection
    {
        $models = $this->newQuery()->findMany($ids);

        /** @var array<int, int|string> $unique */
        $unique = array_values(array_unique($ids, SORT_REGULAR));

        if ($models->count() !== count($unique)) {
            throw (new ModelNotFoundException)->setModel($this->model(), $unique);
        }

        return $models;
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

    /**
     * When an array of conditions is given, array values are treated as
     * whereIn(), exactly like every other $where parameter.
     *
     * @param  array<string, mixed>|string  $column
     */
    public function firstWhere(array|string $column, mixed $operator = null, mixed $value = null): ?Model
    {
        if (is_array($column)) {
            return $this->applyWhere($this->newQuery(), $column)->first();
        }

        return $this->newQuery()->firstWhere($column, $operator, $value);
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

    public function existsById(int|string $id): bool
    {
        return $this->newQuery()->whereKey($id)->exists();
    }

    /**
     * @return SupportCollection<array-key, mixed>
     */
    public function pluck(string $column, ?string $key = null): SupportCollection
    {
        return $this->newQuery()->pluck($column, $key);
    }

    public function value(string $column): mixed
    {
        return $this->newQuery()->value($column);
    }

    public function sum(string $column): int|float
    {
        return $this->toNumber($this->newQuery()->sum($column));
    }

    public function avg(string $column): ?float
    {
        $value = $this->newQuery()->avg($column);

        return $value === null ? null : (float) $value;
    }

    public function max(string $column): mixed
    {
        return $this->newQuery()->max($column);
    }

    public function min(string $column): mixed
    {
        return $this->newQuery()->min($column);
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
     * @param  array<int, string>  $columns
     */
    public function simplePaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $pageName = 'page',
        ?int $page = null,
    ): Paginator {
        return $this->newQuery()->simplePaginate(
            $this->resolvePerPage($perPage),
            $columns,
            $pageName,
            $page,
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function cursorPaginate(
        ?int $perPage = null,
        array $columns = ['*'],
        string $cursorName = 'cursor',
        Cursor|string|null $cursor = null,
    ): CursorPaginator {
        return $this->newQuery()->cursorPaginate(
            $this->resolvePerPage($perPage),
            $columns,
            $cursorName,
            $cursor,
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

    /**
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection
    {
        return $this->newQuery()->cursor();
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
     * Update matching rows in a single query. Global scopes apply; model
     * events, observers, casts and mutators do not. Prefer update()/
     * updateOrCreate() when those matter.
     *
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     * @param  array<string, mixed>  $values
     * @return int number of affected rows
     */
    public function updateWhere(Closure|array $where, array $values): int
    {
        return (int) $this->applyWhere($this->newQuery(), $where)->update($values);
    }

    /**
     * Insert one or many rows in a single query. Bypasses model events,
     * casts and automatic timestamps — supply them yourself.
     *
     * @param  array<int, array<string, mixed>>  $values
     */
    public function insert(array $values): bool
    {
        return $this->newQuery()->insert($values);
    }

    /**
     * Insert rows, silently ignoring unique-key conflicts.
     *
     * @param  array<int, array<string, mixed>>  $values
     * @return int number of inserted rows
     */
    public function insertOrIgnore(array $values): int
    {
        return (int) $this->newQuery()->insertOrIgnore($values);
    }

    /**
     * Insert a single row and return its generated id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function insertGetId(array $attributes, ?string $sequence = null): int
    {
        return (int) $this->newQuery()->insertGetId($attributes, $sequence);
    }

    /**
     * Insert rows, updating the ones that already exist, in one
     * statement. Timestamps are filled automatically, but model events,
     * observers, casts and mutators are NOT applied — pass raw database
     * values. $uniqueBy must be backed by a unique index (on engines
     * whose conflict target is advisory, a missing index inserts
     * duplicates instead of updating).
     *
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>|null  $update  Columns to update on conflict; null updates every column given.
     * @return int number of inserted/updated rows
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        return (int) $this->newQuery()->upsert($values, $uniqueBy, $update);
    }

    /**
     * Insert the row when it does not exist, otherwise update it — no
     * model events or casts. Global scopes apply, so a soft-deleted row
     * is not resurrected; a fresh row is inserted instead. With an array
     * matcher the inserted row contains $attributes merged with $values;
     * with a closure matcher $values must be complete.
     *
     * @param  Closure|array<string, mixed>  $attributes  Array values are treated as whereIn().
     * @param  array<string, mixed>  $values
     */
    public function updateOrInsert(Closure|array $attributes, array $values = []): bool
    {
        $query = $this->applyWhere($this->newQuery(), $attributes);

        if ((clone $query)->exists()) {
            $query->update($values);

            return true;
        }

        $row = $attributes instanceof Closure ? $values : array_merge($attributes, $values);

        return (bool) $this->newQuery()->insert($row);
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
     * Delete matching rows in a single query. Model events do not fire;
     * soft-deletable models are still soft-deleted. An empty $where
     * deletes every row (global scopes still apply) — guard the caller.
     *
     * @param  Closure|array<string, mixed>  $where  Array values are treated as whereIn().
     * @return int number of affected rows
     */
    public function deleteWhere(Closure|array $where): int
    {
        return $this->toInt($this->applyWhere($this->newQuery(), $where)->delete());
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return int number of affected rows
     */
    public function deleteMany(array $ids): int
    {
        return $this->toInt($this->newQuery()->whereKey($ids)->delete());
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
        if (! $this->usesSoftDeletes()) {
            return null;
        }

        $model = $this->findWithTrashed($id);

        if ($model === null || ! method_exists($model, 'restore')) {
            return null;
        }

        return $model->restore() !== false;
    }

    /**
     * Restore several soft-deleted rows in one bulk update. Unlike
     * restore(), no model events fire.
     *
     * @param  array<int, int|string>  $ids
     * @return int number of restored rows
     */
    public function restoreMany(array $ids): int
    {
        $column = $this->deletedAtColumn();

        if ($column === null) {
            return 0;
        }

        return (int) $this->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($ids)
            ->whereNotNull($column)
            ->update([$column => null]);
    }

    /**
     * Whether the row exists and is currently soft-deleted.
     */
    public function trashed(int|string $id): bool
    {
        return $this->onlyTrashed()->whereKey($id)->exists();
    }

    /**
     * Query including soft-deleted rows. For models that do not use
     * SoftDeletes this is identical to query().
     *
     * @return Builder<TModel>
     */
    public function withTrashed(): Builder
    {
        $query = $this->newQuery();

        return $this->usesSoftDeletes()
            ? $query->withoutGlobalScope(SoftDeletingScope::class)
            : $query;
    }

    /**
     * Query matching soft-deleted rows only. For models that do not use
     * SoftDeletes it matches nothing.
     *
     * @return Builder<TModel>
     */
    public function onlyTrashed(): Builder
    {
        $column = $this->deletedAtColumn();

        if ($column === null) {
            return $this->newQuery()->whereRaw('1 = 0');
        }

        return $this->withTrashed()->whereNotNull($column);
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
     * Run the callback inside a transaction on the model's connection.
     */
    public function transaction(Closure $callback): mixed
    {
        return $this->model->getConnection()->transaction($callback);
    }

    /**
     * Atomically increment a column. No model events or casts;
     * updated_at is maintained.
     *
     * @param  array<string, mixed>  $extra
     * @return int number of affected rows
     */
    public function increment(int|string $id, string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->newQuery()->whereKey($id)->increment($column, $amount, $extra);
    }

    /**
     * Atomically decrement a column. No model events or casts;
     * updated_at is maintained.
     *
     * @param  array<string, mixed>  $extra
     * @return int number of affected rows
     */
    public function decrement(int|string $id, string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->newQuery()->whereKey($id)->decrement($column, $amount, $extra);
    }

    /**
     * Empty the table. Bypasses model events and global scopes, and on
     * some engines (MySQL) cannot be rolled back.
     */
    public function truncate(): void
    {
        $this->newQuery()->truncate();
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
    /**
     * Normalise an affected-row count to an int.
     */
    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    /**
     * The model's deleted-at column, or null when it is not
     * soft-deletable.
     */
    private function deletedAtColumn(): ?string
    {
        if (! $this->usesSoftDeletes()) {
            return null;
        }

        $class = $this->model::class;
        $column = defined($class.'::DELETED_AT') ? constant($class.'::DELETED_AT') : 'deleted_at';

        return is_string($column) ? $column : 'deleted_at';
    }

    /**
     * Normalise an aggregate result to a number.
     */
    private function toNumber(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return 0;
    }

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
