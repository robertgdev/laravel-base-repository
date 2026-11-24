<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Shares the posts table but can veto writes from a model event, so the
 * "refused write" behaviour can be tested without leaking listeners into
 * other tests.
 */
class VetoablePost extends Model
{
    public static bool $vetoSave = false;

    public static bool $vetoDelete = false;

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

    protected static function booted(): void
    {
        static::saving(fn (): bool => ! static::$vetoSave);
        static::deleting(fn (): bool => ! static::$vetoDelete);
    }
}
