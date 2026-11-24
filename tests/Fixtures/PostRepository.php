<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use RobertGDev\BaseRepository\BaseRepository;

/**
 * No constructor: the base class resolves the model from model().
 *
 * @extends BaseRepository<Post>
 */
class PostRepository extends BaseRepository
{
    public function model(): string
    {
        return Post::class;
    }
}
