<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use RobertGDev\BaseRepository\Tests\Fixtures\InjectedPostRepository;
use RobertGDev\BaseRepository\Tests\Fixtures\Post;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;

beforeEach(function (): void {
    makePostsTable();
});

describe('model resolution', function (): void {
    it('resolves a repository with no constructor from the container', function (): void {
        $repo = app(PostRepository::class);

        expect($repo)->toBeInstanceOf(PostRepository::class)
            ->and($repo->getModel())->toBeInstanceOf(Post::class)
            ->and($repo->count())->toBe(0);
    });

    it('derives the model from model(), not from a second declaration', function (): void {
        $repo = new PostRepository;

        expect($repo->getModel()::class)->toBe($repo->model());
    });

    it('still supports constructor injection', function (): void {
        $repo = new InjectedPostRepository(new Post);

        expect($repo->getModel())->toBeInstanceOf(Post::class);
        expect($repo->create(['name' => 'A'])->exists)->toBeTrue();
    });

    it('resolves an injecting repository from the container', function (): void {
        $repo = app(InjectedPostRepository::class);

        expect($repo->getModel())->toBeInstanceOf(Post::class)
            ->and($repo->count())->toBe(0);
    });

    it('keeps working when a model instance is passed directly', function (): void {
        $model = new Post;
        $model->setTable('posts');

        $repo = new PostRepository($model);

        expect($repo->getModel())->toBe($model);
    });
});
