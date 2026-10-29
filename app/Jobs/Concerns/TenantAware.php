<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RestoreTenant;
use App\Support\Tenancy\TenantContext;

trait TenantAware
{
    public int $shopId;

    public function initializeTenantAware(): void
    {
        $this->shopId = app(TenantContext::class)->idOrFail();
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RestoreTenant];
    }
}
