<?php

use App\Exceptions\MissingTenantException;
use App\Models\Category;
use App\Models\Shop;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\TenantProbeJob;

beforeEach(function () {
    // connection=sync بيمرّر الـ job على نفس pipeline الـ worker (بما فيها الـ job middleware)
    // عكس dispatch_sync() اللي بيستدعي handle() مباشرة من غير middleware
    config(['queue.default' => 'sync']);

    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);

    Category::factory()->for($this->shopA)->count(2)->create();
    Category::factory()->for($this->shopB)->count(5)->create();

    TenantProbeJob::$seenCount = null;
    TenantProbeJob::$seenShopId = null;
});

it('refuses to dispatch a tenant job without tenant context', function () {
    new TenantProbeJob;
})->throws(MissingTenantException::class);

it('runs the job inside the shop it was dispatched from', function () {
    $this->tenant->set($this->shopA);

    TenantProbeJob::dispatch();

    expect(TenantProbeJob::$seenShopId)->toBe($this->shopA->id)
        ->and(TenantProbeJob::$seenCount)->toBe(2);
});

it('restores the previous context after the job finishes', function () {
    $this->tenant->set($this->shopB);

    TenantProbeJob::dispatch();

    expect($this->tenant->id())->toBe($this->shopB->id);
});

it('keeps each shop job isolated when run back to back', function () {
    $this->tenant->set($this->shopA);
    TenantProbeJob::dispatch();
    expect(TenantProbeJob::$seenCount)->toBe(2);

    $this->tenant->set($this->shopB);
    TenantProbeJob::dispatch();
    expect(TenantProbeJob::$seenCount)->toBe(5);
});

it('restores the shop from the payload when a real worker has no context', function () {
    config(['queue.default' => 'database']);

    $this->tenant->set($this->shopB);
    TenantProbeJob::dispatch();

    // محاكاة الـ worker: مفيش مستخدم ولا request ولا سياق
    $this->tenant->forget();
    app()->forgetScopedInstances();

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);

    expect(TenantProbeJob::$seenShopId)->toBe($this->shopB->id)
        ->and(TenantProbeJob::$seenCount)->toBe(5)
        ->and(app(TenantContext::class)->hasShop())->toBeFalse();
});

it('namespaces cache keys by shop', function () {
    $cache = app(TenantCache::class);

    $this->tenant->run($this->shopA, fn () => $cache->remember('stats', 60, fn () => 'A-data'));
    $this->tenant->run($this->shopB, fn () => $cache->remember('stats', 60, fn () => 'B-data'));

    expect($this->tenant->run($this->shopA, fn () => $cache->remember('stats', 60, fn () => 'x')))->toBe('A-data')
        ->and($this->tenant->run($this->shopB, fn () => $cache->remember('stats', 60, fn () => 'x')))->toBe('B-data')
        ->and(Cache::has('stats'))->toBeFalse();
});

it('refuses to build a cache key without tenant context', function () {
    app(TenantCache::class)->key('stats');
})->throws(MissingTenantException::class);
