<?php

use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\Shop;
use App\Models\User;
use App\Models\VehicleVariant;

function storefrontShop(array $overrides = []): Shop
{
    return Shop::factory()->create([
        'settings' => ['storefront_enabled' => true],
        ...$overrides,
    ]);
}

function storefrontUrl(Shop $shop, array $query = []): string
{
    return route('storefront.index', ['shopSlug' => $shop->slug, ...$query]);
}

it('returns 404 for an unknown slug', function () {
    $this->get('/s/does-not-exist')->assertNotFound();
});

it('returns 404 when the storefront is disabled', function () {
    $shop = storefrontShop(['settings' => ['storefront_enabled' => false]]);

    $this->get(storefrontUrl($shop))->assertNotFound();
});

it('returns 404 for an inactive shop', function () {
    $shop = storefrontShop(['is_active' => false]);

    $this->get(storefrontUrl($shop))->assertNotFound();
});

it('shows only the products of the requested shop', function () {
    $a = storefrontShop();
    $b = storefrontShop();
    Product::factory()->for($a)->create(['name' => 'Alpha Pads']);
    Product::factory()->for($b)->create(['name' => 'Bravo Filter']);

    $this->get(storefrontUrl($a))->assertOk()->assertSee('Alpha Pads')->assertDontSee('Bravo Filter');
    $this->get(storefrontUrl($b))->assertOk()->assertSee('Bravo Filter')->assertDontSee('Alpha Pads');
});

it('uses the URL shop even when another shop owner is logged in', function () {
    $a = storefrontShop();
    $b = storefrontShop();
    Product::factory()->for($a)->create(['name' => 'Alpha Pads']);
    Product::factory()->for($b)->create(['name' => 'Bravo Filter']);
    $ownerA = User::factory()->for($a)->create();

    $this->actingAs($ownerA)->get(storefrontUrl($b))
        ->assertOk()
        ->assertSee('Bravo Filter')
        ->assertDontSee('Alpha Pads');
});

it('hides inactive products', function () {
    $shop = storefrontShop();
    Product::factory()->for($shop)->create(['name' => 'Visible Part']);
    Product::factory()->for($shop)->create(['name' => 'Hidden Part', 'is_active' => false]);

    $this->get(storefrontUrl($shop))->assertSee('Visible Part')->assertDontSee('Hidden Part');
});

it('never exposes wholesale price, cost or exact stock', function () {
    $shop = storefrontShop();
    Product::factory()->for($shop)->withStock(4321, 987.65)->create([
        'name' => 'Secret Part',
        'retail_price' => 250.50,
        'wholesale_price' => 111.11,
    ]);

    $this->get(storefrontUrl($shop))
        ->assertOk()
        ->assertSee('250.50')
        ->assertDontSee('111.11')
        ->assertDontSee('987.65')
        ->assertDontSee('4321');
});

it('finds a product by part number', function () {
    $shop = storefrontShop();
    Product::factory()->for($shop)->create(['name' => 'Brake Pads', 'part_number' => '04465-02220']);
    Product::factory()->for($shop)->create(['name' => 'Oil Filter', 'part_number' => 'W67/1']);

    $this->get(storefrontUrl($shop, ['q' => '04465']))
        ->assertSee('Brake Pads')
        ->assertDontSee('Oil Filter');
});

it('filters by vehicle compatibility', function () {
    $shop = storefrontShop();
    $variant = VehicleVariant::factory()->create();
    $otherVariant = VehicleVariant::factory()->create();

    $fits = Product::factory()->for($shop)->create(['name' => 'Fits Pads']);
    $noFit = Product::factory()->for($shop)->create(['name' => 'Other Pads']);

    ProductFitment::factory()->create(['product_id' => $fits->id, 'vehicle_variant_id' => $variant->id]);
    ProductFitment::factory()->create(['product_id' => $noFit->id, 'vehicle_variant_id' => $otherVariant->id]);

    $this->get(storefrontUrl($shop, ['variant' => $variant->id]))
        ->assertSee('Fits Pads')
        ->assertDontSee('Other Pads');
});

it('rejects an invalid condition value', function () {
    $shop = storefrontShop();

    $this->get(storefrontUrl($shop, ['condition' => 'hacked']))->assertSessionHasErrors('condition');
});
