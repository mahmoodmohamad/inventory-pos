<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Opening balances for the seeded products, via StockService (proper WAC).
 * Skips products that already have an Opening movement. Idempotent.
 */
class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant): void {
            $tenant->run($shop, function (): void {
                $stock = app(StockService::class);

                foreach ($this->balances() as $b) {
                    $product = Product::query()->where('barcode', $b['barcode'])->first();
                    if ($product === null) {
                        continue;
                    }

                    $exists = StockMovement::query()
                        ->where('product_id', $product->id)
                        ->where('type', StockMovementType::Opening)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $stock->openingBalance($product, $b['qty'], $b['cost'], 'Opening balance');
                }
            });
        });
    }

    /** @return list<array{barcode:string,qty:int|float,cost:int|float}> */
    private function balances(): array
    {
        return [
            ['barcode' => '1000000000011', 'qty' => 12, 'cost' => 600],
            ['barcode' => '1000000000021', 'qty' => 40, 'cost' => 110],
            ['barcode' => '1000000000031', 'qty' => 60, 'cost' => 55],
            ['barcode' => '1000000000041', 'qty' => 2,  'cost' => 980],
            ['barcode' => '1000000000051', 'qty' => 20, 'cost' => 140],
        ];
    }
}
