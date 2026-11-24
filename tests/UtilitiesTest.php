<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Support\Facades\DB;
use RobertGDev\BaseRepository\Tests\Fixtures\Article;
use RobertGDev\BaseRepository\Tests\Fixtures\Post;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;
use RuntimeException;

beforeEach(function (): void {
    makePostsTable();
    makeArticlesTable();

    $this->repo = new PostRepository;
});

describe('getModel', function (): void {
    it('returns the model instance', function (): void {
        expect($this->repo->getModel())->toBeInstanceOf(Post::class);
    });
});

describe('setModel', function (): void {
    it('swaps the model the repository queries', function (): void {
        $this->repo->create(['name' => 'A Post']);
        Article::create(['name' => 'An Article']);

        expect($this->repo->count())->toBe(1);

        $article = new Article;
        $result = $this->repo->setModel($article);

        expect($result)->toBe($this->repo)
            ->and($this->repo->getModel())->toBe($article)
            ->and($this->repo->count())->toBe(1)
            ->and($this->repo->first()?->name)->toBe('An Article');
    });
});

describe('increment / decrement', function (): void {
    it('increments a column by one', function (): void {
        $created = $this->repo->create(['name' => 'A', 'views' => 5]);

        expect($this->repo->increment($created->id, 'views'))->toBe(1)
            ->and($this->repo->find($created->id)?->views)->toBe(6);
    });

    it('increments by an amount', function (): void {
        $created = $this->repo->create(['name' => 'A', 'views' => 5]);

        expect($this->repo->increment($created->id, 'views', 10))->toBe(1)
            ->and($this->repo->find($created->id)?->views)->toBe(15);
    });

    it('updates extra columns in the same statement', function (): void {
        $created = $this->repo->create(['name' => 'A', 'views' => 0, 'status' => 'draft']);

        $this->repo->increment($created->id, 'views', 1, ['status' => 'hot']);

        expect($this->repo->find($created->id)?->status)->toBe('hot');
    });

    it('returns zero for an unknown id', function (): void {
        expect($this->repo->increment(999, 'views'))->toBe(0);
    });

    it('decrements a column', function (): void {
        $created = $this->repo->create(['name' => 'A', 'views' => 5]);

        expect($this->repo->decrement($created->id, 'views', 2))->toBe(1)
            ->and($this->repo->find($created->id)?->views)->toBe(3);
    });
});

describe('transaction', function (): void {
    it('commits on success', function (): void {
        $this->repo->transaction(function (): void {
            $this->repo->create(['name' => 'A']);
        });

        expect($this->repo->count())->toBe(1);
    });

    it('rolls back when the callback throws', function (): void {
        try {
            $this->repo->transaction(function (): void {
                $this->repo->create(['name' => 'Temp']);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        expect($this->repo->count())->toBe(0);
    });

    it('returns the callback result', function (): void {
        $result = $this->repo->transaction(fn () => $this->repo->count());

        expect($result)->toBe(0);
    });

    it('runs on the model connection', function (): void {
        expect(DB::transactionLevel())->toBeGreaterThan(0);
    });
});

describe('truncate', function (): void {
    it('empties the table', function (): void {
        $this->repo->create(['name' => 'A']);
        $this->repo->create(['name' => 'B']);

        $this->repo->truncate();

        expect($this->repo->count())->toBe(0);
    });
});
