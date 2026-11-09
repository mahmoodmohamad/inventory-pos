<?php

// tests/Feature/Stock/StockServiceTest.php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\MissingTenantException;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->set($this->shop);

    $this->stock = app(StockService::class);
    $this->product = Product::factory()->for($this->shop)->create();
});

function snapshot(Product $product): array
{
    $p = $product->fresh();

    return [(float) $p->stock_qty, (float) $p->avg_cost, (float) $p->last_cost];
}

it('computes weighted average cost on purchases', function () {
    $this->stock->purchase($this->product, 10, 100);
    $this->stock->purchase($this->product, 10, 120);

    [$qty, $avg, $last] = snapshot($this->product);

    expect($qty)->toBe(20.0)
        ->and($avg)->toEqualWithDelta(110.0, 0.0001)
        ->and($last)->toEqualWithDelta(120.0, 0.0001);
});

it('requires a cost for opening balance and sets the average from it', function () {
    $this->stock->openingBalance($this->product, 8, 75);

    [$qty, $avg] = snapshot($this->product);

    expect($qty)->toBe(8.0)->and($avg)->toEqualWithDelta(75.0, 0.0001);
});

it('snapshots the average cost on sale without changing it', function () {
    $this->stock->purchase($this->product, 10, 100);
    $this->stock->purchase($this->product, 10, 120);

    $movement = $this->stock->sale($this->product, 5);

    [$qty, $avg] = snapshot($this->product);

    expect((float) $movement->unit_cost)->toEqualWithDelta(110.0, 0.0001)
        ->and((float) $movement->quantity)->toBe(-5.0)
        ->and($qty)->toBe(15.0)
        ->and($avg)->toEqualWithDelta(110.0, 0.0001);
});

it('re-enters a sale return into the average at the original cost', function () {
    $this->stock->purchase($this->product, 10, 100);
    $this->stock->purchase($this->product, 10, 120);
    $this->stock->sale($this->product, 5);                 // qty 15, avg 110

    $this->stock->saleReturn($this->product, 1, 100);      // (15*110 + 1*100) / 16

    [$qty, $avg] = snapshot($this->product);

    expect($qty)->toBe(16.0)->and($avg)->toEqualWithDelta(109.375, 0.0001);
});

it('returns purchases at the current average without changing it', function () {
    $this->stock->purchase($this->product, 10, 100);
    $this->stock->purchase($this->product, 10, 120);

    $movement = $this->stock->purchaseReturn($this->product, 4);

    [$qty, $avg] = snapshot($this->product);

    expect((float) $movement->unit_cost)->toEqualWithDelta(110.0, 0.0001)
        ->and($qty)->toBe(16.0)
        ->and($avg)->toEqualWithDelta(110.0, 0.0001);
});

it('records adjustments at the current average', function () {
    $this->stock->purchase($this->product, 10, 100);

    $this->stock->adjust($this->product, -2, 'جرد: عجز');

    [$qty, $avg] = snapshot($this->product);

    expect($qty)->toBe(8.0)->and($avg)->toEqualWithDelta(100.0, 0.0001);
});

it('allows negative stock by default and ignores the negative balance in the average', function () {
    $movement = $this->stock->sale($this->product, 3);   // أول بيع على صفر

    expect((float) $movement->qty_after)->toBe(-3.0);

    $this->stock->purchase($this->product, 10, 50);      // base = 0, مش -3

    [$qty, $avg] = snapshot($this->product);

    expect($qty)->toBe(7.0)->and($avg)->toEqualWithDelta(50.0, 0.0001);
});

it('blocks overselling when the shop disallows negative stock', function () {
    $this->shop->update(['settings' => ['allow_negative_stock' => false]]);
    $this->stock->purchase($this->product, 2, 10);

    expect(fn () => $this->stock->sale($this->product, 3))
        ->toThrow(InsufficientStockException::class);

    expect(snapshot($this->product)[0])->toBe(2.0)
        ->and(StockMovement::count())->toBe(1);
});

it('rejects zero or negative quantities', function () {
    $this->stock->purchase($this->product, 0, 10);
})->throws(InvalidArgumentException::class);

it('keeps the cached stock equal to the sum of the ledger', function () {
    $this->stock->openingBalance($this->product, 5, 40);
    $this->stock->purchase($this->product, 10, 60);
    $this->stock->sale($this->product, 7);
    $this->stock->saleReturn($this->product, 2, 50);
    $this->stock->purchaseReturn($this->product, 1);
    $this->stock->adjust($this->product, -1.5, 'تالف');

    expect((float) StockMovement::query()->sum('quantity'))
        ->toEqualWithDelta(snapshot($this->product)[0], 0.0001)
        ->and(StockMovement::query()->where('product_id', $this->product->id)->latest('id')->first()->qty_after)
        ->toEqual($this->product->fresh()->stock_qty);
});

it('makes the ledger append-only', function () {
    $movement = $this->stock->purchase($this->product, 1, 10);

    expect(fn () => $movement->update(['notes' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('stores the polymorphic reference without a foreign key', function () {
    $movement = $this->stock->purchase($this->product, 1, 10, reference: $this->product);

    expect($movement->reference_type)->toBe(Product::class)
        ->and($movement->reference->is($this->product))->toBeTrue();
});

it('sets shop_id and type on every movement', function () {
    $movement = $this->stock->purchase($this->product, 1, 10);

    expect($movement->shop_id)->toBe($this->shop->id)
        ->and($movement->type)->toBe(StockMovementType::Purchase);
});

it('cannot move stock of a product from another shop', function () {
    $productB = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());

    expect(fn () => $this->stock->purchase($productB->id, 1, 10))
        ->toThrow(ModelNotFoundException::class);

    expect(DB::table('stock_movements')->count())->toBe(0);
});

it('hides movements of another shop', function () {
    $productB = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());

    $this->tenant->run($this->other, fn () => $this->stock->purchase($productB, 5, 10));
    $this->stock->purchase($this->product, 1, 10);

    expect(StockMovement::count())->toBe(1)
        ->and($this->tenant->run($this->other, fn () => StockMovement::count()))->toBe(1);
});

it('rejects at database level a movement for a product of another shop', function () {
    $productB = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());

    expect(fn () => DB::table('stock_movements')->insert([
        'shop_id' => $this->shop->id,
        'product_id' => $productB->id,
        'type' => 'purchase',
        'quantity' => 1,
        'qty_after' => 1,
        'unit_cost' => 1,
        'avg_cost_after' => 1,
    ]))->toThrow(QueryException::class);
});

it('fails closed without tenant context', function () {
    $this->tenant->forget();

    $this->stock->purchase($this->product->id, 1, 10);
})->throws(MissingTenantException::class);
it('does not allow negative stock when shop settings are empty', function () {
    $shop = Shop::factory()->create(['settings' => null]);
    $this->tenant->set($shop);
    $product = Product::factory()->for($shop)->create();

    expect(fn () => $this->stock->sale($product, 1))
        ->toThrow(InsufficientStockException::class);
});