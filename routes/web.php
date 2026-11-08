<?php

use App\Http\Controllers\StorefrontCartController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\ResolvePublicShop;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Public Storefront (guests, one shop per slug)
|--------------------------------------------------------------------------
*/
Route::get('/language/{locale}', function ($locale) {
    abort_unless(in_array($locale, ['ar', 'en']), 404);

    session(['locale' => $locale]);

    return redirect()->back();
})->name('language.switch');
// routes/web.php  (جوه نفس group الـ storefront)
Route::prefix('s/{shopSlug}')
    ->middleware([ResolvePublicShop::class, 'throttle:120,1'])
    ->name('storefront.')
    ->group(function (): void {
        Route::get('/', [StorefrontController::class, 'index'])->name('index');

        Route::get('cart', [StorefrontCartController::class, 'show'])->name('cart.show');
        Route::post('cart', [StorefrontCartController::class, 'store'])->name('cart.store');
        Route::patch('cart/{productId}', [StorefrontCartController::class, 'update'])
            ->whereNumber('productId')->name('cart.update');
        Route::delete('cart/{productId}', [StorefrontCartController::class, 'destroy'])
            ->whereNumber('productId')->name('cart.destroy');
    });


Route::get('/', function (Request $request) {
    $lang = in_array($request->query('lang'), ['ar', 'en'], true)
        ? $request->query('lang')
        : $request->session()->get('landing_lang', 'ar');

    $request->session()->put('landing_lang', $lang);
    app()->setLocale($lang);

    $shop = Shop::query()->where('slug', 'demo-shop')->first()
        ?? new Shop(['name' => 'قطع غيار برو', 'slug' => 'demo-shop']);

    return view('welcome', ['shop' => $shop]);
})->name('home');
Route::get('/{shopSlug}/products/{productId}', [
    StorefrontController::class,
    'show',
])->name('storefront.products.show');
/*
|--------------------------------------------------------------------------
| Authenticated & Verified Routes
|--------------------------------------------------------------------------
|
| Routes here are available to every signed-in, verified user of a shop.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

    Route::livewire('/users', 'pages::users.index')
        ->can('viewAny', User::class)
        ->name('users.index');
    Route::livewire('/categories', 'pages::categories.index')
        ->can('viewAny', Category::class)
        ->name('categories.index');
    // Read-only catalog: any authenticated user can browse.
    Route::livewire('/vehicle-makes', 'pages::vehicle-makes')
        ->name('vehicle-makes.index');
    Route::livewire('/products', 'pages::products.index')
        ->can('viewAny', Product::class)
        ->name('products.index');

    Route::livewire('/brands', 'pages::brands.index')
        ->can('viewAny', Brand::class)
        ->name('brands.index');

    Route::livewire('/stock-movements', 'pages::stock-movements.index')
        ->can('viewAny', StockMovement::class)
        ->name('stock-movements.index');
    Route::livewire('/sales', 'pages::sales.index')
        ->can('viewAny', Sale::class)
        ->name('sales.index');

    Route::livewire('/sales/{id}', 'pages::sales.show')
        ->whereNumber('id')
        ->name('sales.show');

    Route::livewire('/customers', 'pages::customers.index')
        ->can('viewAny', Customer::class)
        ->name('customers.index');
    Route::livewire('suppliers', 'pages::suppliers.index')->name('suppliers.index');
    Route::livewire('purchases', 'pages::purchases.index')->name('purchases.index');
    Route::livewire('purchases/create', 'pages::purchases.create')->name('purchases.create');
    Route::livewire('purchases/{id}', 'pages::purchases.show')->whereNumber('id')->name('purchases.show');
});

/*
|--------------------------------------------------------------------------
| Catalog Management Routes (platform admins only)
|--------------------------------------------------------------------------
|
| Gated behind the `manage-catalog` ability. Regular shop users get 403.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::livewire('catalog/vehicle-variants', 'catalog.vehicle-variants')
        ->name('catalog.vehicle-variants');

    Route::livewire('catalog/vehicles', 'catalog.vehicle-catalog')
        ->name('catalog.vehicles');
});

/*
|--------------------------------------------------------------------------
| Settings Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
