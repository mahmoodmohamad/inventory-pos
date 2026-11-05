<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the owner user for the default shop (production-safe).
 * Demo users live in DemoShopSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            User::query()->updateOrCreate(
                ['email' => 'owner@default.test'],
                [
                    'name' => 'Default Owner',
                    'password' => Hash::make('password'),
                    'shop_id' => $shop->id,
                    'role' => UserRole::Owner,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        });
    }
}
