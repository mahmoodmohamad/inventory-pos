<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds common part brands for every shop. Idempotent: keyed by (shop_id, name).
 */
class BrandSeeder extends Seeder
{
    private const BRANDS = [
        'Bosch', 'Denso', 'Valeo', 'Mann Filter', 'Sachs', 'TRW',
        'Mahle', 'Febi Bilstein', 'SKF', 'NGK', 'Continental', 'Hella',
    ];

    public function run(): void
    {
        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant): void {
            $tenant->run($shop, function (): void {
                foreach (self::BRANDS as $name) {
                    Brand::query()->firstOrCreate(['name' => $name]);
                }
            });
        });
    }
}
