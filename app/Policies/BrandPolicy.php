<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BrandPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->shop_id !== null && $user->is_active;
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function update(User $user, Brand $brand): Response
    {
        $same = $this->sameShop($user, $brand);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager)
            ? Response::allow()
            : Response::deny();
    }

    public function delete(User $user, Brand $brand): Response
    {
        return $this->update($user, $brand);
    }
}
