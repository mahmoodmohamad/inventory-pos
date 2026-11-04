<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class StockMovementPolicy extends ShopPolicy
{
    /** السجل فيه تكاليف، فمقصور على Owner/Manager */
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, UserRole::Owner, UserRole::Manager);
    }
}
