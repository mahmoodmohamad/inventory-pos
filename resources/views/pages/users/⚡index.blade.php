<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ShopUserService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'cashier';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    #[Computed]
    public function users()
    {
        return app(ShopUserService::class)->listFor(Auth::user());
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)->except(UserRole::Owner)],
        ]);

        app(ShopUserService::class)->create(Auth::user(), [
            ...$validated,
            'role' => UserRole::from($validated['role']),
        ]);

        $this->reset('name', 'email', 'password');
        unset($this->users);

        Flux::toast(variant: 'success', text: __('User created.'));
    }

    public function toggleActive(int $id): void
    {
        $service = app(ShopUserService::class);
        $target = $service->findFor(Auth::user(), $id);

        $service->setActive(Auth::user(), $target, ! $target->is_active);
        unset($this->users);
    }

    public function changeRole(int $id, string $role): void
    {
        $service = app(ShopUserService::class);
        $target = $service->findFor(Auth::user(), $id);

        $service->changeRole(Auth::user(), $target, UserRole::from($role));
        unset($this->users);
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>

    <form wire:submit="create" class="mt-6 grid gap-4 max-w-lg">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="email" :label="__('Email')" type="email" required />
        <flux:input wire:model="password" :label="__('Password')" type="password" required viewable />

        <flux:select wire:model="role" :label="__('Role')">
            <flux:select.option value="cashier">{{ __('Cashier') }}</flux:select.option>
            @can('canManageRole', [App\Models\User::class, App\Enums\UserRole::Manager])
                <flux:select.option value="manager">{{ __('Manager') }}</flux:select.option>
            @endcan
        </flux:select>

        <flux:button type="submit" variant="primary">{{ __('Add user') }}</flux:button>
    </form>

    <div class="mt-10 space-y-2">
        @foreach ($this->users as $user)
            <div wire:key="user-{{ $user->id }}" class="flex items-center justify-between rounded-lg border p-4 border-zinc-200 dark:border-zinc-700">
                <div>
                    <div class="font-medium">{{ $user->name }}</div>
                    <flux:text class="text-xs">{{ $user->email }} · {{ $user->role->value }}</flux:text>
                </div>

                @can('update', $user)
                    <flux:button size="sm" variant="{{ $user->is_active ? 'danger' : 'primary' }}" wire:click="toggleActive({{ $user->id }})">
                        {{ $user->is_active ? __('Disable') : __('Enable') }}
                    </flux:button>
                @endcan
            </div>
        @endforeach
    </div>
</section>