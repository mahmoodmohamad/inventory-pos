<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class TenantViolationException extends RuntimeException
{
    public static function for(string $reason, Model $model): self
    {
        return new self(sprintf('Tenant violation on %s: %s', $model::class, $reason));
    }
}
