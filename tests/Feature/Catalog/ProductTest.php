<?php

// tests/Feature/Catalog/ProductTest.php

use App\Enums\ProductCondition;
use App\Exceptions\MissingTenantException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\VehicleVariant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\DemoShopSeeder;
use Database\Seeders\DemoStockSeeder;
use Database\Seeders\VehicleCatalogSeeder;
use Database\Seeders\VehicleVariantSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
});

it('reads only the current shop products', function () {
    Product::factory()->for($this->shopA)->count(2)->create();
    Product::factory()->for($this->shopB)->count(3)->create();

    expect($this->tenant->run($this->shopA, fn () => Product::count()))->toBe(2)
        ->and($this->tenant->run($this->shopB, fn () => Product::count()))->toBe(3);
});

it('fails closed without tenant context', function () {
    Product::query()->count();
})->throws(MissingTenantException::class);

it('cannot find a product of another shop by its known id', function () {
    $productB = Product::factory()->for($this->shopB)->create();

    $this->tenant->run($this->shopA, function () use ($productB) {
        expect(Product::find($productB->id))->toBeNull();
    });
});

it('ignores stock and cost fields sent through mass assignment', function () {
    $product = $this->tenant->run($this->shopA, fn () => Product::create([
        'name' => 'Pad', 'stock_qty' => 50, 'avg_cost' => 99, 'last_cost' => 99,
    ]));

    $fresh = $product->fresh();

    expect((float) $fresh->stock_qty)->toBe(0.0)
        ->and((float) $fresh->avg_cost)->toBe(0.0)
        ->and($fresh->shop_id)->toBe($this->shopA->id);
});

it('allows the same barcode in different shops but not within one shop', function () {
    Product::factory()->for($this->shopA)->create(['barcode' => '111']);
    Product::factory()->for($this->shopB)->create(['barcode' => '111']);

    expect(DB::table('products')->where('barcode', '111')->count())->toBe(2);

    Product::factory()->for($this->shopA)->create(['barcode' => '111']);
})->throws(QueryException::class);

it('keeps each condition as a separate product and search returns all of them', function () {
    Product::factory()->for($this->shopA)->condition(ProductCondition::Original)
        ->create(['part_number' => '04465-02220']);
    Product::factory()->for($this->shopA)->condition(ProductCondition::Used)
        ->create(['part_number' => '04465-02220']);
    Product::factory()->for($this->shopB)->create(['part_number' => '04465-02220']);

    $found = $this->tenant->run($this->shopA, fn () => Product::query()->search('04465')->get());

    expect($found)->toHaveCount(2)
        ->and($found->pluck('condition')->all())
        ->toContain(ProductCondition::Original, ProductCondition::Used);
});

it('finds a product by exact barcode', function () {
    Product::factory()->for($this->shopA)->create(['barcode' => '6220000000011']);

    expect($this->tenant->run($this->shopA, fn () => Product::query()->search('6220000000011')->count()))->toBe(1);
});

it('lists low stock products only', function () {
    Product::factory()->for($this->shopA)->withStock(2)->create(['min_stock_alert' => 5]);
    Product::factory()->for($this->shopA)->withStock(20)->create(['min_stock_alert' => 5]);
    Product::factory()->for($this->shopA)->withStock(0)->create(['min_stock_alert' => 0]);

    expect($this->tenant->run($this->shopA, fn () => Product::query()->lowStock()->count()))->toBe(1);
});

it('rejects at database level a category from another shop', function () {
    $catB = Category::factory()->for($this->shopB)->create();

    expect(fn () => DB::table('products')->insert([
        'shop_id' => $this->shopA->id,
        'category_id' => $catB->id,
        'name' => 'bad product',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects at database level a fitment pointing to another shop product', function () {
    $productB = Product::factory()->for($this->shopB)->create();
    $variant = VehicleVariant::factory()->create();

    expect(fn () => DB::table('product_fitments')->insert([
        'shop_id' => $this->shopA->id,
        'product_id' => $productB->id,
        'vehicle_variant_id' => $variant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('creates fitments in the product shop and rejects duplicates', function () {
    $product = Product::factory()->for($this->shopA)->create();
    $variant = VehicleVariant::factory()->create();

    $this->tenant->run($this->shopA, function () use ($product, $variant) {
        $fitment = $product->fitments()->create(['vehicle_variant_id' => $variant->id]);

        expect($fitment->shop_id)->toBe($this->shopA->id)
            ->and($fitment->variant->is($variant))->toBeTrue();

        expect(fn () => $product->fitments()->create(['vehicle_variant_id' => $variant->id]))
            ->toThrow(QueryException::class);
    });
});

it('hides fitments of another shop', function () {
    $variant = VehicleVariant::factory()->create();
    $productA = Product::factory()->for($this->shopA)->create();
    $productB = Product::factory()->for($this->shopB)->create();

    ProductFitment::factory()->for($productA)->for($variant, 'variant')->create();
    ProductFitment::factory()->for($productB)->for($variant, 'variant')->create();

    expect($this->tenant->run($this->shopA, fn () => ProductFitment::count()))->toBe(1);
});

it('restricts deleting a variant that still has fitments', function () {
    $variant = VehicleVariant::factory()->create();
    $product = Product::factory()->for($this->shopA)->create();
    ProductFitment::factory()->for($product)->for($variant, 'variant')->create();

    expect(fn () => $variant->delete())->toThrow(QueryException::class);
});

it('seeds demo products idempotently', function () {
    $this->seed(VehicleCatalogSeeder::class);
    $this->seed(VehicleVariantSeeder::class);
    $this->seed(DemoShopSeeder::class);
    $this->seed(DemoProductSeeder::class);

    $products = DB::table('products')->count();
    $fitments = DB::table('product_fitments')->count();

    $this->seed(DemoProductSeeder::class);

    expect($products)->toBeGreaterThan(0)
        ->and($fitments)->toBeGreaterThan(0)
        ->and(DB::table('products')->count())->toBe($products)
        ->and(DB::table('product_fitments')->count())->toBe($fitments);
});
it('seeds demo stock through the ledger idempotently', function () {
    $this->seed(VehicleCatalogSeeder::class);
    $this->seed(VehicleVariantSeeder::class);
    $this->seed(DemoShopSeeder::class);
    $this->seed(DemoProductSeeder::class);
    $this->seed(DemoStockSeeder::class);

    $shop = Shop::where('slug', 'demo-shop')->firstOrFail();

    $summary = $this->tenant->run($shop, fn () => [
        'movements' => StockMovement::count(),
        'ledger' => (float) StockMovement::sum('quantity'),
        'cached' => (float) Product::sum('stock_qty'),
        'low' => Product::query()->lowStock()->count(),
    ]);

    expect($summary['movements'])->toBeGreaterThan(0)
        ->and($summary['ledger'])->toEqualWithDelta($summary['cached'], 0.0001)
        ->and($summary['low'])->toBe(1);

    $this->seed(DemoStockSeeder::class);

    expect($this->tenant->run($shop, fn () => StockMovement::count()))->toBe($summary['movements']);
});
