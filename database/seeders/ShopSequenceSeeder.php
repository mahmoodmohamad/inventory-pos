<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the invoice sequence row per shop.
 * Idempotent via unique(shop_id, sequence_key).
 */
class ShopSequenceSeeder extends Seeder
{
    public function run(): void
    {
        Shop::query()->each(function (Shop $shop): void {
            DB::table('shop_sequences')->updateOrInsert(
                ['shop_id' => $shop->id, 'sequence_key' => 'invoice'],
                ['last_number' => 0, 'created_at' => now(), 'updated_at' => now()],
            );
        });
    }
}
