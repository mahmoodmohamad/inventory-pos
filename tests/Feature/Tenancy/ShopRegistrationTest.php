<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopRegistrationService;
use Illuminate\Database\QueryException;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'shop_name' => 'محل الأمل لقطع الغيار',
        'name' => 'John Doe',
        'email' => 'owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

it('creates a shop and an owner user on registration', function () {
    $this->post(route('register.store'), registrationPayload())
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'owner@example.com')->firstOrFail();

    expect($user->shop_id)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Owner)
        ->and($user->shop->name)->toBe('محل الأمل لقطع الغيار')
        ->and($user->shop->slug)->not->toBeEmpty();
});

it('requires a shop name', function () {
    $this->post(route('register.store'), registrationPayload(['shop_name' => '']))
        ->assertSessionHasErrors('shop_name');

    expect(User::count())->toBe(0)->and(Shop::count())->toBe(0);
});

it('ignores role and shop_id sent by the client', function () {
    $other = Shop::factory()->create();

    $this->post(route('register.store'), registrationPayload([
        'role' => 'manager',
        'shop_id' => $other->id,
    ]));

    $user = User::where('email', 'owner@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Owner)
        ->and($user->shop_id)->not->toBe($other->id);
});

it('rolls back the shop when user creation fails', function () {
    User::factory()->create(['email' => 'dup@example.com']);
    $shopsBefore = Shop::count();

    expect(fn () => app(ShopRegistrationService::class)->register([
        'shop_name' => 'Rollback Shop',
        'name' => 'X',
        'email' => 'dup@example.com',
        'password' => 'password',
    ]))->toThrow(QueryException::class);

    expect(Shop::count())->toBe($shopsBefore);
});

it('gives each shop a unique slug even with the same name', function () {
    $service = app(ShopRegistrationService::class);

    $a = $service->register(['shop_name' => 'Same Name', 'name' => 'A', 'email' => 'a@example.com', 'password' => 'password']);
    $b = $service->register(['shop_name' => 'Same Name', 'name' => 'B', 'email' => 'b@example.com', 'password' => 'password']);

    expect($a->shop->slug)->not->toBe($b->shop->slug);
});
