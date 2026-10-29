<?php

namespace App\Jobs\Middleware;

use App\Exceptions\MissingTenantException;
use App\Support\Tenancy\TenantContext;
use Closure;

class RestoreTenant
{
    public function handle(object $job, Closure $next): mixed
    {
        $shopId = $job->shopId ?? throw new MissingTenantException;

        return app(TenantContext::class)->run($shopId, fn () => $next($job));
    }
}
