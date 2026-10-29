<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy extends ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }

    public function update(User $user, User $target): Response
    {
        $same = $this->sameShop($user, $target);

        if ($same->denied()) {
            return $same;
        }

        if ($user->is($target) || $target->role === UserRole::Owner) {
            return Response::deny();
        }

        return $this->canManageRole($user, $target->role)
            ? Response::allow()
            : Response::deny();
    }

    /** نفس شروط update: التعطيل = تعديل حالة الحساب */
    public function toggleActive(User $user, User $target): Response
    {
        return $this->update($user, $target);
    }

    /** الأدوار اللي المستخدم ده يقدر يسندها لغيره */
    public function canManageRole(User $user, UserRole $role): bool
    {
        return match ($role) {
            UserRole::Owner => false,
            UserRole::Manager => $this->hasRole($user, UserRole::Owner),
            UserRole::Cashier => $this->hasRole($user, UserRole::Owner, UserRole::Manager),
        };
    }
}
