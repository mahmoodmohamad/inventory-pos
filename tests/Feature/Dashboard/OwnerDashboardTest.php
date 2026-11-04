<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopDashboardService;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
});

function dashboardUser(Shop $shop, UserRole $role): User
{
    return User::factory()->for($shop)->create(['role' => $role]);
}

it('shows inventory value to the owner', function () {
    Product::factory()->for($this->shop)->withStock(10, 50)->create(['retail_price' => 80]);

    $this->actingAs(dashboardUser($this->shop, UserRole::Owner))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('قيمة المخزون (بالتكلفة)')
        ->assertSee('500.00')   // 10 * 50
        ->assertSee('800.00');  // 10 * 80
});

it('hides financials from the cashier but still opens the page', function () {
    Product::factory()->for($this->shop)->withStock(10, 50)->create();

    $this->actingAs(dashboardUser($this->shop, UserRole::Cashier))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('قيمة المخزون')
        ->assertDontSee('500.00');
});

it('never leaks another shop data into the dashboard', function () {
    Product::factory()->for($this->other)->withStock(1, 10)
        ->create(['name' => 'Secret Part B', 'min_stock_alert' => 5]);

    Product::factory()->for($this->shop)->withStock(1, 10)
        ->create(['name' => 'My Low Part', 'min_stock_alert' => 5]);

    $this->actingAs(dashboardUser($this->shop, UserRole::Owner))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Low Part')
        ->assertDontSee('Secret Part B');
});

it('only counts team members of the same shop', function () {
    dashboardUser($this->shop, UserRole::Manager);
    dashboardUser($this->other, UserRole::Manager);
    dashboardUser($this->other, UserRole::Manager);

    $owner = dashboardUser($this->shop, UserRole::Owner);

    $summary = app(ShopDashboardService::class);

    app(TenantContext::class)->set($this->shop);

    expect($summary->summary($owner)['team']['manager'])->toBe(1);
});
