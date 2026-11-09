<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Common part brands for default-shop only. Idempotent: keyed by (shop_id, name).
 * demo-shop gets its own brands from DemoProductSeeder.
 */
class BrandSeeder extends Seeder
{
    private const BRANDS = [
        'Bosch', 'Denso', 'Valeo', 'Mann Filter', 'Sachs', 'TRW',
        'Mahle', 'Febi Bilstein', 'SKF', 'NGK', 'Continental', 'Hella',
    ];

    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            foreach (self::BRANDS as $name) {
                Brand::query()->firstOrCreate(['name' => $name]);
            }
        });
    }
}