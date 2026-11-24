<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use RobertGDev\BaseRepository\Tests\Fixtures\Post;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;

beforeEach(function (): void {
    makePostsTable();

    $this->repo = new PostRepository;
});

describe('find', function (): void {
    it('returns the model by primary key', function (): void {
        $created = $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $found = $this->repo->find($created->id);

        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('Alice');
    });

    it('returns null when not found', function (): void {
        expect($this->repo->find(999))->toBeNull();
    });

    it('accepts a string key', function (): void {
        $created = $this->repo->create(['name' => 'Alice']);

        expect($this->repo->find((string) $created->id))->not->toBeNull();
    });
});

describe('findOrFail', function (): void {
    it('returns the model', function (): void {
        $created = $this->repo->create(['name' => 'Bob']);

        expect($this->repo->findOrFail($created->id)->name)->toBe('Bob');
    });

    it('throws ModelNotFoundException', function (): void {
        $this->repo->findOrFail(999);
    })->throws(ModelNotFoundException::class);
});

describe('findOr', function (): void {
    it('returns the model when found', function (): void {
        $created = $this->repo->create(['name' => 'Carol']);

        expect($this->repo->findOr($created->id, fn () => 'fallback')->name)->toBe('Carol');
    });

    it('invokes the callback when not found', function (): void {
        expect($this->repo->findOr(999, fn () => 'fallback'))->toBe('fallback');
    });

    it('invokes the callback with no arguments, matching Eloquent', function (): void {
        $received = $this->repo->findOr(999, fn (...$args) => $args);

        expect($received)->toBe([]);
    });
});

describe('findOrNew', function (): void {
    it('returns the existing model', function (): void {
        $created = $this->repo->create(['name' => 'Dave']);

        $result = $this->repo->findOrNew($created->id);

        expect($result->exists)->toBeTrue()
            ->and($result->name)->toBe('Dave');
    });

    it('returns an unsaved instance when not found', function (): void {
        expect($this->repo->findOrNew(999)->exists)->toBeFalse();
    });
});

describe('findMany', function (): void {
    it('returns models for the given ids', function (): void {
        $a = $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
        $b = $this->repo->create(['name' => 'B', 'email' => 'b@test.com']);
        $this->repo->create(['name' => 'C', 'email' => 'c@test.com']);

        expect($this->repo->findMany([$a->id, $b->id]))->toHaveCount(2);
    });

    it('returns an empty collection for unknown ids', function (): void {
        expect($this->repo->findMany([999, 1000]))->toHaveCount(0);
    });
});

describe('findManyOrFail', function (): void {
    it('returns every requested model', function (): void {
        $a = $this->repo->create(['name' => 'A']);
        $b = $this->repo->create(['name' => 'B']);

        expect($this->repo->findManyOrFail([$a->id, $b->id]))->toHaveCount(2);
    });

    it('throws when any id is missing', function (): void {
        $a = $this->repo->create(['name' => 'A']);

        $this->repo->findManyOrFail([$a->id, 999]);
    })->throws(ModelNotFoundException::class);

    it('tolerates duplicate ids', function (): void {
        $a = $this->repo->create(['name' => 'A']);

        expect($this->repo->findManyOrFail([$a->id, $a->id]))->toHaveCount(1);
    });
});

describe('first', function (): void {
    it('returns the first record', function (): void {
        $this->repo->create(['name' => 'First']);
        $this->repo->create(['name' => 'Second']);

        expect($this->repo->first()->name)->toBe('First');
    });

    it('returns null when empty', function (): void {
        expect($this->repo->first())->toBeNull();
    });

    it('accepts a single column name', function (): void {
        $this->repo->create(['name' => 'First', 'email' => 'f@test.com']);

        $attributes = $this->repo->first('name')?->getAttributes();

        expect($attributes)->toHaveKey('name')
            ->and($attributes)->not->toHaveKey('email');
    });
});

describe('firstOrFail', function (): void {
    it('returns the first record', function (): void {
        $this->repo->create(['name' => 'First']);

        expect($this->repo->firstOrFail()->name)->toBe('First');
    });

    it('throws when empty', function (): void {
        $this->repo->firstOrFail();
    })->throws(ModelNotFoundException::class);
});

describe('firstOr', function (): void {
    it('returns the first record when found', function (): void {
        $this->repo->create(['name' => 'First']);

        expect($this->repo->firstOr(fn () => 'fallback'))->toBeInstanceOf(Post::class);
    });

    it('invokes the callback when empty', function (): void {
        expect($this->repo->firstOr(fn () => 'fallback'))->toBe('fallback');
    });

    it('accepts columns as a second argument', function (): void {
        $this->repo->create(['name' => 'First']);

        expect($this->repo->firstOr(fn () => 'fallback', ['name'])?->getAttributes())->toHaveKey('name');
    });
});

describe('firstWhere', function (): void {
    it('returns the first match', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'active']);
        $this->repo->create(['name' => 'B', 'status' => 'archived']);

        expect($this->repo->firstWhere('status', 'archived')->name)->toBe('B');
    });

    it('accepts an array of conditions', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'archived']);
        $this->repo->create(['name' => 'B', 'status' => 'active']);

        expect($this->repo->firstWhere(['name' => 'B', 'status' => 'active'])->name)->toBe('B');
    });

    it('treats array values as whereIn', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'draft']);
        $this->repo->create(['name' => 'B', 'status' => 'archived']);

        expect($this->repo->firstWhere(['status' => ['active', 'archived']])->name)->toBe('B')
            ->and($this->repo->firstWhere(['status' => ['draft']])->name)->toBe('A')
            ->and($this->repo->firstWhere(['status' => ['missing']]))->toBeNull();
    });
});

describe('get', function (): void {
    it('returns every record', function (): void {
        $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
        $this->repo->create(['name' => 'B', 'email' => 'b@test.com']);
        $this->repo->create(['name' => 'C', 'email' => 'c@test.com']);

        expect($this->repo->get())->toHaveCount(3);
    });

    it('returns an empty collection when empty', function (): void {
        expect($this->repo->get())->toHaveCount(0);
    });

    it('honours the requested columns', function (): void {
        $this->repo->create(['name' => 'A']);

        $attributes = $this->repo->get(['name'])->first()?->getAttributes();

        expect($attributes)->toHaveKey('name')
            ->and($attributes)->not->toHaveKey('status');
    });
});

describe('count', function (): void {
    it('counts all rows', function (): void {
        $this->repo->create(['name' => 'A']);
        $this->repo->create(['name' => 'B']);

        expect($this->repo->count())->toBe(2);
    });

    it('returns zero when empty', function (): void {
        expect($this->repo->count())->toBe(0);
    });

    it('counts with simple conditions', function (): void {
        $this->repo->create(['name' => 'A', 'active' => true]);
        $this->repo->create(['name' => 'B', 'active' => false]);

        expect($this->repo->count(['active' => true]))->toBe(1)
            ->and($this->repo->count(['active' => false]))->toBe(1);
    });

    it('treats array values as whereIn', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'active']);
        $this->repo->create(['name' => 'B', 'status' => 'archived']);
        $this->repo->create(['name' => 'C', 'status' => 'draft']);

        expect($this->repo->count(['status' => ['active', 'archived']]))->toBe(2)
            ->and($this->repo->count(['status' => ['active', 'archived'], 'active' => true]))->toBe(2)
            ->and($this->repo->count(['status' => []]))->toBe(0);
    });

    it('supports null conditions', function (): void {
        $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
        $this->repo->create(['name' => 'B']);

        expect($this->repo->count(['email' => null]))->toBe(1);
    });

    it('supports a closure for complex conditions', function (): void {
        $this->repo->create(['name' => 'A', 'views' => 5]);
        $this->repo->create(['name' => 'B', 'views' => 50]);

        expect($this->repo->count(fn (Builder $query) => $query->where('views', '>', 10)))->toBe(1);
    });

    it('counts a specific column', function (): void {
        $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
        $this->repo->create(['name' => 'B']);

        expect($this->repo->count([], 'email'))->toBe(1);
    });
});

describe('exists / doesntExist', function (): void {
    it('reports existence', function (): void {
        $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

        expect($this->repo->exists(['email' => 'alice@test.com']))->toBeTrue()
            ->and($this->repo->exists(['email' => 'nobody@test.com']))->toBeFalse();
    });

    it('works with no arguments', function (): void {
        expect($this->repo->exists())->toBeFalse();

        $this->repo->create(['name' => 'Alice']);

        expect($this->repo->exists())->toBeTrue();
    });

    it('treats array values as whereIn', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'archived']);

        expect($this->repo->exists(['status' => ['active', 'archived']]))->toBeTrue()
            ->and($this->repo->exists(['status' => ['draft']]))->toBeFalse();
    });

    it('inverts existence', function (): void {
        $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

        expect($this->repo->doesntExist(['email' => 'nobody@test.com']))->toBeTrue()
            ->and($this->repo->doesntExist(['email' => 'alice@test.com']))->toBeFalse()
            ->and($this->repo->doesntExist())->toBeFalse();
    });
});

describe('existsById', function (): void {
    it('reports existence without hydrating a model', function (): void {
        $created = $this->repo->create(['name' => 'A']);

        expect($this->repo->existsById($created->id))->toBeTrue()
            ->and($this->repo->existsById(999))->toBeFalse();
    });
});

describe('paginate', function (): void {
    beforeEach(function (): void {
        for ($i = 1; $i <= 25; $i++) {
            $this->repo->create(['name' => "User{$i}", 'email' => "user{$i}@test.com"]);
        }
    });

    it('returns a paginated result', function (): void {
        $result = $this->repo->paginate(perPage: 10);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->total())->toBe(25)
            ->and($result->perPage())->toBe(10)
            ->and($result->items())->toHaveCount(10);
    });

    it('falls back to the model per page', function (): void {
        expect($this->repo->paginate()->perPage())->toBe(15);
    });

    it('falls back to the model per page for zero', function (): void {
        expect($this->repo->paginate(0)->perPage())->toBe(15);
    });

    it('falls back to the model per page for negative values', function (): void {
        expect($this->repo->paginate(-5)->perPage())->toBe(15)
            ->and($this->repo->paginate(-5)->items())->toHaveCount(15);
    });
});

describe('simplePaginate', function (): void {
    it('returns a simple paginator', function (): void {
        for ($i = 1; $i <= 12; $i++) {
            $this->repo->create(['name' => "User{$i}"]);
        }

        $result = $this->repo->simplePaginate(5);

        expect($result)->toBeInstanceOf(Paginator::class)
            ->and($result->perPage())->toBe(5)
            ->and($result->items())->toHaveCount(5)
            ->and($result->hasMorePages())->toBeTrue();
    });
});

describe('cursorPaginate', function (): void {
    it('returns a cursor paginator', function (): void {
        for ($i = 1; $i <= 5; $i++) {
            $this->repo->create(['name' => "User{$i}"]);
        }

        $result = $this->repo->cursorPaginate(2);

        expect($result)->toBeInstanceOf(CursorPaginator::class)
            ->and($result->items())->toHaveCount(2);
    });

    it('clamps an invalid per page', function (): void {
        expect($this->repo->cursorPaginate(0)->perPage())->toBe(15);
    });
});

describe('create', function (): void {
    it('creates and persists a model', function (): void {
        $model = $this->repo->create(['name' => 'Grace', 'email' => 'grace@test.com']);

        expect($model->exists)->toBeTrue()
            ->and($model->name)->toBe('Grace')
            ->and($this->repo->count())->toBe(1);
    });

    it('applies casts', function (): void {
        $model = $this->repo->create(['name' => 'Grace', 'active' => 1]);

        expect($model->active)->toBeTrue();
    });
});

describe('make', function (): void {
    it('returns an unsaved instance', function (): void {
        $model = $this->repo->make(['name' => 'Henry']);

        expect($model->exists)->toBeFalse()
            ->and($model->name)->toBe('Henry')
            ->and($this->repo->count())->toBe(0);
    });
});

describe('update', function (): void {
    it('updates and returns the model', function (): void {
        $created = $this->repo->create(['name' => 'Ivy']);

        $updated = $this->repo->update($created->id, ['name' => 'Ivy Updated']);

        expect($updated)->not->toBeNull()
            ->and($updated->name)->toBe('Ivy Updated')
            ->and($this->repo->find($created->id)?->name)->toBe('Ivy Updated');
    });

    it('returns null when the model does not exist', function (): void {
        expect($this->repo->update(999, ['name' => 'Nobody']))->toBeNull();
    });
});

describe('updateOrCreate', function (): void {
    it('updates the existing record', function (): void {
        $this->repo->create(['name' => 'Jack', 'email' => 'jack@test.com']);

        $result = $this->repo->updateOrCreate(['email' => 'jack@test.com'], ['name' => 'Jack Updated']);

        expect($result->name)->toBe('Jack Updated')
            ->and($this->repo->count())->toBe(1);
    });

    it('creates when there is no match', function (): void {
        $result = $this->repo->updateOrCreate(['email' => 'kate@test.com'], ['name' => 'Kate']);

        expect($result->name)->toBe('Kate')
            ->and($this->repo->count())->toBe(1);
    });
});

describe('firstOrCreate / firstOrNew', function (): void {
    it('returns the existing record without duplicating', function (): void {
        $created = $this->repo->create(['name' => 'Eve', 'email' => 'eve@test.com']);

        $result = $this->repo->firstOrCreate(['email' => 'eve@test.com'], ['name' => 'Other']);

        expect($result->id)->toBe($created->id)
            ->and($result->name)->toBe('Eve')
            ->and($this->repo->count())->toBe(1);
    });

    it('creates when there is no match', function (): void {
        $result = $this->repo->firstOrCreate(['email' => 'new@test.com'], ['name' => 'New User']);

        expect($result->name)->toBe('New User')
            ->and($result->exists)->toBeTrue()
            ->and($this->repo->count())->toBe(1);
    });

    it('returns an unsaved instance from firstOrNew', function (): void {
        $result = $this->repo->firstOrNew(['email' => 'ghost@test.com']);

        expect($result->exists)->toBeFalse()
            ->and($this->repo->count())->toBe(0);
    });
});

describe('delete', function (): void {
    it('deletes the model', function (): void {
        $created = $this->repo->create(['name' => 'Leo']);

        expect($this->repo->delete($created->id))->toBeTrue()
            ->and($this->repo->count())->toBe(0);
    });

    it('returns null when the model does not exist', function (): void {
        expect($this->repo->delete(999))->toBeNull();
    });
});

describe('forceDelete', function (): void {
    it('deletes the model regardless of soft deletes', function (): void {
        $created = $this->repo->create(['name' => 'Mia']);

        expect($this->repo->forceDelete($created->id))->toBeTrue()
            ->and($this->repo->find($created->id))->toBeNull();
    });

    it('returns null when the model does not exist', function (): void {
        expect($this->repo->forceDelete(999))->toBeNull();
    });
});

describe('query', function (): void {
    it('returns an Eloquent builder', function (): void {
        expect($this->repo->query())->toBeInstanceOf(Builder::class);
    });

    it('composes custom queries', function (): void {
        $this->repo->create(['name' => 'Active', 'active' => true]);
        $this->repo->create(['name' => 'Inactive', 'active' => false]);

        $results = $this->repo->query()->where('active', true)->orderBy('name', 'asc')->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('Active');
    });

    it('returns a fresh builder each time', function (): void {
        $this->repo->create(['name' => 'A']);

        expect($this->repo->query()->where('name', 'A')->count())->toBe(1)
            ->and($this->repo->query()->count())->toBe(1);
    });
});

describe('model', function (): void {
    it('returns the model FQCN', function (): void {
        expect($this->repo->model())->toBe(Post::class);
    });
});
