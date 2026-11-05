<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SalePolicy extends ShopPolicy
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

    public function view(User $user, Sale $sale): Response
    {
        return $this->sameShop($user, $sale);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager, UserRole::Cashier);
    }

    public function update(User $user, Sale $sale): Response
    {
        $same = $this->sameShop($user, $sale);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager, UserRole::Cashier)
            ? Response::allow()
            : Response::deny();
    }

    public function complete(User $user, Sale $sale): Response
    {
        return $this->update($user, $sale);
    }

    public function delete(User $user, Sale $sale): Response
    {
        return $this->update($user, $sale);
    }
}
