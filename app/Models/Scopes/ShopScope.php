<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class ShopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // fail-closed: من غير محل معروف بيرمي exception بدل ما يرجّع كل الداتا
        $builder->where(
            $model->qualifyColumn('shop_id'),
            app(TenantContext::class)->idOrFail()
        );
    }
}
