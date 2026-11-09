<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Generic random suppliers for default-shop (not called by DatabaseSeeder; use
 * DemoSupplierSeeder for demo data). Skips if the shop already has suppliers.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            if (Supplier::query()->exists()) {
                return;
            }

            Supplier::factory()->count(5)->create(['shop_id' => $shop->id]);
        });
    }
}