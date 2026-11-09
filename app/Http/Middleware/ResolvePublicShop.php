<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicShop
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = Shop::query()
            ->where('slug', (string) $request->route('shopSlug'))
            ->where('is_active', true)
            ->first();

        // نفس الرد للمحل غير الموجود والمعطّل
        abort_unless(
            $shop !== null && $shop->setting('storefront_enabled', false),
            404
        );

        // الواجهة العامة تستخدم سياق المحل الموجود في الرابط
        $this->tenant->set($shop);

        $request->attributes->set('storefront_shop', $shop);

        return $next($request);
    }
}
