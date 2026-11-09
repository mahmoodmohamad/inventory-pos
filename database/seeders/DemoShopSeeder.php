<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->firstOrNew([
            'slug' => 'demo-shop',
        ]);

        $shop->fill([
            'name' => 'محل قطع غيار تجريبي',
            'phone' => '01000000000',
            'address' => 'القاهرة، مصر',
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        $shop->settings = array_merge(
            $shop->settings ?? [],
            [
                'allow_negative_stock' => true,
                'storefront_enabled' => true,
            ],
        );

        $shop->save();

        // ShopSequenceSeeder runs before this seeder; ensure demo-shop has sequences.
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

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            $users = [
                [
                    'email' => 'owner@demo.test',
                    'name' => 'أحمد المالك',
                    'role' => UserRole::Owner,
                ],
                [
                    'email' => 'manager@demo.test',
                    'name' => 'محمد المدير',
                    'role' => UserRole::Manager,
                ],
                [
                    'email' => 'cashier@demo.test',
                    'name' => 'كريم الكاشير',
                    'role' => UserRole::Cashier,
                ],
            ];

            foreach ($users as $row) {
                $user = User::query()->firstOrNew([
                    'email' => $row['email'],
                ]);

                $user->forceFill([
                    'name' => $row['name'],
                    'password' => Hash::make('password'),
                    'shop_id' => $shop->id,
                    'role' => $row['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $user->save();
            }

            $categories = [
                'محرك',
                'فرامل',
                'كهرباء',
                'تعليق',
                'تكييف',
                'فلاتر',
            ];

            foreach ($categories as $name) {
    Category::query()->firstOrCreate([
        'name' => $name,
        'parent_id' => null,
    ]);
}
        });
    }
}
