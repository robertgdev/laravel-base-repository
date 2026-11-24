<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Database\Eloquent\Builder;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;
use RobertGDev\BaseRepository\Tests\Fixtures\VetoablePost;
use RobertGDev\BaseRepository\Tests\Fixtures\VetoablePostRepository;

beforeEach(function (): void {
    makePostsTable();

    VetoablePost::$vetoSave = false;
    VetoablePost::$vetoDelete = false;

    $this->repo = new PostRepository;
});

describe('insert', function (): void {
    it('inserts many rows in one statement', function (): void {
        expect($this->repo->insert([
            ['name' => 'A', 'status' => 'active'],
            ['name' => 'B', 'status' => 'draft'],
        ]))->toBeTrue()
            ->and($this->repo->count())->toBe(2);
    });
});

describe('insertOrIgnore', function (): void {
    it('ignores unique conflicts', function (): void {
        $this->repo->insertOrIgnore([['name' => 'A', 'email' => 'a@test.com']]);

        expect($this->repo->insertOrIgnore([['name' => 'A again', 'email' => 'a@test.com']]))->toBe(0)
            ->and($this->repo->count())->toBe(1)
            ->and($this->repo->first()?->name)->toBe('A');
    });

    it('returns the number of inserted rows', function (): void {
        expect($this->repo->insertOrIgnore([
            ['name' => 'A', 'email' => 'a@test.com'],
            ['name' => 'B', 'email' => 'b@test.com'],
        ]))->toBe(2);
    });
});

describe('insertGetId', function (): void {
    it('returns the generated id', function (): void {
        $id = $this->repo->insertGetId(['name' => 'A']);

        expect($id)->toBeInt()
            ->and($this->repo->find($id)?->name)->toBe('A');
    });
});

describe('upsert', function (): void {
    it('inserts new rows and updates conflicting ones', function (): void {
        expect($this->repo->upsert([
            ['email' => 'a@test.com', 'name' => 'A', 'status' => 'active'],
            ['email' => 'b@test.com', 'name' => 'B', 'status' => 'active'],
        ], ['email'], ['name']))->toBe(2)
            ->and($this->repo->count())->toBe(2);

        $this->repo->upsert([
            ['email' => 'a@test.com', 'name' => 'A renamed', 'status' => 'archived'],
        ], ['email'], ['name']);

        expect($this->repo->count())->toBe(2)
            ->and($this->repo->firstWhere('email', 'a@test.com')?->name)->toBe('A renamed')
            ->and($this->repo->firstWhere('email', 'a@test.com')?->status)->toBe('active');
    });

    it('updates every given column when no update list is passed', function (): void {
        $this->repo->upsert([['email' => 'a@test.com', 'name' => 'A', 'status' => 'active']], ['email']);
        $this->repo->upsert([['email' => 'a@test.com', 'name' => 'A2', 'status' => 'archived']], ['email']);

        expect($this->repo->firstWhere('email', 'a@test.com')?->status)->toBe('archived');
    });

    it('fills timestamps', function (): void {
        $this->repo->upsert([['name' => 'A', 'status' => 'active']], ['id'], ['name']);

        expect($this->repo->first()?->created_at)->not->toBeNull();
    });

    it('bypasses model events', function (): void {
        $repo = new VetoablePostRepository;
        VetoablePost::$vetoSave = true;

        expect($repo->upsert([['name' => 'A', 'status' => 'active']], ['id'], ['name']))->toBe(1)
            ->and($repo->count())->toBe(1);
    });
});

describe('updateWhere', function (): void {
    it('updates matching rows by array', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'draft']);
        $this->repo->create(['name' => 'B', 'status' => 'draft']);
        $this->repo->create(['name' => 'C', 'status' => 'active']);

        expect($this->repo->updateWhere(['status' => 'draft'], ['status' => 'published']))->toBe(2)
            ->and($this->repo->count(['status' => 'published']))->toBe(2);
    });

    it('treats array values as whereIn', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'draft']);
        $this->repo->create(['name' => 'B', 'status' => 'archived']);

        expect($this->repo->updateWhere(['status' => ['draft', 'archived']], ['views' => 9]))->toBe(2)
            ->and($this->repo->count(['views' => 9]))->toBe(2);
    });

    it('supports a closure for complex conditions', function (): void {
        $this->repo->create(['name' => 'A', 'views' => 5]);
        $this->repo->create(['name' => 'B', 'views' => 50]);

        expect($this->repo->updateWhere(fn (Builder $query) => $query->where('views', '>', 10), ['active' => false]))->toBe(1)
            ->and($this->repo->count(['active' => false]))->toBe(1);
    });

    it('bypasses model events', function (): void {
        $repo = new VetoablePostRepository;
        $repo->create(['name' => 'A', 'status' => 'draft']);

        VetoablePost::$vetoSave = true;

        expect($repo->updateWhere(['status' => 'draft'], ['status' => 'published']))->toBe(1);
    });
});

describe('updateOrInsert', function (): void {
    it('inserts when nothing matches', function (): void {
        expect($this->repo->updateOrInsert(['email' => 'new@test.com'], ['name' => 'New', 'status' => 'active']))->toBeTrue()
            ->and($this->repo->firstWhere('email', 'new@test.com')?->name)->toBe('New');
    });

    it('updates when a row matches', function (): void {
        $this->repo->create(['name' => 'Old', 'email' => 'x@test.com', 'status' => 'draft']);

        expect($this->repo->updateOrInsert(['email' => 'x@test.com'], ['name' => 'New', 'status' => 'active']))->toBeTrue()
            ->and($this->repo->count())->toBe(1)
            ->and($this->repo->firstWhere('email', 'x@test.com')?->name)->toBe('New');
    });

    it('supports a closure matcher', function (): void {
        $this->repo->create(['name' => 'Existing', 'status' => 'draft']);

        expect($this->repo->updateOrInsert(fn (Builder $query) => $query->where('status', 'draft'), ['status' => 'published']))->toBeTrue()
            ->and($this->repo->firstWhere('name', 'Existing')?->status)->toBe('published');

        expect($this->repo->updateOrInsert(fn (Builder $query) => $query->where('status', 'missing'), ['name' => 'Fresh']))->toBeTrue()
            ->and($this->repo->firstWhere('name', 'Fresh'))->not->toBeNull();
    });
});

describe('deleteWhere', function (): void {
    it('deletes matching rows', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'draft']);
        $this->repo->create(['name' => 'B', 'status' => 'active']);

        expect($this->repo->deleteWhere(['status' => 'draft']))->toBe(1)
            ->and($this->repo->count())->toBe(1);
    });

    it('treats array values as whereIn', function (): void {
        $this->repo->create(['name' => 'A', 'status' => 'draft']);
        $this->repo->create(['name' => 'B', 'status' => 'archived']);
        $this->repo->create(['name' => 'C', 'status' => 'active']);

        expect($this->repo->deleteWhere(['status' => ['draft', 'archived']]))->toBe(2)
            ->and($this->repo->count())->toBe(1);
    });

    it('supports a closure', function (): void {
        $this->repo->create(['name' => 'A', 'views' => 5]);
        $this->repo->create(['name' => 'B', 'views' => 50]);

        expect($this->repo->deleteWhere(fn (Builder $query) => $query->where('views', '>', 10)))->toBe(1);
    });
});

describe('deleteMany', function (): void {
    it('deletes the given ids', function (): void {
        $a = $this->repo->create(['name' => 'A']);
        $b = $this->repo->create(['name' => 'B']);
        $this->repo->create(['name' => 'C']);

        expect($this->repo->deleteMany([$a->id, $b->id]))->toBe(2)
            ->and($this->repo->count())->toBe(1);
    });

    it('returns zero for unknown ids', function (): void {
        expect($this->repo->deleteMany([999]))->toBe(0);
    });
});
