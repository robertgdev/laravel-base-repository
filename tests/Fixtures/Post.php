<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Plain (non soft-deleting) fixture model.
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'name',
        'email',
        'status',
        'active',
        'views',
    ];

    protected $casts = [
        'active' => 'boolean',
        'views' => 'integer',
    ];
}
