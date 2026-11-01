
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

Route::get('vehicle-makes', [VehicleMakeController::class, 'index'])
    ->name('vehicle-makes.index');

/*
|--------------------------------------------------------------------------
| Authenticated & Verified Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('/dashboard', 'dashboard')
        ->name('dashboard');

    Route::livewire('/users', 'pages::users.index')
        ->can('viewAny', User::class)
        ->name('users.index');

});

/*
|--------------------------------------------------------------------------
| Catalog Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function (): void {
    Route::livewire('catalog/vehicle-variants', 'catalog.vehicle-variants')
        ->name('catalog.vehicle-variants');

    Route::get('/catalog/vehicles', VehicleCatalog::class)
        ->middleware(['auth'])
        ->name('catalog.vehicles');
});

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
