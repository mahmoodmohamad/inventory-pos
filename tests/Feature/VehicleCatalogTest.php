<?php

use App\Models\Shop;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Database\QueryException;

it('builds valid makes and models from factories', function () {
    $model = VehicleModel::factory()->create();

    expect($model->make)->toBeInstanceOf(VehicleMake::class)
        ->and($model->is_active)->toBeTrue()
        ->and(VehicleModel::factory()->inactive()->make()->is_active)->toBeFalse();
});

it('links a make to its models', function () {
    $make = VehicleMake::factory()->create();
    VehicleModel::factory()->count(3)->for($make, 'make')->create();

    expect($make->models)->toHaveCount(3)
        ->and($make->models->first()->make->is($make))->toBeTrue();
});

it('allows the same model slug under different makes', function () {
    VehicleModel::factory()->for(VehicleMake::factory(), 'make')->create(['slug' => 'sedan']);
    VehicleModel::factory()->for(VehicleMake::factory(), 'make')->create(['slug' => 'sedan']);

    expect(VehicleModel::where('slug', 'sedan')->count())->toBe(2);
});

it('rejects a duplicate model slug under the same make', function () {
    $make = VehicleMake::factory()->create();

    VehicleModel::factory()->for($make, 'make')->create(['slug' => 'corolla']);
    VehicleModel::factory()->for($make, 'make')->create(['slug' => 'corolla']);
})->throws(QueryException::class);

it('restricts deleting a make that still has models', function () {
    $make = VehicleMake::factory()->create();
    VehicleModel::factory()->for($make, 'make')->create();

    expect(fn () => $make->delete())->toThrow(QueryException::class);
});

it('is shared catalog data and ignores the tenant context', function () {
    VehicleModel::factory()->count(2)->create();

    // من غير سياق محل: مفيش MissingTenantException
    expect(VehicleModel::count())->toBe(2);

    $shop = Shop::factory()->create();

    expect(app(TenantContext::class)->run($shop, fn () => VehicleModel::count()))->toBe(2);
});

it('seeds the catalog idempotently', function () {
    $this->seed(VehicleCatalogSeeder::class);

    $makes = VehicleMake::count();
    $models = VehicleModel::count();

    $this->seed(VehicleCatalogSeeder::class);

    expect($makes)->toBeGreaterThan(0)
        ->and(VehicleMake::count())->toBe($makes)
        ->and(VehicleModel::count())->toBe($models)
        ->and(VehicleMake::where('slug', 'toyota')->first()->models->pluck('slug'))->toContain('corolla');
});
