<?php

use App\Exceptions\MissingTenantException;
use App\Models\Category;
use App\Models\Shop;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\TenantProbeJob;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);

    Category::factory()->for($this->shopA)->count(2)->create();
    Category::factory()->for($this->shopB)->count(5)->create();
    TenantProbeJob::$seenCount = null;
});

it('refuses to dispatch a tenant job without tenant context', function () {
    new TenantProbeJob;
})->throws(MissingTenantException::class);

it('runs the job inside the shop it was dispatched from', function () {
    $job = $this->tenant->run($this->shopA, fn () => new TenantProbeJob);

    // نفضّي السياق عشان نحاكي الـ worker (مفيش مستخدم ولا request)
    $this->tenant->forget();

    dispatch_sync($job);

    expect(TenantProbeJob::$seenCount)->toBe(2);
});

it('does not leak the tenant context after the job finishes', function () {
    $job = $this->tenant->run($this->shopB, fn () => new TenantProbeJob);

    $this->tenant->forget();
    dispatch_sync($job);

    expect($this->tenant->hasShop())->toBeFalse();
});

it('keeps each shop job isolated when run back to back', function () {
    $jobA = $this->tenant->run($this->shopA, fn () => new TenantProbeJob);
    $jobB = $this->tenant->run($this->shopB, fn () => new TenantProbeJob);

    dispatch_sync($jobA);
    expect(TenantProbeJob::$seenCount)->toBe(2);

    dispatch_sync($jobB);
    expect(TenantProbeJob::$seenCount)->toBe(5);
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
