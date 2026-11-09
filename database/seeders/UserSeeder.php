<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()
            ->where('slug', 'default-shop')
            ->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            $user = User::query()->firstOrNew([
                'email' => 'owner@default.test',
            ]);

            $user->forceFill([
                'name' => 'Default Owner',
                'password' => Hash::make('password'),
                'shop_id' => $shop->id,
                'role' => UserRole::Owner,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->save();
        });
    }
}
