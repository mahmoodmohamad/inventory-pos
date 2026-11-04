<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::updateOrCreate(
            ['slug' => 'demo-shop'],
            [
                'name' => 'محل قطع غيار تجريبي',
                'phone' => '01000000000',
                'address' => 'القاهرة، مصر',
                'currency' => 'EGP',
                'is_active' => true,
                'settings' => ['allow_negative_stock' => true, 'storefront_enabled' => true],
            ],
        );

        // كل اللي جوه run() بيشتغل بسياق المحل ده، فالـ ShopScope يلاقي tenant
        app(TenantContext::class)->run($shop, function () use ($shop): void {
            $users = [
                ['email' => 'owner@demo.test',   'name' => 'أحمد المالك',   'role' => UserRole::Owner],
                ['email' => 'manager@demo.test', 'name' => 'محمد المدير',   'role' => UserRole::Manager],
                ['email' => 'cashier@demo.test', 'name' => 'كريم الكاشير',  'role' => UserRole::Cashier],
            ];

            foreach ($users as $row) {
                User::updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name' => $row['name'],
                        'password' => Hash::make('password'),
                        'shop_id' => $shop->id,
                        'role' => $row['role'],
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ],
                );
            }

            $categories = ['محرك', 'فرامل', 'كهرباء', 'تعليق', 'تكييف', 'فلاتر'];
            foreach ($categories as $name) {
                Category::updateOrCreate(
                    ['shop_id' => $shop->id, 'name' => $name, 'parent_id' => null],
                    [],
                );
            }
        });
    }
}
