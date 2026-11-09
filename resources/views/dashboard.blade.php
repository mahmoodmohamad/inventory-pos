<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('أهلاً') }} {{ auth()->user()->name }}</flux:heading>
            <flux:subheading>
                {{ auth()->user()->shop?->name }} — {{ auth()->user()->role->value }}
            </flux:subheading>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text class="text-sm text-zinc-500">{{ __('عدد المستخدمين') }}</flux:text>
                <flux:heading size="2xl" class="mt-2">{{ auth()->user()->shop?->users()->count() ?? 0 }}</flux:heading>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text class="text-sm text-zinc-500">{{ __('عدد الفئات') }}</flux:text>
                <flux:heading size="2xl" class="mt-2">{{ \App\Models\Category::count() }}</flux:heading>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text class="text-sm text-zinc-500">{{ __('ماركات السيارات') }}</flux:text>
                <flux:heading size="2xl" class="mt-2">{{ \App\Models\VehicleMake::where('is_active', true)->count() }}</flux:heading>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('روابط سريعة') }}</flux:heading>
            <div class="mt-4 flex flex-wrap gap-3">
                <flux:button :href="route('vehicle-makes.index')" wire:navigate variant="filled" icon="truck">
                    {{ __('كتالوج السيارات') }}
                </flux:button>
                @can('viewAny', \App\Models\User::class)
                    <flux:button :href="route('users.index')" wire:navigate variant="filled" icon="users">
                        {{ __('إدارة المستخدمين') }}
                    </flux:button>
                @endcan
                @if (in_array(strtolower(auth()->user()->email), config('app.platform_admins', []), true))
                    <flux:button :href="route('catalog.vehicle-catalog')" wire:navigate variant="filled" icon="wrench">
                        {{ __('إدارة الكتالوج') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>