<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use RobertGDev\BaseRepository\BaseRepository;

/**
 * @extends BaseRepository<Article>
 */
class ArticleRepository extends BaseRepository
{
    public function model(): string
    {
        return Article::class;
    }
}
