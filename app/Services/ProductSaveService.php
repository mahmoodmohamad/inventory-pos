<?php

declare(strict_types=1);

namespace App\Services;

use App\Livewire\Forms\ProductForm;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ProductSaveService
{
    public function __construct(private StockService $stock) {}

    public function save(User $actor, ProductForm $form, ?Product $existing, ?Product $source): Product
    {
        return DB::transaction(function () use ($actor, $form, $existing, $source): Product {
            $brandId = filled($form->brand_id) ? (int) $form->brand_id : null;

            if (filled($form->newBrand)) {
                $brandId = Brand::query()->firstOrCreate(['name' => trim($form->newBrand)])->id;
            }

            $data = $form->payload($brandId);

            if ($existing !== null) {
                $existing->update($data);
                $this->applyStockCorrection($actor, $form, $existing);

                return $existing->refresh();
            }

            $created = Product::query()->create($data);

            if ($source !== null && $form->copyFitments) {
                $source->fitments()->pluck('vehicle_variant_id')->each(
                    fn ($variantId) => $created->fitments()->firstOrCreate(['vehicle_variant_id' => $variantId]),
                );
            }

            if ((float) $form->opening_qty > 0) {
                $this->stock->openingBalance($created, (float) $form->opening_qty, (float) $form->opening_cost, 'رصيد افتتاحي');
            }

            return $created;
        });
    }

    private function applyStockCorrection(User $actor, ProductForm $form, Product $product): void
    {
        if ($form->new_stock_qty === '' || ! Gate::forUser($actor)->allows('set-stock')) {
            return;
        }

        $current = (float) Product::query()->lockForUpdate()->findOrFail($product->id)->stock_qty;
        $delta = round((float) $form->new_stock_qty - $current, 3);

        if ($delta == 0.0) {
            return;
        }

        $this->stock->adjust(
            $product,
            $delta,
            filled($form->stock_note) ? trim($form->stock_note) : 'تعديل الكمية من شاشة المنتج',
        );
    }
}
