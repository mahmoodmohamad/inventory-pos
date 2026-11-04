<?php

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
});

function ownerPagesUser(Shop $shop, UserRole $role): User
{
    return User::factory()->for($shop)->create(['role' => $role]);
}

// ---------- Brands ----------

it('lists only brands of the current shop', function () {
    Brand::factory()->for($this->shop)->create(['name' => 'Bosch Mine']);
    Brand::factory()->for($this->other)->create(['name' => 'Denso Secret']);

    $this->actingAs(ownerPagesUser($this->shop, UserRole::Owner))
        ->get(route('brands.index'))
        ->assertOk()
        ->assertSee('Bosch Mine')
        ->assertDontSee('Denso Secret');
});

it('lets an owner create a brand in their own shop', function () {
    $owner = ownerPagesUser($this->shop, UserRole::Owner);
    $this->tenant->set($this->shop);

    Livewire::actingAs($owner)->test('pages::brands.index')
        ->call('openCreate')
        ->set('name', 'Valeo')
        ->call('save')
        ->assertHasNoErrors();

    expect(Brand::withoutGlobalScopes()->where('name', 'Valeo')->firstOrFail()->shop_id)
        ->toBe($this->shop->id);
});

it('forbids a cashier from creating a brand', function () {
    $cashier = ownerPagesUser($this->shop, UserRole::Cashier);
    $this->tenant->set($this->shop);

    Livewire::actingAs($cashier)->test('pages::brands.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('rejects a duplicate brand name inside the same shop only', function () {
    Brand::factory()->for($this->shop)->create(['name' => 'Sachs']);
    Brand::factory()->for($this->other)->create(['name' => 'TRW']);

    $owner = ownerPagesUser($this->shop, UserRole::Owner);
    $this->tenant->set($this->shop);

    $page = Livewire::actingAs($owner)->test('pages::brands.index');

    $page->call('openCreate')->set('name', 'Sachs')->call('save')->assertHasErrors(['name']);
    $page->set('name', 'TRW')->call('save')->assertHasNoErrors();
});

it('refuses to delete a brand that still has products, even soft-deleted ones', function () {
    $brand = Brand::factory()->for($this->shop)->create();
    $product = Product::factory()->for($this->shop)->create(['brand_id' => $brand->id]);

    $owner = ownerPagesUser($this->shop, UserRole::Owner);
    $this->tenant->set($this->shop);

    $product->delete();

    Livewire::actingAs($owner)->test('pages::brands.index')->call('delete', $brand->id);

    expect(Brand::withoutGlobalScopes()->whereKey($brand->id)->exists())->toBeTrue();
});

it('returns 404 when deleting a brand of another shop', function () {
    $foreign = Brand::factory()->for($this->other)->create();

    $owner = ownerPagesUser($this->shop, UserRole::Owner);
    $this->tenant->set($this->shop);

    Livewire::actingAs($owner)->test('pages::brands.index')
        ->call('delete', $foreign->id)
        ->assertNotFound();

    expect(Brand::withoutGlobalScopes()->whereKey($foreign->id)->exists())->toBeTrue();
});

// ---------- Stock movements ----------

it('hides the stock ledger from the cashier', function () {
    $this->actingAs(ownerPagesUser($this->shop, UserRole::Cashier))
        ->get(route('stock-movements.index'))
        ->assertForbidden();
});

it('shows only movements of the current shop', function () {
    $mine = Product::factory()->for($this->shop)->create(['name' => 'Alpha Part']);
    $theirs = Product::factory()->for($this->other)->create(['name' => 'Beta Part']);

    $this->tenant->run($this->shop, fn () => app(StockService::class)->openingBalance($mine, 5, 10));
    $this->tenant->run($this->other, fn () => app(StockService::class)->openingBalance($theirs, 5, 10));

    $this->actingAs(ownerPagesUser($this->shop, UserRole::Manager))
        ->get(route('stock-movements.index'))
        ->assertOk()
        ->assertSee('Alpha Part')
        ->assertDontSee('Beta Part');
});

// ---------- Shop settings ----------

it('lets only the owner open shop settings', function () {
    $this->actingAs(ownerPagesUser($this->shop, UserRole::Manager))
        ->get(route('shop.edit'))
        ->assertForbidden();

    $this->actingAs(ownerPagesUser($this->shop, UserRole::Owner))
        ->get(route('shop.edit'))
        ->assertOk();
});

it('updates the shop and keeps unrelated settings', function () {
    $this->shop->update(['settings' => ['allow_negative_stock' => true, 'foo' => 'bar']]);
    $otherName = $this->other->name;

    Livewire::actingAs(ownerPagesUser($this->shop, UserRole::Owner))
        ->test('pages::settings.shop')
        ->set('name', 'محل النور')
        ->set('currency', 'SAR')
        ->set('allow_negative_stock', false)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $this->shop->fresh();

    expect($fresh->name)->toBe('محل النور')
        ->and($fresh->currency)->toBe('SAR')
        ->and($fresh->setting('allow_negative_stock'))->toBeFalse()
        ->and($fresh->setting('foo'))->toBe('bar')
        ->and($this->other->fresh()->name)->toBe($otherName);
});

it('rejects an unsupported currency', function () {
    Livewire::actingAs(ownerPagesUser($this->shop, UserRole::Owner))
        ->test('pages::settings.shop')
        ->set('currency', 'XXX')
        ->call('save')
        ->assertHasErrors(['currency']);
});
