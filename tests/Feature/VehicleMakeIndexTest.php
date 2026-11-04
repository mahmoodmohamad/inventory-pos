<?php

use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Livewire\Livewire;

it('rejects guests', function () {
    $this->getJson(route('vehicle-makes.index'))->assertUnauthorized();
});

it('lists active makes with their active models ordered by name', function () {
    $toyota = VehicleMake::factory()->create(['name' => 'Toyota', 'slug' => 'toyota']);
    $audi = VehicleMake::factory()->create(['name' => 'Audi', 'slug' => 'audi']);
    VehicleMake::factory()->inactive()->create(['name' => 'Hidden Make', 'slug' => 'hidden-make']);

    VehicleModel::factory()->for($toyota, 'make')->create(['name' => 'Yaris', 'slug' => 'yaris']);
    VehicleModel::factory()->for($toyota, 'make')->create(['name' => 'Corolla', 'slug' => 'corolla']);
    VehicleModel::factory()->inactive()->for($toyota, 'make')->create(['name' => 'Old Model', 'slug' => 'old-model']);
    VehicleModel::factory()->for($audi, 'make')->create(['name' => 'A4', 'slug' => 'a4']);

    $this->actingAs(User::factory()->create())
        ->get(route('vehicle-makes.index'))
        ->assertOk()
        ->assertSeeInOrder(['Audi', 'A4', 'Toyota', 'Corolla', 'Yaris'])
        ->assertDontSee('Hidden Make')
        ->assertDontSee('Old Model');
});

it('filters makes by the search term', function () {
    VehicleMake::factory()->create(['name' => 'Toyota', 'slug' => 'toyota']);
    VehicleMake::factory()->create(['name' => 'Audi', 'slug' => 'audi']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::vehicle-makes')
        ->set('search', 'toy')
        ->assertSee('Toyota')
        ->assertDontSee('Audi');
});
it('forbids a regular shop user from the catalog management page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('catalog.vehicles'))
        ->assertForbidden();
});

it('lets a platform admin open the catalog management page', function () {
    config(['app.platform_admins' => ['admin@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']))
        ->get(route('catalog.vehicles'))
        ->assertOk();
});
