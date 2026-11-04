<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ProductCondition;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

final class ProductQuery
{
    public const SORTABLE = ['name', 'retail_price', 'wholesale_price', 'stock_qty', 'updated_at'];

    public const PER_PAGE = [15, 30, 50];

    /**
     * @param  array<string, string>  $f
     * @return Builder<Product>
     */
    public static function build(array $f, string $sortBy, string $sortDir): Builder
    {
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'name';
        $dir = $sortDir === 'desc' ? 'desc' : 'asc';

        return Product::query()
            ->with(['brand', 'category'])
            ->withCount('fitments')
            ->when(trim($f['search'] ?? '') !== '', fn ($q) => $q->search($f['search']))
            ->when(
                ProductCondition::tryFrom($f['condition'] ?? ''),
                fn ($q, ProductCondition $c) => $q->where('condition', $c->value),
            )
            ->when(($f['category'] ?? '') !== '', function ($q) use ($f): void {
                $id = (int) $f['category'];
                $q->whereIn('category_id', Category::query()->where('parent_id', $id)->pluck('id')->push($id));
            })
            ->when(($f['brand'] ?? '') !== '', fn ($q) => $q->where('brand_id', (int) $f['brand']))
            ->when(($f['stock'] ?? '') === 'low', fn ($q) => $q->lowStock())
            ->when(($f['stock'] ?? '') === 'out', fn ($q) => $q->where('stock_qty', '<=', 0))
            ->when(($f['stock'] ?? '') === 'in', fn ($q) => $q->where('stock_qty', '>', 0))
            ->when(($f['status'] ?? 'all') === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($f['status'] ?? 'all') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->tap(fn ($q) => self::applyVehicle($q, $f))
            ->orderBy($sortBy, $dir)
            ->orderBy('id');
    }

    /**
     * @param  Builder<Product>  $q
     * @param  array<string, string>  $f
     */
    private static function applyVehicle(Builder $q, array $f): void
    {
        $variant = $f['variant'] ?? '';
        $model = $f['model'] ?? '';
        $make = $f['make'] ?? '';

        match (true) {
            $variant !== '' => $q->whereHas('fitments', fn ($x) => $x->where('vehicle_variant_id', (int) $variant)),
            $model !== '' => $q->whereHas('fitments.variant', fn ($x) => $x->where('vehicle_model_id', (int) $model)),
            $make !== '' => $q->whereHas('fitments.variant.vehicleModel', fn ($x) => $x->where('vehicle_make_id', (int) $make)),
            default => null,
        };
    }
}
