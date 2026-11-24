<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soft-deleting fixture with a custom deleted-at column.
 */
class Archive extends Model
{
    use SoftDeletes;

    public const DELETED_AT = 'removed_at';

    protected $table = 'archives';

    protected $fillable = [
        'name',
    ];
}
