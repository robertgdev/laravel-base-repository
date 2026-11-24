<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use RobertGDev\BaseRepository\Tests\Fixtures\Archive;
use RobertGDev\BaseRepository\Tests\Fixtures\ArchiveRepository;
use RobertGDev\BaseRepository\Tests\Fixtures\Article;
use RobertGDev\BaseRepository\Tests\Fixtures\ArticleRepository;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;

beforeEach(function (): void {
    makeArticlesTable();
    makeArchivesTable();
    makePostsTable();

    $this->repo = new ArticleRepository;
});

describe('soft deletes', function (): void {
    it('soft deletes through delete()', function (): void {
        $created = $this->repo->create(['name' => 'Gone']);

        expect($this->repo->delete($created->id))->toBeTrue()
            ->and($this->repo->find($created->id))->toBeNull()
            ->and($this->repo->get())->toHaveCount(0);

        $this->assertDatabaseHas('articles', ['id' => $created->id]);
    });

    it('keeps soft-deleted rows visible through withTrashed()', function (): void {
        $created = $this->repo->create(['name' => 'Gone']);
        $this->repo->delete($created->id);

        expect($this->repo->withTrashed()->count())->toBe(1)
            ->and($this->repo->withTrashed()->whereKey($created->id)->first()?->name)->toBe('Gone');
    });

    it('restricts onlyTrashed() to deleted rows', function (): void {
        $gone = $this->repo->create(['name' => 'Gone']);
        $this->repo->create(['name' => 'Here']);
        $this->repo->delete($gone->id);

        expect($this->repo->onlyTrashed()->count())->toBe(1)
            ->and($this->repo->onlyTrashed()->first()?->name)->toBe('Gone');
    });

    it('finds a trashed row with findWithTrashed()', function (): void {
        $created = $this->repo->create(['name' => 'Gone']);
        $this->repo->delete($created->id);

        expect($this->repo->findWithTrashed($created->id)?->name)->toBe('Gone')
            ->and($this->repo->findWithTrashed(999))->toBeNull();
    });

    it('reports trashed state', function (): void {
        $created = $this->repo->create(['name' => 'Alive']);
        $gone = $this->repo->create(['name' => 'Gone']);
        $this->repo->delete($gone->id);

        expect($this->repo->trashed($gone->id))->toBeTrue()
            ->and($this->repo->trashed($created->id))->toBeFalse()
            ->and($this->repo->trashed(999))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('restores a soft-deleted row', function (): void {
        $created = $this->repo->create(['name' => 'Back']);

        $this->repo->delete($created->id);

        expect($this->repo->restore($created->id))->toBeTrue()
            ->and($this->repo->find($created->id)?->name)->toBe('Back')
            ->and($this->repo->trashed($created->id))->toBeFalse();
    });

    it('fires the restoring and restored model events', function (): void {
        $created = $this->repo->create(['name' => 'Back']);
        $this->repo->delete($created->id);

        $events = [];

        Article::restoring(function () use (&$events): void {
            $events[] = 'restoring';
        });

        Article::restored(function () use (&$events): void {
            $events[] = 'restored';
        });

        $this->repo->restore($created->id);

        expect($events)->toBe(['restoring', 'restored']);

        Article::flushEventListeners();
    });

    it('returns null for a row that does not exist', function (): void {
        expect($this->repo->restore(999))->toBeNull();
    });

    it('returns null for a model that is not soft-deletable', function (): void {
        $posts = new PostRepository;
        $created = $posts->create(['name' => 'Plain']);

        expect($posts->restore($created->id))->toBeNull();
    });

    it('returns false when the restoring event vetoes the restore', function (): void {
        $created = $this->repo->create(['name' => 'Veto']);
        $this->repo->delete($created->id);

        Article::restoring(fn (): bool => false);

        expect($this->repo->restore($created->id))->toBeFalse()
            ->and($this->repo->trashed($created->id))->toBeTrue();

        Article::flushEventListeners();
    });

    it('returns null from forceDelete() for a missing row', function (): void {
        expect($this->repo->forceDelete(999))->toBeNull();
    });

    it('restores several rows in one bulk update', function (): void {
        $a = $this->repo->create(['name' => 'A']);
        $b = $this->repo->create(['name' => 'B']);
        $c = $this->repo->create(['name' => 'C']);

        $this->repo->deleteMany([$a->id, $b->id]);

        expect($this->repo->restoreMany([$a->id, $b->id, $c->id]))->toBe(2)
            ->and($this->repo->get())->toHaveCount(3);
    });

    it('restores nothing for a model that is not soft-deletable', function (): void {
        expect((new PostRepository)->restoreMany([1, 2]))->toBe(0);
    });
});

describe('bulk writes and global scopes', function (): void {
    it('does not resurrect a soft-deleted row with updateOrInsert()', function (): void {
        $created = $this->repo->create(['name' => 'Gone', 'status' => 'draft']);
        $this->repo->delete($created->id);

        // The trashed row is invisible, so this must insert a fresh row
        // instead of silently updating the soft-deleted one.
        expect($this->repo->updateOrInsert(['name' => 'Gone'], ['status' => 'archived']))->toBeTrue()
            ->and($this->repo->count())->toBe(1)
            ->and($this->repo->withTrashed()->count())->toBe(2)
            ->and($this->repo->first()?->status)->toBe('archived');
    });

    it('does not touch soft-deleted rows with updateWhere()', function (): void {
        $gone = $this->repo->create(['name' => 'Gone', 'status' => 'draft']);
        $this->repo->create(['name' => 'Here', 'status' => 'draft']);
        $this->repo->delete($gone->id);

        expect($this->repo->updateWhere(['status' => 'draft'], ['status' => 'published']))->toBe(1)
            ->and($this->repo->withTrashed()->whereKey($gone->id)->first()?->status)->toBe('draft');
    });

    it('does not touch soft-deleted rows with deleteWhere()', function (): void {
        $gone = $this->repo->create(['name' => 'Gone']);
        $this->repo->create(['name' => 'Here']);
        $this->repo->delete($gone->id);

        // deleteWhere() soft-deletes rather than removing rows.
        expect($this->repo->deleteWhere([]))->toBe(1)
            ->and($this->repo->count())->toBe(0)
            ->and($this->repo->withTrashed()->count())->toBe(2);
    });
});

describe('soft deletes on plain models', function (): void {
    it('treats withTrashed() as the plain query', function (): void {
        $repo = new PostRepository;
        $repo->create(['name' => 'A']);

        expect($repo->withTrashed()->count())->toBe(1);
    });

    it('matches nothing with onlyTrashed()', function (): void {
        $repo = new PostRepository;
        $repo->create(['name' => 'A']);

        expect($repo->onlyTrashed()->count())->toBe(0);
    });

    it('hard deletes with forceDelete()', function (): void {
        $created = $this->repo->create(['name' => 'Nuke']);

        expect($this->repo->forceDelete($created->id))->toBeTrue()
            ->and($this->repo->withTrashed()->count())->toBe(0);
    });
});

describe('custom deleted-at column', function (): void {
    beforeEach(function (): void {
        $this->archives = new ArchiveRepository;
    });

    it('soft deletes and restores using the custom column', function (): void {
        $created = $this->archives->create(['name' => 'Old']);

        $this->archives->delete($created->id);

        expect($this->archives->find($created->id))->toBeNull()
            ->and($this->archives->trashed($created->id))->toBeTrue()
            ->and($this->archives->withTrashed()->count())->toBe(1);

        $this->assertDatabaseHas('archives', ['id' => $created->id]);
        expect($created->fresh()?->getAttribute('removed_at'))->not->toBeNull();

        expect($this->archives->restore($created->id))->toBeTrue()
            ->and($this->archives->find($created->id)?->name)->toBe('Old');
    });

    it('bulk restores using the custom column', function (): void {
        $a = $this->archives->create(['name' => 'A']);
        $b = $this->archives->create(['name' => 'B']);
        $this->archives->deleteMany([$a->id, $b->id]);

        expect($this->archives->restoreMany([$a->id, $b->id]))->toBe(2)
            ->and($this->archives->count())->toBe(2);
    });

    it('exposes the custom deleted-at column metadata', function (): void {
        expect((new Archive)->getDeletedAtColumn())->toBe('removed_at');
    });
});
