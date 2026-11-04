<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\BarcodeGenerator;
use App\Services\ProductBulkService;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->service = app(ProductBulkService::class);
});

it('skips products of another shop', function () {
    $owner = User::factory()->for($this->shopA)->create(['role' => UserRole::Owner]);
    $mine = Product::factory()->for($this->shopA)->create();
    $foreign = Product::factory()->for($this->shopB)->create();

    $this->tenant->set($this->shopA);

    $result = $this->service->apply($owner, 'deactivate', [$mine->id, $foreign->id]);

    expect($result)->toBe(['done' => 1, 'skipped' => 1])
        ->and(Product::withoutGlobalScopes()->find($foreign->id)->is_active)->toBeTrue();
});

it('does not delete products with stock', function () {
    $owner = User::factory()->for($this->shopA)->create(['role' => UserRole::Owner]);
    $withStock = Product::factory()->for($this->shopA)->withStock(5)->create();
    $empty = Product::factory()->for($this->shopA)->create();

    $this->tenant->set($this->shopA);

    $result = $this->service->apply($owner, 'delete', [$withStock->id, $empty->id]);

    expect($result)->toBe(['done' => 1, 'skipped' => 1])
        ->and(Product::find($withStock->id))->not->toBeNull()
        ->and(Product::find($empty->id))->toBeNull();
});

it('blocks a cashier from bulk actions', function () {
    $cashier = User::factory()->for($this->shopA)->create(['role' => UserRole::Cashier]);
    $product = Product::factory()->for($this->shopA)->create();

    $this->tenant->set($this->shopA);

    expect($this->service->apply($cashier, 'deactivate', [$product->id]))
        ->toBe(['done' => 0, 'skipped' => 1]);
});

it('generates a valid unique internal EAN-13', function () {
    $this->tenant->set($this->shopA);
    $gen = app(BarcodeGenerator::class);

    $code = $gen->internalEan13();

    expect($gen->isValidEan13($code))->toBeTrue()
        ->and($code)->toStartWith('200');
});
