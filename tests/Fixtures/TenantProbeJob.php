<?php

namespace Tests\Fixtures;

use App\Jobs\Concerns\TenantAware;
use App\Models\Category;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TenantProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels, TenantAware;

    public static ?int $seenCount = null;

    public function __construct()
    {
        $this->initializeTenantAware();
    }

    public function handle(): void
    {
        // حل مؤقت: نحط الـ shopId بشكل صريح قدام أي query
        app(TenantContext::class)->run($this->shopId, function () {
            self::$seenCount = Category::count();
        });
    }
}
