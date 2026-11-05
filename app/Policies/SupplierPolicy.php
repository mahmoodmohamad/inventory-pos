<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupplierPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function view(User $user, Supplier $supplier): Response
    {
        return $this->guarded($user, $supplier);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function update(User $user, Supplier $supplier): Response
    {
        return $this->guarded($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): Response
    {
        return $this->guarded($user, $supplier);
    }

    private function guarded(User $user, Supplier $supplier): Response
    {
        $same = $this->sameShop($user, $supplier);

        if ($same->denied()) {
            return $same;
        }

        return $this->hasRole($user, UserRole::Owner, UserRole::Manager)
            ? Response::allow()
            : Response::deny();
    }
}
