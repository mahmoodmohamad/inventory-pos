<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

abstract class ShopPolicy
{
    protected function sameShop(User $user, Model $model): Response
{
    if (! $user->is_active) {
        return Response::deny();
    }

    return $user->shop_id !== null
        && (int) $user->shop_id === (int) $model->getAttribute('shop_id')
            ? Response::allow()
            : Response::denyAsNotFound();
}

    protected function hasRole(User $user, UserRole ...$roles): bool
{
    // A user without a shop can't act on any shop resource.
    if (! $user->is_active || $user->shop_id === null) {
        return false;
    }

    foreach ($roles as $role) {
        if ($user->role === $role) {
            return true;
        }
    }

    return false;
}
}
