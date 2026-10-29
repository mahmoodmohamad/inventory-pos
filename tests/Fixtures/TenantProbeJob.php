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

    public static ?int $seenShopId = null;

    public function __construct()
    {
        $this->initializeTenantAware();
    }

    public function handle(): void
    {

        self::$seenShopId = app(TenantContext::class)->id();
        self::$seenCount = Category::count();
    }
}
