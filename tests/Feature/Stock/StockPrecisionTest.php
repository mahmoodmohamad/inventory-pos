<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    app(TenantContext::class)->set($this->shop);

    $this->stock = app(StockService::class);
    $this->product = Product::factory()->for($this->shop)->create();
});

it('rejects quantities with more than three decimals and writes nothing', function () {
    expect(fn () => $this->stock->openingBalance($this->product, '0.0004', '10'))
        ->toThrow(InvalidArgumentException::class);

    expect(StockMovement::count())->toBe(0)
        ->and($this->product->fresh()->stock_qty)->toBe('0.000')
        ->and($this->product->fresh()->avg_cost)->toBe('0.0000');
});

it('keeps exact three-decimal quantities in the ledger', function () {
    $this->stock->purchase($this->product, '0.001', '10.5');

    expect($this->product->fresh()->stock_qty)->toBe('0.001')
        ->and(StockMovement::first()->quantity)->toBe('0.001');
});

it('rejects invalid unit costs', function (mixed $cost) {
    expect(fn () => $this->stock->purchase($this->product, 1, $cost))
        ->toThrow(InvalidArgumentException::class);

    expect(StockMovement::count())->toBe(0);
})->with(['negative' => '-1', 'too precise' => '1.23456', 'too large' => '100000000']);

it('computes the average without float drift', function () {
    $this->stock->purchase($this->product, '3', '0.1');
    $this->stock->purchase($this->product, '3', '0.2');

    expect($this->product->fresh()->avg_cost)->toBe('0.1500');
});

it('still accepts int and float inputs', function () {
    $this->stock->purchase($this->product, 5, 10.5);
    $this->stock->adjust($this->product, -1.5, 'test');

    expect($this->product->fresh()->stock_qty)->toBe('3.500');
});

it('rejects a stock quantity beyond the column range', function () {
    expect(fn () => $this->stock->purchase($this->product, '1000000000', '1'))
        ->toThrow(InvalidArgumentException::class);
});
