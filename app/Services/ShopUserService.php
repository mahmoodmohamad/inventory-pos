<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

final class ShopUserService
{
    /** @return Collection<int, User> */
    public function listFor(User $actor): Collection
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::query()
            ->where('shop_id', $actor->shop_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, password: string, role: UserRole}  $data
     */
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        abort_unless(app(UserPolicy::class)->canManageRole($actor, $data['role']), 403);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->forceFill([
            'shop_id' => $actor->shop_id, // دايمًا محل الـ actor، عمره ما يجي من الـ client
            'role' => $data['role'],
            'is_active' => true,
        ])->save();

        return $user;
    }

    public function changeRole(User $actor, User $target, UserRole $role): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        abort_unless(app(UserPolicy::class)->canManageRole($actor, $role), 403);

        $target->forceFill(['role' => $role])->save();

        return $target;
    }

    public function setActive(User $actor, User $target, bool $active): User
    {
        Gate::forUser($actor)->authorize('toggleActive', $target);

        $target->forceFill(['is_active' => $active])->save();

        return $target;
    }

    /** لازم التارجت يتجاب من محل الـ actor بس، وإلا 404 */
    public function findFor(User $actor, int $id): User
    {
        return User::query()
            ->where('shop_id', $actor->shop_id)
            ->findOrFail($id);
    }
}
