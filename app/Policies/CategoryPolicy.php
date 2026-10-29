<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->shop_id !== null && $user->is_active;
    }

    public function view(User $user, Category $category): Response
    {
        return $this->sameShop($user, $category);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function update(User $user, Category $category): Response
    {
        $same = $this->sameShop($user, $category);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager)
            ? Response::allow()
            : Response::deny();
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->update($user, $category);
    }
}
