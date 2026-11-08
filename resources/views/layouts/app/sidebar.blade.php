<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">

        {{-- Sidebar Header --}}
        <flux:sidebar.header>
            <x-app-logo :sidebar="true"
                :href="route('dashboard')"
                wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        {{-- Main Navigation --}}
        <flux:sidebar.nav>

            {{-- Overview --}}
            <flux:sidebar.group heading="Overview" class="grid">
                <flux:sidebar.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    Dashboard
                </flux:sidebar.item>
            </flux:sidebar.group>

            {{-- Vehicle Management --}}
            <flux:sidebar.group heading="Vehicle Management" class="grid">

                <flux:sidebar.item
                    icon="tag"
                    :href="route('vehicle-makes.index')"
                    :current="request()->routeIs('vehicle-makes.*')"
                    wire:navigate>
                    Vehicle Makes
                </flux:sidebar.item>

                @can('manage-catalog')
                    <flux:sidebar.item
                        icon="squares-2x2"
                        :href="route('catalog.vehicle-variants')"
                        :current="request()->routeIs('catalog.vehicle-variants')"
                        wire:navigate>
                        Vehicle Variants
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="truck"
                        :href="route('catalog.vehicles')"
                        :current="request()->routeIs('catalog.vehicles')"
                        wire:navigate>
                        Vehicle Catalog
                    </flux:sidebar.item>
                @endcan

            </flux:sidebar.group>

            {{-- Inventory --}}
            <flux:sidebar.group heading="Inventory" class="grid">

                @can('viewAny', \App\Models\Product::class)
                    <flux:sidebar.item
                        icon="cube"
                        :href="route('products.index')"
                        :current="request()->routeIs('products.*')"
                        wire:navigate>
                        Products
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\Category::class)
                    <flux:sidebar.item
                        icon="rectangle-group"
                        :href="route('categories.index')"
                        :current="request()->routeIs('categories.*')"
                        wire:navigate>
                        Categories
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\Brand::class)
                    <flux:sidebar.item
                        icon="bookmark"
                        :href="route('brands.index')"
                        :current="request()->routeIs('brands.*')"
                        wire:navigate>
                        Brands
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\StockMovement::class)
                    <flux:sidebar.item
                        icon="arrows-right-left"
                        :href="route('stock-movements.index')"
                        :current="request()->routeIs('stock-movements.*')"
                        wire:navigate>
                        Stock Movements
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\Supplier::class)
                    <flux:sidebar.item
                        icon="truck"
                        :href="route('suppliers.index')"
                        :current="request()->routeIs('suppliers.*')"
                        wire:navigate>
                        Suppliers
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\Purchase::class)
                    <flux:sidebar.item
                        icon="shopping-cart"
                        :href="route('purchases.index')"
                        :current="request()->routeIs('purchases.*')"
                        wire:navigate>
                        Purchases
                    </flux:sidebar.item>
                @endcan

            </flux:sidebar.group>

            {{-- Sales --}}
            <flux:sidebar.group heading="Sales" class="grid">

                @can('viewAny', \App\Models\Sale::class)
                    <flux:sidebar.item
                        icon="document-text"
                        :href="route('sales.index')"
                        :current="request()->routeIs('sales.*')"
                        wire:navigate>
                        Invoices
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', \App\Models\Customer::class)
                    <flux:sidebar.item
                        icon="users"
                        :href="route('customers.index')"
                        :current="request()->routeIs('customers.*')"
                        wire:navigate>
                        Customers
                    </flux:sidebar.item>
                @endcan

            </flux:sidebar.group>

            {{-- Administration --}}
            <flux:sidebar.group heading="Administration" class="grid">

                @can('viewAny', \App\Models\User::class)
                    <flux:sidebar.item
                        icon="user-circle"
                        :href="route('users.index')"
                        :current="request()->routeIs('users.*')"
                        wire:navigate>
                        Users
                    </flux:sidebar.item>
                @endcan

                <flux:sidebar.item
                    icon="cog-6-tooth"
                    :href="route('profile.edit')"
                    :current="request()->routeIs('profile.*')"
                    wire:navigate>
                    Settings
                </flux:sidebar.item>

            </flux:sidebar.group>

        </flux:sidebar.nav>

        <flux:spacer />

        {{-- Desktop User Menu --}}
        <x-desktop-user-menu
            class="hidden lg:block"
            :name="auth()->user()->name"
        />

    </flux:sidebar>

    {{-- Mobile Header --}}
    <flux:header class="lg:hidden">

        <flux:sidebar.toggle
            class="lg:hidden"
            icon="bars-2"
            inset="left"
        />

        <flux:spacer />

        <flux:dropdown position="top" align="end">

            <flux:profile
                :initials="auth()->user()->initials()"
                icon-trailing="chevron-down"
            />

            <flux:menu>

                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">

                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">
                                    {{ auth()->user()->name }}
                                </flux:heading>

                                <flux:text class="truncate">
                                    {{ auth()->user()->email }}
                                </flux:text>
                            </div>

                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.item
                    :href="route('profile.edit')"
                    icon="cog-6-tooth"
                    wire:navigate>
                    Settings
                </flux:menu.item>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf

                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button">
                        Log out
                    </flux:menu.item>
                </form>

            </flux:menu>

        </flux:dropdown>

    </flux:header>

    {{-- Page Content --}}
    {{ $slot }}

    {{-- Toast Notifications --}}
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts

</body>
</html>