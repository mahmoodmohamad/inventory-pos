<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentShop
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shopId = $request->user()?->shop_id;

        // set أو forget دايمًا، عشان ما يفضلش سياق قديم من request سابق
        $shopId ? $this->tenant->set($shopId) : $this->tenant->forget();

        return $next($request);
    }
}
