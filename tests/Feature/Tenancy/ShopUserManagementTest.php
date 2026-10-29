<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->service = app(ShopUserService::class);

    $this->ownerA = User::factory()->for($this->shopA)->create(['role' => UserRole::Owner]);
    $this->managerA = User::factory()->for($this->shopA)->create(['role' => UserRole::Manager]);
    $this->cashierA = User::factory()->for($this->shopA)->create(['role' => UserRole::Cashier]);
    $this->ownerB = User::factory()->for($this->shopB)->create(['role' => UserRole::Owner]);
});

it('lists only users of the actor shop', function () {
    $ids = $this->service->listFor($this->ownerA)->pluck('id');

    expect($ids)->toContain($this->ownerA->id, $this->managerA->id, $this->cashierA->id)
        ->and($ids)->not->toContain($this->ownerB->id);
});

it('denies the cashier from listing users', function () {
    expect(fn () => $this->service->listFor($this->cashierA))->toThrow(AuthorizationException::class);
});

it('creates a user in the actor shop even if another shop is attempted', function () {
    $user = $this->service->create($this->ownerA, [
        'name' => 'New Cashier',
        'email' => 'new@example.com',
        'password' => 'password',
        'role' => UserRole::Cashier,
    ]);

    expect($user->shop_id)->toBe($this->shopA->id)
        ->and($user->role)->toBe(UserRole::Cashier)
        ->and($user->is_active)->toBeTrue();
});

it('never lets anyone create an owner from the management page', function () {
    expect(fn () => $this->service->create($this->ownerA, [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'role' => UserRole::Owner,
    ]))->toThrow(HttpException::class);
});

it('lets a manager create cashiers but not managers', function () {
    $ok = $this->service->create($this->managerA, [
        'name' => 'C', 'email' => 'c@example.com', 'password' => 'password', 'role' => UserRole::Cashier,
    ]);

    expect($ok->role)->toBe(UserRole::Cashier);

    expect(fn () => $this->service->create($this->managerA, [
        'name' => 'M', 'email' => 'm@example.com', 'password' => 'password', 'role' => UserRole::Manager,
    ]))->toThrow(HttpException::class);
});

it('returns 404 for a user id from another shop', function () {
    expect(fn () => $this->service->findFor($this->ownerA, $this->ownerB->id))
        ->toThrow(ModelNotFoundException::class);
});

it('blocks changing the role or status of the owner and of oneself', function () {
    expect(fn () => $this->service->changeRole($this->managerA, $this->ownerA, UserRole::Cashier))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->setActive($this->ownerA, $this->ownerA, false))
        ->toThrow(AuthorizationException::class);
});

it('blocks a manager from promoting or touching another manager', function () {
    $otherManager = User::factory()->for($this->shopA)->create(['role' => UserRole::Manager]);

    expect(fn () => $this->service->setActive($this->managerA, $otherManager, false))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->changeRole($this->managerA, $this->cashierA, UserRole::Manager))
        ->toThrow(HttpException::class);
});

it('lets the owner deactivate and reactivate a cashier', function () {
    $this->service->setActive($this->ownerA, $this->cashierA, false);
    expect($this->cashierA->fresh()->is_active)->toBeFalse();

    $this->service->setActive($this->ownerA, $this->cashierA, true);
    expect($this->cashierA->fresh()->is_active)->toBeTrue();
});

it('logs out a disabled user on the next request', function () {
    Route::middleware('web')->get('/_active_probe', fn () => 'ok');

    $disabled = User::factory()->for($this->shopA)->create(['is_active' => false]);

    $this->actingAs($disabled)->get('/_active_probe')->assertRedirect('/');

    $this->assertGuest();
});
