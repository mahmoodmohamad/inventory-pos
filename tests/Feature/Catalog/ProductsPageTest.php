<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VehicleVariant;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->set($this->shop);

    $this->owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
    $this->manager = User::factory()->for($this->shop)->create(['role' => UserRole::Manager]);
    $this->cashier = User::factory()->for($this->shop)->create(['role' => UserRole::Cashier]);
});

function fillProductForm($component, array $overrides = [])
{
    $values = array_merge([
        'name' => 'فحمات فرامل',
        'part_number' => '04465-02220',
        'condition' => 'original',
        'unit' => 'piece',
        'retail_price' => '850',
        'wholesale_price' => '720',
        'min_stock_alert' => '4',
    ], $overrides);

    foreach ($values as $key => $value) {
        $component->set("form.{$key}", $value);
    }

    return $component;
}

it('lets a cashier view the page but not create, edit or delete', function () {
    $product = Product::factory()->for($this->shop)->create();

    $this->actingAs($this->cashier)->get(route('products.index'))->assertOk();

    Livewire::actingAs($this->cashier)->test('pages::products.index')
        ->call('openCreate')->assertForbidden();

    Livewire::actingAs($this->cashier)->test('pages::products.index')
        ->call('openEdit', $product->id)->assertForbidden();

    Livewire::actingAs($this->cashier)->test('pages::products.index')
        ->call('delete', $product->id)->assertForbidden();
});

it('creates a product with an opening balance through the ledger', function () {
    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['opening_qty' => '10', 'opening_cost' => '500'])
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('part_number', '04465-02220')->firstOrFail();

    expect($product->shop_id)->toBe($this->shop->id)
        ->and((float) $product->stock_qty)->toBe(10.0)
        ->and((float) $product->avg_cost)->toBe(500.0)
        ->and(StockMovement::query()->where('product_id', $product->id)->count())->toBe(1);
});

it('requires a unit cost when an opening quantity is entered', function () {
    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['opening_qty' => '5'])
        ->call('save')
        ->assertHasErrors(['form.opening_cost']);

    expect(Product::count())->toBe(0);
});

it('rejects a duplicate barcode within the same shop', function () {
    Product::factory()->for($this->shop)->create(['barcode' => '123']);

    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['barcode' => '123'])
        ->call('save')
        ->assertHasErrors(['form.barcode']);
});

it('allows a barcode that only exists in another shop', function () {
    $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create(['barcode' => '123']));

    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['barcode' => '123'])
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects a category from another shop', function () {
    $foreign = $this->tenant->run($this->other, fn () => Category::factory()->for($this->other)->create());

    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['category_id' => (string) $foreign->id])
        ->call('save')
        ->assertHasErrors(['form.category_id']);
});

it('returns 404 when editing a product of another shop', function () {
    $foreign = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('openEdit', $foreign->id)
        ->assertNotFound();
});

it('searches by part number and never shows another shop products', function () {
    Product::factory()->for($this->shop)->create(['name' => 'Alpha Pad', 'part_number' => 'AB-1001']);
    Product::factory()->for($this->shop)->create(['name' => 'Beta Filter', 'part_number' => 'ZZ-2002']);
    $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create(['name' => 'Foreign Pad', 'part_number' => 'AB-1001']));

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('search', 'AB-10')
        ->assertSee('Alpha Pad')
        ->assertDontSee('Beta Filter')
        ->assertDontSee('Foreign Pad');
});

it('filters low stock products', function () {
    Product::factory()->for($this->shop)->withStock(1)->create(['name' => 'Low One', 'min_stock_alert' => 5]);
    Product::factory()->for($this->shop)->withStock(50)->create(['name' => 'Plenty One', 'min_stock_alert' => 5]);

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('stockFilter', 'low')
        ->assertSee('Low One')
        ->assertDontSee('Plenty One');
});

it('lets a manager adjust stock and forbids a cashier', function () {
    $product = Product::factory()->for($this->shop)->withStock(10)->create();

    Livewire::actingAs($this->manager)->test('pages::products.index')
        ->call('openAdjust', $product->id)
        ->set('adjustQty', '-2')
        ->set('adjustNotes', 'تالف')
        ->call('saveAdjust')
        ->assertHasNoErrors();

    expect((float) $product->fresh()->stock_qty)->toBe(8.0);

    Livewire::actingAs($this->cashier)->test('pages::products.index')
        ->call('openAdjust', $product->id)
        ->assertForbidden();
});

it('refuses to delete a product that still has stock', function () {
    $product = Product::factory()->for($this->shop)->withStock(3)->create();

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('delete', $product->id);

    expect(Product::find($product->id))->not->toBeNull();
});

it('deletes a product with zero stock (soft delete)', function () {
    $product = Product::factory()->for($this->shop)->create();

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('delete', $product->id);

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('adds and removes a vehicle fitment', function () {
    $product = Product::factory()->for($this->shop)->create();
    $variant = VehicleVariant::factory()->create();

    $component = Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('openFitments', $product->id)
        ->set('fitVariantId', (string) $variant->id)
        ->call('addFitment')
        ->assertHasNoErrors();

    expect($product->fitments()->count())->toBe(1);

    $fitmentId = $product->fitments()->value('id');
    $component->call('removeFitment', $fitmentId);

    expect($product->fitments()->count())->toBe(0);
});
it('filters out-of-stock products', function () {
    Product::factory()->for($this->shop)->create(['name' => 'Empty One']);
    Product::factory()->for($this->shop)->withStock(5)->create(['name' => 'Stocked One']);

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('stockFilter', 'out')
        ->assertSee('Empty One')
        ->assertDontSee('Stocked One');
});

it('rejects a wholesale price higher than retail', function () {
    $component = Livewire::actingAs($this->owner)->test('pages::products.index');

    fillProductForm($component, ['retail_price' => '100', 'wholesale_price' => '150'])
        ->call('save')
        ->assertHasErrors(['form.wholesale_price']);
});

it('bulk deactivates products and skips deleting those with stock', function () {
    $a = Product::factory()->for($this->shop)->create();
    $b = Product::factory()->for($this->shop)->withStock(3)->create();

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('selected', [(string) $a->id, (string) $b->id])
        ->call('bulk', 'deactivate');

    expect($a->fresh()->is_active)->toBeFalse()
        ->and($b->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('selected', [(string) $a->id, (string) $b->id])
        ->call('bulk', 'delete');

    expect(Product::find($a->id))->toBeNull()
        ->and(Product::find($b->id))->not->toBeNull();
});

it('never bulk-touches products of another shop', function () {
    $foreign = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());

    Livewire::actingAs($this->owner)->test('pages::products.index')
        ->set('selected', [(string) $foreign->id])
        ->call('bulk', 'deactivate');

    expect($this->tenant->run($this->other, fn () => Product::find($foreign->id)->is_active))->toBeTrue();
});

it('duplicates a product as another condition and copies fitments', function () {
    $source = Product::factory()->for($this->shop)->create(['part_number' => 'DUP-1', 'barcode' => 'B-1']);
    $variant = VehicleVariant::factory()->create();
    $source->fitments()->create(['vehicle_variant_id' => $variant->id]);

    $component = Livewire::actingAs($this->owner)->test('pages::products.index')
        ->call('openDuplicate', $source->id)
        ->assertSet('form.barcode', '')
        ->assertSet('form.copyFitments', true)
        ->set('form.condition', 'commercial')
        ->call('save')
        ->assertHasNoErrors();

    $copy = Product::query()->where('part_number', 'DUP-1')->where('condition', 'commercial')->firstOrFail();

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->fitments()->count())->toBe(1);
});
