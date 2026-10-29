<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ShopRegistrationService
{
    /**
     * @param  array{shop_name: string, name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => $this->uniqueSlug($data['shop_name']),
            ]);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // role/shop_id مش fillable عمدًا، فبنحطهم يدويًا من الكود بس
            $user->forceFill([
                'shop_id' => $shop->id,
                'role' => UserRole::Owner,
                'is_active' => true,
            ])->save();

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        // الاسم العربي ممكن يطلع slug فاضي، فبنحط fallback
        $base = Str::limit(Str::slug($name) ?: 'shop', 40, '');

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Shop::where('slug', $slug)->exists());

        return $slug;
    }
}
