<?php

use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;

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
        ->getJson(route('vehicle-makes.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Audi')
        ->assertJsonPath('data.1.name', 'Toyota')
        ->assertJsonCount(2, 'data.1.models')
        ->assertJsonPath('data.1.models.0.name', 'Corolla')
        ->assertJsonPath('data.1.models.1.name', 'Yaris')
        ->assertJsonMissing(['name' => 'Hidden Make'])
        ->assertJsonMissing(['name' => 'Old Model'])
        ->assertJsonMissingPath('data.0.created_at');
});