<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Support\LazyCollection;
use RobertGDev\BaseRepository\Tests\Fixtures\Post;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;

beforeEach(function (): void {
    makePostsTable();

    $this->repo = new PostRepository;

    for ($i = 1; $i <= 10; $i++) {
        $this->repo->create(['name' => "Post {$i}"]);
    }
});

describe('chunk', function (): void {
    it('processes every row', function (): void {
        $names = [];

        $this->repo->chunk(3, function ($chunk) use (&$names): bool {
            foreach ($chunk as $model) {
                $names[] = $model->name;
            }

            return true;
        });

        expect($names)->toHaveCount(10)
            ->and($names[0])->toBe('Post 1');
    });

    it('stops when the callback returns false', function (): void {
        $pages = 0;

        $this->repo->chunk(3, function () use (&$pages): bool {
            $pages++;

            return false;
        });

        expect($pages)->toBe(1);
    });
});

describe('chunkById', function (): void {
    it('processes every row', function (): void {
        $count = 0;

        $this->repo->chunkById(3, function ($chunk) use (&$count): bool {
            $count += $chunk->count();

            return true;
        });

        expect($count)->toBe(10);
    });

    it('does not skip rows when the callback mutates the result set', function (): void {
        $names = [];

        $this->repo->query()->where('status', 'active')->chunkById(3, function ($chunk) use (&$names): bool {
            foreach ($chunk as $model) {
                $names[] = $model->name;
            }

            Post::whereKey($chunk->modelKeys())->update(['status' => 'processed']);

            return true;
        });

        expect($names)->toHaveCount(10)
            ->and($this->repo->count(['status' => 'processed']))->toBe(10);
    });
});

describe('lazy / lazyById', function (): void {
    it('streams every row lazily', function (): void {
        $lazy = $this->repo->lazy(4);

        expect($lazy)->toBeInstanceOf(LazyCollection::class);

        $count = 0;

        foreach ($lazy as $model) {
            expect($model)->toBeInstanceOf(Post::class);
            $count++;
        }

        expect($count)->toBe(10);
    });

    it('streams every row lazily by id', function (): void {
        expect($this->repo->lazyById(4)->count())->toBe(10);
    });
});

describe('cursor', function (): void {
    it('returns a lazy collection over the results', function (): void {
        $cursor = $this->repo->cursor();

        expect($cursor)->toBeInstanceOf(LazyCollection::class)
            ->and($cursor->count())->toBe(10);
    });
});
