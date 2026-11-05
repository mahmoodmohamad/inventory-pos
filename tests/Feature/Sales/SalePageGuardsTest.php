<?php

use App\Enums\PriceType;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;

it('hides checkout and customer controls until the backend supports them', function () {
    $shop = Shop::factory()->create();
    app(TenantContext::class)->set($shop);
    $cashier = User::factory()->for($shop)->create(['role' => UserRole::Cashier]);
    $sale = app(SaleService::class)->createDraft($cashier, null, PriceType::Retail);

    $this->actingAs($cashier)->get(route('sales.show', ['id' => $sale->id]))
        ->assertOk()
        ->assertDontSee('إكمال الفاتورة')
        ->assertDontSee('شريحة السعر');
})->skip(fn () => method_exists(SaleService::class, 'complete'), 'Checkout implemented (step 5.3).');
