<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ShopDashboardService
{
    /**
     * كل الاستعلامات على جداول tenant بتعدّي على ShopScope (محل الـ actor بس).
     * الـ users مفيهاش scope فبنفلتر بـ shop_id صراحةً.
     *
     * @return array<string, mixed>
     */
    public function summary(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', Product::class);

        $active = fn () => Product::query()->where('is_active', true);

        $summary = [
            'products_count' => $active()->count(),
            'out_of_stock_count' => $active()->where('stock_qty', '<=', 0)->count(),
            'low_stock_count' => $active()->lowStock()->count(),
            'categories_count' => Category::query()->count(),

            // `condition` كلمة محجوزة في MySQL فلازم backticks
            'by_condition' => $active()
                ->selectRaw('`condition`, count(*) as total')
                ->groupBy('condition')
                ->toBase()
                ->pluck('total', 'condition'),

            'team' => User::query()
                ->where('shop_id', $actor->shop_id)
                ->where('is_active', true)
                ->selectRaw('role, count(*) as total')
                ->groupBy('role')
                ->toBase()
                ->pluck('total', 'role'),

            'low_stock' => $active()
                ->lowStock()
                ->with('brand')
                ->orderByRaw('stock_qty - min_stock_alert')
                ->limit(8)
                ->get(),

            'recent_movements' => StockMovement::query()
                ->with(['product', 'user'])
                ->latest('id')
                ->limit(8)
                ->get(),

            'financials' => null,
        ];

        if (Gate::forUser($actor)->allows('view-financials')) {
            $summary['financials'] = [
                // الرصيد السالب مش بيتحسب في قيمة المخزون
                'inventory_cost' => (float) Product::query()
                    ->where('stock_qty', '>', 0)
                    ->sum(DB::raw('stock_qty * avg_cost')),
                'inventory_retail' => (float) Product::query()
                    ->where('stock_qty', '>', 0)
                    ->sum(DB::raw('stock_qty * retail_price')),
            ];
        }

        return $summary;
    }
}
