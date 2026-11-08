<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\VehicleVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Attaches the seeded products to a couple of vehicle variants.
 * Idempotent via unique(shop_id, product_id, vehicle_variant_id).
 */
class ProductFitmentSeeder extends Seeder
{
    public function run(): void
    {
        $variants = VehicleVariant::query()
            ->whereIn('slug', ['1-6-2014-2018', '1-6-2019-2022', '1-6-2016-2020'])
            ->get();

        if ($variants->isEmpty()) {
            return;
        }

        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant, $variants): void {
            $tenant->run($shop, function () use ($variants): void {
                $products = Product::query()
                    ->whereIn('barcode', [
                        '1000000000011', '1000000000021', '1000000000031',
                        '1000000000041', '1000000000051',
                    ])
                    ->get();

                foreach ($products as $product) {
                    foreach ($variants as $variant) {
                        $product->fitments()->firstOrCreate([
                            'vehicle_variant_id' => $variant->id,
                        ]);
                    }
                }
            });
        });
    }
}
