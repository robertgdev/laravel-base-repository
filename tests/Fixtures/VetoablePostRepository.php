<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use RobertGDev\BaseRepository\BaseRepository;

/**
 * @extends BaseRepository<VetoablePost>
 */
class VetoablePostRepository extends BaseRepository
{
    public function model(): string
    {
        return VetoablePost::class;
    }
}
