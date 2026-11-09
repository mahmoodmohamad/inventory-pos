<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ShopRegistrationService
{
    /**
     * @param array{
     *     shop_name: string,
     *     name: string,
     *     email: string,
     *     password: string
     * } $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => $this->uniqueSlug($data['shop_name']),
                'settings' => [
                    'allow_negative_stock' => false,
                    'storefront_enabled' => false,
                ],
            ]);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->forceFill([
                'shop_id' => $shop->id,
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);

            $user->save();

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        // الاسم العربي ممكن ينتج slug فارغ، لذلك نستخدم قيمة بديلة.
        $base = Str::limit(
            Str::slug($name) ?: 'shop',
            40,
            '',
        );

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Shop::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
