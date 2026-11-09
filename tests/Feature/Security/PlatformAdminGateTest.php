<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config(['app.platform_admins' => ['admin@example.com']]);
});

function platformUser(string $email, array $attrs = []): User
{
    return User::factory()->for(Shop::factory()->create())->create([
        'email' => $email,
        'role' => UserRole::Owner,
        ...$attrs,
    ]);
}

it('allows a verified active user whose email is in PLATFORM_ADMINS', function () {
    $admin = platformUser('Admin@Example.com');

    expect(Gate::forUser($admin)->allows('manage-catalog'))->toBeTrue();
    $this->actingAs($admin)->get(route('catalog.vehicles'))->assertOk();
});

it('denies an unverified user even with an admin email', function () {
    $user = platformUser('admin@example.com', ['email_verified_at' => null]);

    expect(Gate::forUser($user)->allows('manage-catalog'))->toBeFalse();
    $this->actingAs($user)->get(route('catalog.vehicles'))
        ->assertRedirect(route('verification.notice'));
});

it('denies a verified user whose email is not in the list', function () {
    $user = platformUser('someone@example.com');

    expect(Gate::forUser($user)->allows('manage-catalog'))->toBeFalse();
    $this->actingAs($user)->get(route('catalog.vehicles'))->assertForbidden();
});

it('denies an inactive admin', function () {
    $user = platformUser('admin@example.com', ['is_active' => false]);

    expect(Gate::forUser($user)->allows('manage-catalog'))->toBeFalse();
});

it('does not grant platform admin through the shop owner role', function () {
    $owner = platformUser('owner@example.com');

    expect($owner->role)->toBe(UserRole::Owner)
        ->and(Gate::forUser($owner)->allows('manage-catalog'))->toBeFalse();
});

it('does not grant catalog access by registering with an admin email', function () {
    $this->post(route('register.store'), [
        'shop_name' => 'Evil Shop',
        'name' => 'Attacker',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->get(route('catalog.vehicles'))->assertRedirect(route('verification.notice'));
});

it('revokes access when an admin changes email to an unverified one', function () {
    $user = platformUser('admin@example.com');

    $user->forceFill(['email' => 'other@example.com', 'email_verified_at' => null])->save();

    expect(Gate::forUser($user->refresh())->allows('manage-catalog'))->toBeFalse();
});
