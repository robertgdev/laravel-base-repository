<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests;

use Illuminate\Support\Collection as SupportCollection;
use RobertGDev\BaseRepository\Tests\Fixtures\PostRepository;

beforeEach(function (): void {
    makePostsTable();

    $this->repo = new PostRepository;
});

describe('pluck', function (): void {
    it('returns a support collection of values', function (): void {
        $this->repo->create(['name' => 'A']);
        $this->repo->create(['name' => 'B']);

        $names = $this->repo->pluck('name');

        expect($names)->toBeInstanceOf(SupportCollection::class)
            ->and($names->all())->toBe(['A', 'B']);
    });

    it('supports a key column', function (): void {
        $created = $this->repo->create(['name' => 'A']);

        expect($this->repo->pluck('name', 'id')->all())->toBe([$created->id => 'A']);
    });

    it('returns an empty collection when empty', function (): void {
        expect($this->repo->pluck('name'))->toHaveCount(0);
    });
});

describe('value', function (): void {
    it('returns the first matching value', function (): void {
        $this->repo->create(['name' => 'A', 'views' => 7]);

        expect($this->repo->value('views'))->toBe(7);
    });

    it('returns null when empty', function (): void {
        expect($this->repo->value('views'))->toBeNull();
    });
});

describe('aggregates', function (): void {
    beforeEach(function (): void {
        $this->repo->create(['name' => 'A', 'views' => 10]);
        $this->repo->create(['name' => 'B', 'views' => 20]);
    });

    it('sums a column', function (): void {
        expect($this->repo->sum('views'))->toBe(30);
    });

    it('averages a column', function (): void {
        expect($this->repo->avg('views'))->toBe(15.0);
    });

    it('returns the maximum', function (): void {
        expect($this->repo->max('views'))->toBe(20);
    });

    it('returns the minimum', function (): void {
        expect($this->repo->min('views'))->toBe(10);
    });
});

describe('aggregates on an empty set', function (): void {
    it('sums to zero', function (): void {
        expect($this->repo->sum('views'))->toBe(0);
    });

    it('averages to null', function (): void {
        expect($this->repo->avg('views'))->toBeNull();
    });

    it('max and min are null', function (): void {
        expect($this->repo->max('views'))->toBeNull()
            ->and($this->repo->min('views'))->toBeNull();
    });
});
