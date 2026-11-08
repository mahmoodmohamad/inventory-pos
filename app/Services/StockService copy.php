<?php

// app/Services/StockService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * الوحيد المسموح له يغيّر stock_qty / avg_cost / last_cost.
 * كل method بتشتغل جوه transaction وبتقفل صف المنتج (lockForUpdate).
 *
 * كل الحسابات بـ bcmath (عن طريق Money)، ومفيش floats:
 * - الكمية: بحد أقصى 3 منازل عشرية (DECIMAL(12,3)).
 * - التكلفة: غير سالبة، بحد أقصى 4 منازل عشرية (DECIMAL(12,4)).
 * الـ int/float بتتقبل للتوافق، لكن بتتحوّل لنص عشري، وأي قيمة أدق من العمود بتترفض بدل ما تتقرّب بصمت.
 */
final class StockService
{
    private const MAX_QTY = '999999999.999';

    private const MAX_COST = '99999999.9999';

    public function __construct(private TenantContext $tenant) {}

    public function openingBalance(Product|int $product, int|float|string $qty, int|float|string $unitCost, ?string $notes = null): StockMovement
    {
        $qty = $this->positiveQty($qty);

        return $this->record($product, StockMovementType::Opening, $qty, $this->cost($unitCost), null, $notes);
    }

    public function purchase(Product|int $product, int|float|string $qty, int|float|string $unitCost, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $qty = $this->positiveQty($qty);

        return $this->record($product, StockMovementType::Purchase, $qty, $this->cost($unitCost), $reference, $notes);
    }

    /** التكلفة = لقطة من المتوسط الحالي (بتتحفظ في sale_items.unit_cost) */
    public function sale(Product|int $product, int|float|string $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $qty = $this->positiveQty($qty);

        return $this->record($product, StockMovementType::Sale, Money::mul($qty, '-1', 3), null, $reference, $notes);
    }

    /** $originalUnitCost = unit_cost بتاع سطر الفاتورة الأصلي */
    public function saleReturn(Product|int $product, int|float|string $qty, int|float|string $originalUnitCost, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $qty = $this->positiveQty($qty);

        return $this->record($product, StockMovementType::SaleReturn, $qty, $this->cost($originalUnitCost), $reference, $notes);
    }

    public function purchaseReturn(Product|int $product, int|float|string $qty, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        $qty = $this->positiveQty($qty);

        return $this->record($product, StockMovementType::PurchaseReturn, Money::mul($qty, '-1', 3), null, $reference, $notes);
    }

    /** $signedQty موجب = زيادة، سالب = عجز. بتتسجّل بالمتوسط الحالي */
    public function adjust(Product|int $product, int|float|string $signedQty, string $notes): StockMovement
    {
        $signedQty = $this->qty($signedQty);

        if (bccomp($signedQty, '0', 3) === 0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        return $this->record($product, StockMovementType::Adjustment, $signedQty, null, null, $notes);
    }

    /**
     * @param  numeric-string  $signedQty
     * @param  numeric-string|null  $unitCost
     */
    private function record(
        Product|int $product,
        StockMovementType $type,
        string $signedQty,
        ?string $unitCost,
        ?Model $reference,
        ?string $notes,
    ): StockMovement {
        $productId = $product instanceof Product ? (int) $product->getKey() : $product;
        $shopId = $this->tenant->idOrFail();

        return DB::transaction(function () use ($productId, $shopId, $type, $signedQty, $unitCost, $reference, $notes): StockMovement {
            // الـ ShopScope بيضمن إن المنتج من نفس المحل (غير كده ModelNotFoundException)
            $locked = Product::query()->lockForUpdate()->findOrFail($productId);

            $oldQty = Money::norm($locked->stock_qty);
            $oldAvg = Money::norm($locked->avg_cost);
            $newQty = Money::add($oldQty, $signedQty, 3);

            if (bccomp(ltrim($newQty, '-'), self::MAX_QTY, 3) > 0) {
                throw new InvalidArgumentException('Resulting stock quantity is out of range.');
            }

            $outgoing = bccomp($signedQty, '0', 3) < 0;

            if ($outgoing && bccomp($newQty, '0', 3) < 0 && ! $this->allowsNegativeStock($shopId)) {
                throw InsufficientStockException::for($productId, (float) $oldQty, abs((float) $signedQty));
            }

            if ($type->entersAverage()) {
                $cost = $unitCost ?? throw new InvalidArgumentException('Unit cost is required for this movement.');

                // الرصيد السالب بيتحسب صفر في المتوسط
                $base = bccomp($oldQty, '0', 3) > 0 ? $oldQty : '0';
                $totalValue = Money::add(Money::mul($base, $oldAvg, 10), Money::mul($signedQty, $cost, 10), 10);
                $totalQty = Money::add($base, $signedQty, 3);

                $newAvg = Money::round(bcdiv($totalValue, $totalQty, 10), 4);
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

    // ---------- normalization ----------

    /** @return numeric-string */
    private function decimalString(int|float|string $value): string
    {
        // number_format بيمنع الصيغة العلمية، و Money::norm بيرفض أي حاجة مش رقم عشري عادي
        return Money::norm(is_float($value) ? number_format($value, 10, '.', '') : $value);
    }

    /**
     * كمية موقّعة بدقة 3 منازل بالظبط. أي دقة أعلى بتترفض بدل ما تتقرّب.
     *
     * @return numeric-string
     */
    private function qty(int|float|string $value): string
    {
        $raw = $this->decimalString($value);
        $qty = bcadd($raw, '0', 3);

        if (bccomp($qty, $raw, 10) !== 0) {
            throw new InvalidArgumentException('Quantity supports at most 3 decimal places.');
        }

        return $qty;
    }

    /** @return numeric-string */
    private function positiveQty(int|float|string $value): string
    {
        $qty = $this->qty($value);

        if (bccomp($qty, '0', 3) <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $qty;
    }

    /**
     * تكلفة وحدة: غير سالبة، بحد أقصى 4 منازل، وفي حدود DECIMAL(12,4).
     *
     * @return numeric-string
     */
    private function cost(int|float|string $value): string
    {
        $raw = $this->decimalString($value);
        $cost = bcadd($raw, '0', 4);

        if (bccomp($raw, '0', 10) < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }

        if (bccomp($cost, $raw, 10) !== 0) {
            throw new InvalidArgumentException('Unit cost supports at most 4 decimal places.');
        }

        if (bccomp($cost, self::MAX_COST, 4) > 0) {
            throw new InvalidArgumentException('Unit cost is out of range.');
        }

        return $cost;
    }
}
