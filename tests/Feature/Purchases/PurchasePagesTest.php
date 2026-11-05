<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pp_user(UserRole $role): User
{
    $shop = Shop::factory()->create();
    app(TenantContext::class)->set($shop);

    return User::factory()->create(['shop_id' => $shop->id, 'role' => $role]);
}

it('renders the supplier and purchase pages for owners', function () {
    $owner = pp_user(UserRole::Owner);
    Supplier::factory()->create(['shop_id' => $owner->shop_id]);

    foreach (['suppliers.index', 'purchases.index', 'purchases.create'] as $route) {
        $this->actingAs($owner)->get(route($route))->assertOk();
    }
});

it('forbids cashiers from supplier and purchase pages', function () {
    $cashier = pp_user(UserRole::Cashier);

    foreach (['suppliers.index', 'purchases.index', 'purchases.create'] as $route) {
        $this->actingAs($cashier)->get(route($route))->assertForbidden();
    }
});

it('returns 404 for another shops purchase', function () {
    $owner = pp_user(UserRole::Owner);

    $this->actingAs($owner)->get(route('purchases.show', ['id' => 999999]))->assertNotFound();
});
