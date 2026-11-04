<?php

// app/Services/StockService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * الوحيد المسموح له يغيّر stock_qty / avg_cost / last_cost.
 * كل method بتشتغل جوه transaction وبتقفل صف المنتج (lockForUpdate).
 */
final class StockService
{
    public function __construct(private TenantContext $tenant) {}

    public function openingBalance(Product|int $product, float $qty, float $unitCost, ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return $this->record($product, StockMovementType::Opening, $qty, $unitCost, null, $notes);
    }

    public function purchase(Product|int $product, float $qty, float $unitCost, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return $this->record($product, StockMovementType::Purchase, $qty, $unitCost, $reference, $notes);
    }

    /** التكلفة = لقطة من المتوسط الحالي (بتتحفظ في sale_items.unit_cost) */
    public function sale(Product|int $product, float $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return $this->record($product, StockMovementType::Sale, -$qty, null, $reference, $notes);
    }

    /** $originalUnitCost = unit_cost بتاع سطر الفاتورة الأصلي */
    public function saleReturn(Product|int $product, float $qty, float $originalUnitCost, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return $this->record($product, StockMovementType::SaleReturn, $qty, $originalUnitCost, $reference, $notes);
    }

    public function purchaseReturn(Product|int $product, float $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $this->assertPositive($qty);

        return $this->record($product, StockMovementType::PurchaseReturn, -$qty, null, $reference, $notes);
    }

    /** $signedQty موجب = زيادة، سالب = عجز. بتتسجّل بالمتوسط الحالي */
    public function adjust(Product|int $product, float $signedQty, string $notes): StockMovement
    {
        if ($signedQty == 0.0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        return $this->record($product, StockMovementType::Adjustment, $signedQty, null, null, $notes);
    }

    private function record(
        Product|int $product,
        StockMovementType $type,
        float $signedQty,
        ?float $unitCost,
        ?Model $reference,
        ?string $notes,
    ): StockMovement {
        $productId = $product instanceof Product ? (int) $product->getKey() : $product;
        $shopId = $this->tenant->idOrFail();

        return DB::transaction(function () use ($productId, $shopId, $type, $signedQty, $unitCost, $reference, $notes): StockMovement {
            // الـ ShopScope بيضمن إن المنتج من نفس المحل (غير كده ModelNotFoundException)
            $locked = Product::query()->lockForUpdate()->findOrFail($productId);

            $oldQty = (float) $locked->stock_qty;
            $oldAvg = (float) $locked->avg_cost;
            $newQty = round($oldQty + $signedQty, 3);

            if ($signedQty < 0 && $newQty < 0 && ! $this->allowsNegativeStock($shopId)) {
                throw InsufficientStockException::for($productId, $oldQty, abs($signedQty));
            }

            if ($type->entersAverage()) {
                $cost = (float) $unitCost;
                // الرصيد السالب بيتحسب صفر في المتوسط
                $base = max($oldQty, 0.0);
                $newAvg = round((($base * $oldAvg) + ($signedQty * $cost)) / ($base + $signedQty), 4);
            } else {
                $cost = $unitCost ?? $oldAvg;
                $newAvg = $oldAvg;
            }

            $locked->forceFill([
                'stock_qty' => $newQty,
                'avg_cost' => $newAvg,
            ]);

            if ($type->updatesLastCost()) {
                $locked->forceFill(['last_cost' => $cost]);
            }

            $locked->save();

            $movement = new StockMovement([
                'product_id' => $locked->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $signedQty,
                'qty_after' => $newQty,
                'unit_cost' => $cost,
                'avg_cost_after' => $newAvg,
                'notes' => $notes,
            ]);

            if ($reference !== null) {
                $movement->reference()->associate($reference);
            }

            $movement->save();

            return $movement;
        });
    }

    private function allowsNegativeStock(int $shopId): bool
    {
        $shop = Shop::query()->find($shopId);

        return (bool) ($shop?->setting('allow_negative_stock', true) ?? true);
    }

    private function assertPositive(float $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }
}
