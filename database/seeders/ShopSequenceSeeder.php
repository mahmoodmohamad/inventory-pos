<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopSequenceSeeder extends Seeder
{
    public function run(): void
    {
        Shop::query()->each(function (Shop $shop): void {
            $now = now();

            DB::table('shop_sequences')->insertOrIgnore([
                [
                    'shop_id' => $shop->id,
                    'sequence_key' => 'sales_invoice',
                    'last_number' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'shop_id' => $shop->id,
                    'sequence_key' => 'purchase_invoice',
                    'last_number' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }
}
