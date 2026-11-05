<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    app(TenantContext::class)->set($this->shop);
    $this->owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
});

function validationForm($component, array $overrides = [])
{
    foreach (array_merge([
        'name' => 'Pad', 'condition' => 'original', 'unit' => 'piece',
        'retail_price' => '100', 'wholesale_price' => '90', 'min_stock_alert' => '0',
    ], $overrides) as $key => $value) {
        $component->set("form.{$key}", $value);
    }

    return $component;
}

it('rejects a wholesale price that only exceeds retail after rounding', function () {
    $c = Livewire::actingAs($this->owner)->test('pages::products.index');

    validationForm($c, ['retail_price' => '100.00', 'wholesale_price' => '100.009'])
        ->call('save')->assertHasErrors(['form.wholesale_price']);

    expect(Product::count())->toBe(0);
});

it('rejects scientific notation as a validation error instead of a server error', function () {
    $c = Livewire::actingAs($this->owner)->test('pages::products.index');

    validationForm($c, ['retail_price' => '1e2'])
        ->call('save')->assertHasErrors(['form.retail_price']);
});

it('rejects over-precise quantities and costs', function () {
    $c = Livewire::actingAs($this->owner)->test('pages::products.index');

    validationForm($c, ['opening_qty' => '0.0004', 'opening_cost' => '10'])
        ->call('save')->assertHasErrors(['form.opening_qty']);

    validationForm($c, ['opening_qty' => '1', 'opening_cost' => '10.12345'])
        ->call('save')->assertHasErrors(['form.opening_cost']);
});

it('rejects an over-precise stock adjustment', function () {
    $product = Product::factory()->for($this->shop)->withStock(10)->create();

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('openAdjust', $product->id)
        ->set('adjustQty', '0.0004')->set('adjustNotes', 'x')
        ->call('saveAdjust')->assertHasErrors(['adjustQty']);

    expect($product->fresh()->stock_qty)->toBe('10.000');
});
