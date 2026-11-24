<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use RobertGDev\BaseRepository\BaseRepository;

/**
 * The classic injection style: a constructor forwards the model to the
 * parent. Kept to prove the old wiring still works.
 *
 * @extends BaseRepository<Post>
 */
class InjectedPostRepository extends BaseRepository
{
    public function __construct(Post $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return Post::class;
    }
}
