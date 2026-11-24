<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use RobertGDev\BaseRepository\BaseRepository;

/**
 * @extends BaseRepository<Archive>
 */
class ArchiveRepository extends BaseRepository
{
    public function model(): string
    {
        return Archive::class;
    }
}
