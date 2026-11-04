<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * يشتغل جوه سياق محل (ShopScope) ويرجّع الحقول العامة بس.
 * أي حقل جديد لازم يتضاف هنا صراحة، عشان التكلفة والجملة والكمية ما يتسربوش.
 */
final class StorefrontCatalog
{
    /**
     * @param  array{q?: string|null, condition?: string|null, make?: string|int|null, model?: string|int|null, variant?: string|int|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $condition = $filters['condition'] ?? null;
        $make = (int) ($filters['make'] ?? 0);
        $model = (int) ($filters['model'] ?? 0);
        $variant = (int) ($filters['variant'] ?? 0);

        return Product::query()
            ->with('brand')
            ->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->search($term))
            ->when(filled($condition), fn ($q) => $q->where('condition', $condition))
            // أخصّ فلتر عربية بس هو اللي يتطبّق
            ->when($variant > 0, fn ($q) => $q->whereHas(
                'fitments',
                fn ($f) => $f->where('vehicle_variant_id', $variant),
            ))
            ->when($variant === 0 && $model > 0, fn ($q) => $q->whereHas(
                'fitments.variant',
                fn ($v) => $v->where('vehicle_model_id', $model),
            ))
            ->when($variant === 0 && $model === 0 && $make > 0, fn ($q) => $q->whereHas(
                'fitments.variant.vehicleModel',
                fn ($m) => $m->where('vehicle_make_id', $make),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(fn (Product $p): array => $this->present($p));
    }

    /** @return Collection<int, VehicleMake> */
    public function makes(): Collection
    {
        return VehicleMake::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, VehicleModel> */
    public function modelsOf(int $makeId): Collection
    {
        return $makeId <= 0 ? new Collection : VehicleModel::query()
            ->where('vehicle_make_id', $makeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, VehicleVariant> */
    public function variantsOf(int $modelId): Collection
    {
        return $modelId <= 0 ? new Collection : VehicleVariant::query()
            ->where('vehicle_model_id', $modelId)
            ->where('is_active', true)
            ->orderBy('year_from')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    private function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'part_number' => $product->part_number,
            'oem_number' => $product->oem_number,
            'condition' => $product->condition->label(),
            'condition_key' => $product->condition->value,
            'brand' => $product->brand?->name,
            'unit' => $product->unit->label(),
            'price' => (float) $product->retail_price,
            'in_stock' => (float) $product->stock_qty > 0,
        ];
    }
}
