<?php

// app/Policies/ProductPolicy.php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->shop_id !== null && $user->is_active;
    }

    public function view(User $user, Product $product): Response
    {
        return $this->sameShop($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function update(User $user, Product $product): Response
    {
        $same = $this->sameShop($user, $product);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager)
            ? Response::allow()
            : Response::deny();
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->update($user, $product);
    }

    /** تسوية المخزون (جرد / تالف) */
    public function adjustStock(User $user, Product $product): Response
    {
        return $this->update($user, $product);
    }
}
