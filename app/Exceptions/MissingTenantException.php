<?php

namespace App\Exceptions;

use RuntimeException;

class MissingTenantException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tenant (shop) context is set. Refusing to run a tenant-scoped operation.');
    }
}
