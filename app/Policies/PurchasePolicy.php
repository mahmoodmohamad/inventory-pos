<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchasePolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function view(User $user, Purchase $purchase): Response
    {
        $same = $this->sameShop($user, $purchase);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager)
            ? Response::allow()
            : Response::deny();
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }
}
