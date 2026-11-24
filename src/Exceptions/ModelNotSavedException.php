<?php

declare(strict_types=1);

namespace RobertGDev\BaseRepository\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when Eloquent refused to persist a model — typically because a
 * "creating", "saving" or "updating" model event returned false, or because
 * a strict-mode attribute guard aborted the write.
 *
 * Repository write methods never report success for a write that did not
 * happen; they throw this instead.
 */
class ModelNotSavedException extends RuntimeException
{
    public static function for(Model $model, string $operation): self
    {
        return new self(sprintf(
            'Unable to %s [%s]: the write was cancelled (model event returned false, or a strict-mode guard aborted it).',
            $operation,
            $model::class,
        ));
    }
}
