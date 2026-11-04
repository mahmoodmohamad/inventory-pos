<?php

use App\Enums\PriceType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->set($this->shop);

    $this->owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
    $this->cashier = User::factory()->for($this->shop)->create(['role' => UserRole::Cashier]);
    $this->product = Product::factory()->for($this->shop)->withStock(5)
        ->create(['barcode' => '6220000000099', 'retail_price' => '100.00']);
});

it('redirects guests to login', function () {
    $this->get(route('sales.index'))->assertRedirect(route('login'));
});

it('lists only the sales of the current shop', function () {
    $mine = Customer::factory()->for($this->shop)->create(['name' => 'Mine Customer']);
    Sale::factory()->for($this->shop)->create(['customer_id' => $mine->id]);

    $this->tenant->run($this->other, function () {
        $theirs = Customer::factory()->for($this->other)->create(['name' => 'Foreign Customer']);
        Sale::factory()->for($this->other)->create(['customer_id' => $theirs->id]);
    });

    $this->actingAs($this->cashier)->get(route('sales.index'))
        ->assertOk()
        ->assertSee('Mine Customer')
        ->assertDontSee('Foreign Customer');
});

it('lets a cashier open a draft from the page', function () {
    Livewire::actingAs($this->cashier)->test('pages::sales.index')
        ->set('priceType', 'wholesale')
        ->call('createDraft')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Sale::query()->firstOrFail()->price_type)->toBe(PriceType::Wholesale);
});

it('returns 404 for a sale of another shop', function () {
    $foreign = $this->tenant->run($this->other, fn () => Sale::factory()->for($this->other)->create());

    $this->actingAs($this->owner)->get(route('sales.show', ['id' => $foreign->id]))->assertNotFound();
});

it('adds an item by scanning its barcode', function () {
    $sale = app(SaleService::class)->createDraft($this->cashier, null, PriceType::Retail);

    Livewire::actingAs($this->cashier)->test('pages::sales.show', ['id' => $sale->id])
        ->set('search', '6220000000099')
        ->call('addFromSearch');

    expect($sale->items()->count())->toBe(1)
        ->and($sale->refresh()->total_amount)->toBe('100.00');
});

it('forbids a cashier from overriding a price through the page', function () {
    $service = app(SaleService::class);
    $sale = $service->createDraft($this->cashier, null, PriceType::Retail);
    $item = $service->addItem($this->cashier, $sale, $this->product->id);

    Livewire::actingAs($this->cashier)->test('pages::sales.show', ['id' => $sale->id])
        ->call('setPrice', $item->id, '1.00')
        ->assertForbidden();

    expect($item->fresh()->unit_price)->toBe('100.00');
});

it('hides the product search on a completed sale', function () {
    $sale = Sale::factory()->for($this->shop)->completed()->create();

    $this->actingAs($this->cashier)->get(route('sales.show', ['id' => $sale->id]))
        ->assertOk()
        ->assertDontSee('امسح الباركود');
});
