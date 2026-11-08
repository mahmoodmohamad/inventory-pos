<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;

/**
 * Creates the base shop(s). Always runs (not demo-only) so the app has a tenant.
 * Idempotent: keyed by slug.
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        Shop::query()->updateOrCreate(
            ['slug' => 'default-shop'],
            [
                'name' => 'Default Shop',
                'phone' => null,
                'address' => null,
                'currency' => 'EGP',
                'is_active' => true,
                'settings' => ['allow_negative_stock' => false, 'storefront_enabled' => false],
            ],
        );
    }
}
