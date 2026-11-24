<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Orchestra\Testbench\TestCase;
use RobertGDev\BaseRepository\BaseRepository;

uses(TestCase::class);
uses(RefreshDatabase::class);

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = [
        'name',
        'email',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}

class TestModelRepository extends BaseRepository
{
    public function __construct(TestModel $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return TestModel::class;
    }
}

describe('BaseRepository', function () {
    beforeEach(function () {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $this->repo = new TestModelRepository(new TestModel);
    });

    describe('find', function () {
        it('returns model by id', function () {
            $created = $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

            $found = $this->repo->find($created->id);

            expect($found)->not->toBeNull()
                ->and($found->name)->toBe('Alice');
        });

        it('returns null when not found', function () {
            expect($this->repo->find(999))->toBeNull();
        });
    });

    describe('findOrFail', function () {
        it('returns model by id', function () {
            $created = $this->repo->create(['name' => 'Bob', 'email' => 'bob@test.com']);

            $found = $this->repo->findOrFail($created->id);

            expect($found->name)->toBe('Bob');
        });

        it('throws ModelNotFoundException when not found', function () {
            $this->repo->findOrFail(999);
        })->throws(ModelNotFoundException::class);
    });

    describe('findOr', function () {
        it('returns model when found', function () {
            $created = $this->repo->create(['name' => 'Carol', 'email' => 'carol@test.com']);

            $result = $this->repo->findOr($created->id, fn () => 'fallback');

            expect($result)->toBeInstanceOf(TestModel::class)
                ->and($result->name)->toBe('Carol');
        });

        it('invokes callback when not found', function () {
            $result = $this->repo->findOr(999, fn () => 'fallback');

            expect($result)->toBe('fallback');
        });
    });

    describe('findOrNew', function () {
        it('returns existing model when found', function () {
            $created = $this->repo->create(['name' => 'Dave', 'email' => 'dave@test.com']);

            $result = $this->repo->findOrNew($created->id);

            expect($result->exists)->toBeTrue()
                ->and($result->name)->toBe('Dave');
        });

        it('returns new unsaved instance when not found', function () {
            $result = $this->repo->findOrNew(999);

            expect($result->exists)->toBeFalse();
        });
    });

    describe('findMany', function () {
        it('returns models for given ids', function () {
            $a = $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
            $b = $this->repo->create(['name' => 'B', 'email' => 'b@test.com']);
            $this->repo->create(['name' => 'C', 'email' => 'c@test.com']);

            $results = $this->repo->findMany([$a->id, $b->id]);

            expect($results)->toHaveCount(2);
        });

        it('returns empty collection for non-existent ids', function () {
            $results = $this->repo->findMany([999, 1000]);

            expect($results)->toHaveCount(0);
        });
    });

    describe('first', function () {
        it('returns first record', function () {
            $this->repo->create(['name' => 'First', 'email' => 'first@test.com']);
            $this->repo->create(['name' => 'Second', 'email' => 'second@test.com']);

            $result = $this->repo->first();

            expect($result)->not->toBeNull()
                ->and($result->name)->toBe('First');
        });

        it('returns null when table is empty', function () {
            expect($this->repo->first())->toBeNull();
        });
    });

    describe('firstOrFail', function () {
        it('returns first record', function () {
            $this->repo->create(['name' => 'First', 'email' => 'first@test.com']);

            $result = $this->repo->firstOrFail();

            expect($result->name)->toBe('First');
        });

        it('throws ModelNotFoundException when empty', function () {
            $this->repo->firstOrFail();
        })->throws(ModelNotFoundException::class);
    });

    describe('firstOr', function () {
        it('returns first record when found', function () {
            $this->repo->create(['name' => 'First', 'email' => 'first@test.com']);

            $result = $this->repo->firstOr(fn () => 'fallback');

            expect($result)->toBeInstanceOf(TestModel::class);
        });

        it('invokes callback when empty', function () {
            $result = $this->repo->firstOr(fn () => 'fallback');

            expect($result)->toBe('fallback');
        });
    });

    describe('firstOrCreate', function () {
        it('returns first matching record', function () {
            $this->repo->create(['name' => 'Eve', 'email' => 'eve@test.com']);

            $result = $this->repo->firstOrCreate(['email' => 'eve@test.com']);

            expect($result->name)->toBe('Eve');
        });

        it('creates when no match', function () {
            expect($this->repo->count())->toBe(0);

            $result = $this->repo->firstOrCreate(
                ['email' => 'new@test.com'],
                ['name' => 'New User']
            );

            expect($result->name)->toBe('New User')
                ->and($this->repo->count())->toBe(1);
        });
    });

    describe('firstOrNew', function () {
        it('returns existing record without duplicating', function () {
            $created = $this->repo->create(['name' => 'Frank', 'email' => 'frank@test.com']);

            $result = $this->repo->firstOrNew(['email' => 'frank@test.com']);

            expect($result->id)->toBe($created->id)
                ->and($result->exists)->toBeTrue();
        });

        it('returns new unsaved instance when no match', function () {
            $result = $this->repo->firstOrNew(['email' => 'ghost@test.com']);

            expect($result->exists)->toBeFalse()
                ->and($this->repo->count())->toBe(0);
        });
    });

    describe('get', function () {
        it('returns records via fresh query', function () {
            $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
            $this->repo->create(['name' => 'B', 'email' => 'b@test.com']);

            $results = $this->repo->get();

            expect($results)->toHaveCount(2);
        });

        it('returns empty collection when no records', function () {
            expect($this->repo->get())->toHaveCount(0);
        });
    });

    describe('count', function () {
        it('returns total count', function () {
            $this->repo->create(['name' => 'A', 'email' => 'a@test.com']);
            $this->repo->create(['name' => 'B', 'email' => 'b@test.com']);

            expect($this->repo->count())->toBe(2);
        });

        it('returns zero when empty', function () {
            expect($this->repo->count())->toBe(0);
        });

        it('counts with where conditions', function () {
            $this->repo->create(['name' => 'A', 'email' => 'a@test.com', 'active' => true]);
            $this->repo->create(['name' => 'B', 'email' => 'b@test.com', 'active' => false]);

            expect($this->repo->count(['active' => true]))->toBe(1)
                ->and($this->repo->count(['active' => false]))->toBe(1);
        });
    });

    describe('exists', function () {
        it('returns true when records match', function () {
            $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

            expect($this->repo->exists(['email' => 'alice@test.com']))->toBeTrue();
        });

        it('returns false when no records match', function () {
            expect($this->repo->exists(['email' => 'nobody@test.com']))->toBeFalse();
        });
    });

    describe('doesntExist', function () {
        it('returns true when no records match', function () {
            expect($this->repo->doesntExist(['email' => 'nobody@test.com']))->toBeTrue();
        });

        it('returns false when records match', function () {
            $this->repo->create(['name' => 'Alice', 'email' => 'alice@test.com']);

            expect($this->repo->doesntExist(['email' => 'alice@test.com']))->toBeFalse();
        });
    });

    describe('paginate', function () {
        it('returns paginated results', function () {
            for ($i = 1; $i <= 25; $i++) {
                $this->repo->create(['name' => "User{$i}", 'email' => "user{$i}@test.com"]);
            }

            $result = $this->repo->paginate(perPage: 10);

            expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
                ->and($result->total())->toBe(25)
                ->and($result->perPage())->toBe(10)
                ->and($result->items())->toHaveCount(10);
        });

        it('uses model default per page when not specified', function () {
            for ($i = 1; $i <= 20; $i++) {
                $this->repo->create(['name' => "User{$i}", 'email' => "user{$i}@test.com"]);
            }

            $result = $this->repo->paginate();

            expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
                ->and($result->total())->toBe(20)
                ->and($result->perPage())->toBe(15);
        });
    });

    describe('chunk', function () {
        it('chunks results', function () {
            for ($i = 1; $i <= 15; $i++) {
                $this->repo->create(['name' => "User{$i}", 'email' => "user{$i}@test.com"]);
            }

            $names = [];
            $this->repo->chunk(5, function ($chunk) use (&$names) {
                foreach ($chunk as $model) {
                    $names[] = $model->name;
                }

                return true;
            });

            expect($names)->toHaveCount(15);
        });
    });

    describe('lazy', function () {
        it('returns a LazyCollection', function () {
            for ($i = 1; $i <= 10; $i++) {
                $this->repo->create(['name' => "User{$i}", 'email' => "user{$i}@test.com"]);
            }

            $lazy = $this->repo->lazy(5);

            expect($lazy)->toBeInstanceOf(LazyCollection::class);

            $count = 0;
            foreach ($lazy as $model) {
                expect($model)->toBeInstanceOf(TestModel::class);
                $count++;
            }

            expect($count)->toBe(10);
        });
    });

    describe('create', function () {
        it('creates and persists a model', function () {
            $model = $this->repo->create(['name' => 'Grace', 'email' => 'grace@test.com']);

            expect($model->exists)->toBeTrue()
                ->and($model->name)->toBe('Grace')
                ->and($this->repo->count())->toBe(1);
        });
    });

    describe('make', function () {
        it('returns new unsaved instance', function () {
            $model = $this->repo->make(['name' => 'Henry', 'email' => 'henry@test.com']);

            expect($model->exists)->toBeFalse()
                ->and($model->name)->toBe('Henry')
                ->and($this->repo->count())->toBe(0);
        });
    });

    describe('update', function () {
        it('updates and returns the model', function () {
            $created = $this->repo->create(['name' => 'Ivy', 'email' => 'ivy@test.com']);

            $updated = $this->repo->update($created->id, ['name' => 'Ivy Updated']);

            expect($updated)->not->toBeNull()
                ->and($updated->name)->toBe('Ivy Updated');
        });

        it('returns null when model not found', function () {
            expect($this->repo->update(999, ['name' => 'Nobody']))->toBeNull();
        });
    });

    describe('updateOrCreate', function () {
        it('updates existing record', function () {
            $this->repo->create(['name' => 'Jack', 'email' => 'jack@test.com']);

            $result = $this->repo->updateOrCreate(
                ['email' => 'jack@test.com'],
                ['name' => 'Jack Updated']
            );

            expect($result->name)->toBe('Jack Updated')
                ->and($this->repo->count())->toBe(1);
        });

        it('creates when no match', function () {
            $result = $this->repo->updateOrCreate(
                ['email' => 'kate@test.com'],
                ['name' => 'Kate']
            );

            expect($result->name)->toBe('Kate')
                ->and($this->repo->count())->toBe(1);
        });
    });

    describe('delete', function () {
        it('deletes a model', function () {
            $created = $this->repo->create(['name' => 'Leo', 'email' => 'leo@test.com']);

            expect($this->repo->count())->toBe(1);

            $result = $this->repo->delete($created->id);

            expect($result)->toBeTrue()
                ->and($this->repo->count())->toBe(0);
        });

        it('returns null when model not found', function () {
            expect($this->repo->delete(999))->toBeNull();
        });
    });

    describe('forceDelete', function () {
        it('force deletes a model', function () {
            $created = $this->repo->create(['name' => 'Mia', 'email' => 'mia@test.com']);

            $result = $this->repo->forceDelete($created->id);

            expect($result)->toBeTrue()
                ->and($this->repo->find($created->id))->toBeNull();
        });

        it('returns null when model not found', function () {
            expect($this->repo->forceDelete(999))->toBeNull();
        });
    });

    describe('query', function () {
        it('returns an Eloquent builder', function () {
            $builder = $this->repo->query();

            expect($builder)->toBeInstanceOf(Builder::class);
        });

        it('can compose custom queries', function () {
            $this->repo->create(['name' => 'Active', 'email' => 'active@test.com', 'active' => true]);
            $this->repo->create(['name' => 'Inactive', 'email' => 'inactive@test.com', 'active' => false]);

            $results = $this->repo->query()
                ->where('active', true)
                ->orderBy('name', 'asc')
                ->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->name)->toBe('Active');
        });
    });

    describe('model', function () {
        it('returns model FQCN', function () {
            expect($this->repo->model())->toBe(TestModel::class);
        });
    });
});
