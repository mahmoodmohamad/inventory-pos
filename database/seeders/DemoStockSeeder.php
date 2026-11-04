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
 * رصيد افتتاحي للمنتجات التجريبية عن طريق StockService.
 *
 * يجب تشغيل DemoProductSeeder قبله.
 * آمن للتشغيل أكثر من مرة.
 */
class DemoStockSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()
            ->where('slug', 'demo-shop')
            ->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            $stockService = app(StockService::class);

            foreach ($this->balances() as $balance) {
                $product = Product::query()
                    ->where('barcode', $balance['barcode'])
                    ->first();

                if ($product === null) {
                    continue;
                }

                $alreadySeeded = StockMovement::query()
                    ->where('product_id', $product->id)
                    ->where('type', StockMovementType::Opening)
                    ->exists();

                if ($alreadySeeded) {
                    continue;
                }

                $stockService->openingBalance(
                    $product,
                    $balance['qty'],
                    $balance['cost'],
                    'رصيد افتتاحي تجريبي'
                );
            }
        });
    }

    /**
     * @return list<array{
     *     barcode: string,
     *     qty: int|float,
     *     cost: int|float
     * }>
     */
    private function balances(): array
    {
        return [
            [
                'barcode' => '6220000000011',
                'qty' => 12,
                'cost' => 600,
            ],
            [
                'barcode' => '6220000000012',
                'qty' => 20,
                'cost' => 270,
            ],
            [
                'barcode' => '6220000000021',
                'qty' => 40,
                'cost' => 110,
            ],
            [
                'barcode' => '6220000000031',
                'qty' => 60,
                'cost' => 55,
            ],
            [
                'barcode' => '6220000000041',
                'qty' => 1,
                'cost' => 980,
            ],
        ];
    }
}
