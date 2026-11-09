<?php

declare(strict_types=1);

namespace App\Services;

use App\Livewire\Forms\ProductForm;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ProductSaveService
{
    public function __construct(
        private StockService $stock
    ) {}

      public function save(User $actor, ProductForm $form, ?Product $existing, ?Product $source): Product
  {
      Gate::forUser($actor)->authorize($existing !== null ? 'update' : 'create', $existing ?? Product::class);


        // Authorization
        if ($existing !== null) {
            Gate::forUser($actor)->authorize('update', $existing);
        } else {
            Gate::forUser($actor)->authorize('create', Product::class);
        }

        return DB::transaction(function () use (
            $actor,
            $form,
            $existing,
            $source
        ): Product {

            // Resolve brand
            $brandId = filled($form->brand_id)
                ? (int) $form->brand_id
                : null;

            if (filled($form->newBrand)) {
                $brandId = Brand::query()
                    ->firstOrCreate([
                        'name' => trim($form->newBrand),
                    ])->id;
            }

            // Build product payload
            $data = $form->payload($brandId);

            // Update existing product
            if ($existing !== null) {
                $existing->update($data);

                $this->applyStockCorrection(
                    $actor,
                    $form,
                    $existing
                );

                return $existing->refresh();
            }

            // Create new product
            $created = Product::query()->create($data);

            // Copy vehicle fitments
            if ($source !== null && $form->copyFitments) {
                $source->fitments()
                    ->pluck('vehicle_variant_id')
                    ->each(
                        fn ($variantId) => $created->fitments()
                            ->firstOrCreate([
                                'vehicle_variant_id' => $variantId,
                            ])
                    );
            }

            // Opening stock balance
            // Decimal values are passed as strings (no floats).
            if (Money::cmp($form->opening_qty, '0', 3) > 0) {
                $this->stock->openingBalance(
                    $created,
                    trim($form->opening_qty),
                    trim($form->opening_cost),
                    'رصيد افتتاحي'
                );
            }

            return $created;
        });
    }

    private function applyStockCorrection(
        User $actor,
        ProductForm $form,
        Product $product
    ): void {

        if (
            trim($form->new_stock_qty) === '' ||
            ! Gate::forUser($actor)->allows('set-stock')
        ) {
            return;
        }

        // Lock product row to prevent concurrent stock modifications.
        $current = Money::norm(
            Product::query()
                ->lockForUpdate()
                ->findOrFail($product->id)
                ->stock_qty
        );

        $delta = Money::sub(
            trim($form->new_stock_qty),
            $current,
            3
        );

        if (Money::cmp($delta, '0', 3) === 0) {
            return;
        }

        $this->stock->adjust(
            $product,
            $delta,
            filled($form->stock_note)
                ? trim($form->stock_note)
                : 'تعديل الكمية من شاشة المنتج'
        );
    }
    
}
