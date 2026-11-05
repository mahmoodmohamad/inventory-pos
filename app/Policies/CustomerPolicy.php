<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->shop_id !== null
            && in_array($user->role, [
                UserRole::Owner,
                UserRole::Manager,
                UserRole::Cashier,
            ], true);
    }

    public function view(User $user, Customer $customer): Response
    {
        $same = $this->sameShop($user, $customer);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager, UserRole::Cashier)
            ? Response::allow()
            : Response::deny();
    }
}
