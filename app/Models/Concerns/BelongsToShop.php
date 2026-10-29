<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantViolationException;
use App\Models\Scopes\ShopScope;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopScope);

        static::creating(function (Model $model): void {
            $tenant = app(TenantContext::class);

            if ($model->getAttribute('shop_id') === null) {
                $model->setAttribute('shop_id', $tenant->idOrFail());

                return;
            }

            // shop_id صريح + سياق معروف + مش متطابقين = محاولة كتابة في محل تاني
            if ($tenant->hasShop() && (int) $model->getAttribute('shop_id') !== $tenant->id()) {
                throw TenantViolationException::for('cross-tenant create', $model);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('shop_id')) {
                throw TenantViolationException::for('shop_id is immutable', $model);
            }

            static::assertOwnedByCurrentTenant($model);
        });

        static::deleting(fn (Model $model) => static::assertOwnedByCurrentTenant($model));
    }

    protected static function assertOwnedByCurrentTenant(Model $model): void
    {
        $tenant = app(TenantContext::class);

        // حتى لو الـ scope اتعطّل (withoutGlobalScopes) الكتابة على سجل محل تاني ممنوعة
        if ($tenant->hasShop() && (int) $model->getAttribute('shop_id') !== $tenant->id()) {
            throw TenantViolationException::for('record belongs to another shop', $model);
        }
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
