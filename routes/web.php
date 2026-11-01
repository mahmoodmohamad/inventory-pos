<?php

use App\Http\Controllers\VehicleMakeController;
use App\Livewire\VehicleCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated & Verified Routes
|--------------------------------------------------------------------------
|
| Routes here are available to every signed-in, verified user of a shop.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::livewire('/users', 'pages::users.index')
        ->can('viewAny', User::class)
        ->name('users.index');

    // Read-only catalog: any authenticated user can browse.
    Route::get('vehicle-makes', [VehicleMakeController::class, 'index'])
        ->name('vehicle-makes.index');
});

/*
|--------------------------------------------------------------------------
| Catalog Management Routes (platform admins only)
|--------------------------------------------------------------------------
|
| Gated behind the `manage-catalog` ability. Regular shop users get 403.
|
*/

Route::middleware(['auth', 'verified', 'can:manage-catalog'])->group(function () {
    Route::livewire('catalog/vehicle-variants', 'catalog.vehicle-variants')
        ->name('catalog.vehicle-variants');

    Route::get('catalog/vehicles', VehicleCatalog::class)
        ->name('catalog.vehicles');
});

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
