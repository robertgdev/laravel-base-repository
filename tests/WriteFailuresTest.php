<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use RobertGDev\BaseRepository\Exceptions\ModelNotSavedException;
use RobertGDev\BaseRepository\Tests\Fixtures\VetoablePost;
use RobertGDev\BaseRepository\Tests\Fixtures\VetoablePostRepository;

beforeEach(function (): void {
    makePostsTable();

    VetoablePost::$vetoSave = false;
    VetoablePost::$vetoDelete = false;

    $this->repo = new VetoablePostRepository;
});

describe('refused writes', function (): void {
    it('throws when create() is refused instead of returning an unsaved model', function (): void {
        VetoablePost::$vetoSave = true;

        expect(fn () => $this->repo->create(['name' => 'Nope']))
            ->toThrow(ModelNotSavedException::class);

        VetoablePost::$vetoSave = false;

        expect($this->repo->count())->toBe(0);
    });

    it('throws when update() is refused and leaves the row untouched', function (): void {
        $created = $this->repo->create(['name' => 'Original']);

        VetoablePost::$vetoSave = true;

        expect(fn () => $this->repo->update($created->id, ['name' => 'Changed']))
            ->toThrow(ModelNotSavedException::class);

        VetoablePost::$vetoSave = false;

        expect($this->repo->find($created->id)?->name)->toBe('Original');
    });

    it('throws when firstOrCreate() is refused', function (): void {
        VetoablePost::$vetoSave = true;

        expect(fn () => $this->repo->firstOrCreate(['email' => 'x@test.com'], ['name' => 'X']))
            ->toThrow(ModelNotSavedException::class);

        VetoablePost::$vetoSave = false;

        expect($this->repo->count())->toBe(0);
    });

    it('throws when updateOrCreate() is refused', function (): void {
        VetoablePost::$vetoSave = true;

        expect(fn () => $this->repo->updateOrCreate(['email' => 'x@test.com'], ['name' => 'X']))
            ->toThrow(ModelNotSavedException::class);

        VetoablePost::$vetoSave = false;

        expect($this->repo->count())->toBe(0);
    });

    it('returns a model that matches the database after a successful update', function (): void {
        $created = $this->repo->create(['name' => 'Original']);

        $updated = $this->repo->update($created->id, ['name' => 'Changed']);

        expect($updated?->name)->toBe('Changed')
            ->and($updated?->fresh()?->name)->toBe('Changed');
    });

    it('distinguishes a missing row from a refused delete', function (): void {
        expect($this->repo->delete(999))->toBeNull();

        $created = $this->repo->create(['name' => 'Sticky']);

        VetoablePost::$vetoDelete = true;

        expect($this->repo->delete($created->id))->toBeFalse()
            ->and($this->repo->find($created->id))->not->toBeNull();

        VetoablePost::$vetoDelete = false;

        expect($this->repo->delete($created->id))->toBeTrue()
            ->and($this->repo->find($created->id))->toBeNull();
    });

    it('does not throw from bulk writes, which bypass events', function (): void {
        VetoablePost::$vetoSave = true;

        expect($this->repo->updateWhere(['name' => 'nobody'], ['name' => 'x']))->toBe(0)
            ->and($this->repo->insert([['name' => 'Bulk']]))->toBeTrue();
    });
});
