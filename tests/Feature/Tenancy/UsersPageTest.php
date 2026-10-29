<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
});

it('forbids a cashier from the users page', function () {
    $cashier = User::factory()->for($this->shop)->create(['role' => UserRole::Cashier]);

    $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
});

it('shows only users of the same shop', function () {
    $owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
    $foreign = User::factory()->for($this->other)->create(['name' => 'Foreign Person']);

    $this->actingAs($owner)->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('Foreign Person');
});

it('lets an owner add a cashier from the page', function () {
    $owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);

    Livewire::actingAs($owner)->test('pages::users.index')
        ->set('name', 'Ali')
        ->set('email', 'ali@example.com')
        ->set('password', 'password')
        ->set('role', 'cashier')
        ->call('create')
        ->assertHasNoErrors();

    expect(User::where('email', 'ali@example.com')->first())
        ->shop_id->toBe($this->shop->id)
        ->role->toBe(UserRole::Cashier);
});

it('rejects creating an owner through the form', function () {
    $owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);

    Livewire::actingAs($owner)->test('pages::users.index')
        ->set('name', 'X')->set('email', 'x@example.com')->set('password', 'password')
        ->set('role', 'owner')
        ->call('create')
        ->assertHasErrors(['role']);
});

it('cannot toggle a user of another shop', function () {
    $owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
    $foreign = User::factory()->for($this->other)->create();

    Livewire::actingAs($owner)->test('pages::users.index')
        ->call('toggleActive', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh()->is_active)->toBeTrue();
});
