<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerBlueprintMacros();
        $this->registerGates();
    }

    protected function registerGates(): void
    {

        Gate::define('view-financials', fn (User $user): bool => $user->is_active
            && in_array(
                $user->role,
                [UserRole::Owner, UserRole::Manager],
                true,
            )
        );

        Gate::define('manage-shop', fn (User $user): bool => $user->is_active
            && $user->role === UserRole::Owner
        );

        Gate::define('set-stock', fn (User $user): bool => $user->is_active
            && $user->role === UserRole::Owner
        );

        // صلاحية منفصلة: تغيير سعر البيع يدويًا
        Gate::define('override-price', fn (User $user): bool => $user->is_active
            && in_array(
                $user->role,
                [UserRole::Owner, UserRole::Manager],
                true,
            )
        );
        Gate::define('manage-catalog', fn (User $user): bool => $user->is_active
    && $user->hasVerifiedEmail()
    && in_array(
        $user->role,
        [UserRole::Owner],
        true,
    )
);
    }

    protected function registerBlueprintMacros(): void
    {
        /**
         * Composite tenant FK: (shop_id, $column) -> ($on.shop_id, $on.id), RESTRICT دايمًا.
         * لازم عمود shop_id يتعرّف قبلها في نفس الجدول.
         */
        Blueprint::macro('tenantForeign', function (string $column, ?string $on = null, bool $nullable = false): ColumnDefinition {
            /** @var Blueprint $this */
            $on ??= Str::plural(Str::beforeLast($column, '_id'));

            $definition = $this->unsignedBigInteger($column);

            if ($nullable) {
                $definition->nullable();
            }

            $this->foreign(['shop_id', $column])
                ->references(['shop_id', 'id'])
                ->on($on)
                ->restrictOnDelete();

            return $definition;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
