<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\MissingTenantException;
use App\Exceptions\TenantViolationException;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function sup_shop(UserRole $role = UserRole::Owner): array
{
    $shop = Shop::factory()->create();
    $user = User::factory()->create(['shop_id' => $shop->id, 'role' => $role]);
    app(TenantContext::class)->set($shop);

    return [$shop, $user];
}

it('assigns shop_id from the tenant context', function () {
    [$shop] = sup_shop();

    $supplier = Supplier::query()->create(['name' => 'Al Nour Parts']);

    expect($supplier->shop_id)->toBe($shop->id);
});

it('fails closed without a tenant context', function () {
    app(TenantContext::class)->forget();

    expect(fn () => Supplier::query()->count())->toThrow(MissingTenantException::class);
});

it('hides other shops suppliers from queries', function () {
    [$shopA] = sup_shop();
    $shopB = Shop::factory()->create();

    $foreign = app(TenantContext::class)->run($shopB, fn () => Supplier::factory()->create(['shop_id' => $shopB->id]));

    expect(Supplier::query()->find($foreign->id))->toBeNull()
        ->and(Supplier::query()->count())->toBe(0);
});

it('rejects cross-tenant create and shop_id changes', function () {
    [$shopA] = sup_shop();
    $shopB = Shop::factory()->create();

    expect(fn () => Supplier::factory()->create(['shop_id' => $shopB->id]))
        ->toThrow(TenantViolationException::class);

    $supplier = Supplier::factory()->create(['shop_id' => $shopA->id]);
    $supplier->shop_id = $shopB->id;

    expect(fn () => $supplier->save())->toThrow(TenantViolationException::class);
});

it('soft deletes suppliers', function () {
    [$shop] = sup_shop();
    $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);

    $supplier->delete();

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and(Supplier::query()->withTrashed()->find($supplier->id))->not->toBeNull();
});

it('limits supplier access to owners and managers', function () {
    [$shop, $owner] = sup_shop();
    $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
    $manager = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Manager]);
    $cashier = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Cashier]);

    foreach ([$owner, $manager] as $user) {
        expect(Gate::forUser($user)->allows('viewAny', Supplier::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('create', Supplier::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $supplier))->toBeTrue();
    }

    expect(Gate::forUser($cashier)->allows('viewAny', Supplier::class))->toBeFalse()
        ->and(Gate::forUser($cashier)->allows('create', Supplier::class))->toBeFalse()
        ->and(Gate::forUser($cashier)->allows('view', $supplier))->toBeFalse();
});

it('returns 404 for another shops supplier and denies inactive users', function () {
    [$shopA, $owner] = sup_shop();
    $supplier = Supplier::factory()->create(['shop_id' => $shopA->id]);

    $otherOwner = User::factory()->create(['role' => UserRole::Owner]); // محل تاني
    expect(Gate::forUser($otherOwner)->inspect('view', $supplier)->status())->toBe(404);

    $owner->update(['is_active' => false]);
    expect(Gate::forUser($owner->refresh())->allows('viewAny', Supplier::class))->toBeFalse();
});
