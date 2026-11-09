<?php

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

final class StockService
{
    /** @var numeric-string */
    private const MAX_QTY = '999999999.999';

    /** @var numeric-string */
    private const MAX_UNIT_COST = '99999999.9999';

    public function __construct(
        private TenantContext $tenant
    ) {}

    public function openingBalance(
        Product|int $product,
        string|int|float $qty,
        string|int|float $unitCost,
        ?string $notes = null,
    ): StockMovement {
        return $this->record(
            $product,
            StockMovementType::Opening,
            $this->positiveQuantity($qty),
            $this->unitCost($unitCost),
            null,
            $notes,
        );
    }

    /**
     * $originalUnitCost = unit_cost of the original sale item.
     */
    public function saleReturn(
        Product|int $product,
        string|int|float $qty,
        string|int|float $originalUnitCost,
        ?Model $reference = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->record(
            $product,
            StockMovementType::SaleReturn,
            $this->positiveQuantity($qty),
            $this->unitCost($originalUnitCost),
            $reference,
            $notes,
        );
    }

    public function purchaseReturn(
        Product|int $product,
        string|int|float $qty,
        ?Model $reference = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->record(
            $product,
            StockMovementType::PurchaseReturn,
            bcsub('0', $this->positiveQuantity($qty), 3),
            null,
            $reference,
            $notes,
        );
    }

    /**
     * Positive quantity = increase.
     * Negative quantity = decrease.
     */
    public function adjust(
        Product|int $product,
        string|int|float $signedQty,
        string $notes,
    ): StockMovement {
        $quantity = $this->quantity($signedQty, allowNegative: true);

        if (bccomp($quantity, '0', 3) === 0) {
            throw new InvalidArgumentException(
                'Adjustment quantity cannot be zero.'
            );
        }

        return $this->record(
            $product,
            StockMovementType::Adjustment,
            $quantity,
            null,
            null,
            $notes,
        );
    }

    public function purchase(
        Product|int $product,
        string|int|float $qty,
        string|int|float $unitCost,
        ?Model $reference = null,
        ?string $notes = null,
        ?int $userId = null,
    ): StockMovement {
        return $this->record(
            $product,
            StockMovementType::Purchase,
            $this->positiveQuantity($qty),
            $this->unitCost($unitCost),
            $reference,
            $notes,
            $userId,
        );
    }

    public function sale(
        Product|int $product,
        string|int|float $qty,
        ?Model $reference = null,
        ?string $notes = null,
        ?int $userId = null,
    ): StockMovement {
        return $this->record(
            $product,
            StockMovementType::Sale,
            bcsub('0', $this->positiveQuantity($qty), 3),
            null,
            $reference,
            $notes,
            $userId,
        );
    }

    /**
     * @param numeric-string $signedQty
     * @param numeric-string|null $unitCost
     */
    private function record(
        Product|int $product,
        StockMovementType $type,
        string $signedQty,
        ?string $unitCost,
        ?Model $reference,
        ?string $notes,
        ?int $userId = null,
    ): StockMovement {
        $productId = $product instanceof Product
            ? (int) $product->getKey()
            : $product;

        $shopId = $this->tenant->idOrFail();

        // Ensure the reference belongs to the current shop.
        if (
            $reference !== null
            && $reference->offsetExists('shop_id')
            && (int) $reference->getAttribute('shop_id') !== $shopId
        ) {
            throw new InvalidArgumentException(
                'Stock movement reference belongs to another shop.'
            );
        }

        return DB::transaction(function () use (
            $productId,
            $shopId,
            $type,
            $signedQty,
            $unitCost,
            $reference,
            $notes,
            $userId,
        ): StockMovement {

            // Lock product row to prevent concurrent stock modifications.
            $locked = Product::query()
                ->lockForUpdate()
                ->findOrFail($productId);

            $oldQty = Money::norm($locked->stock_qty);
            $oldAvg = Money::norm($locked->avg_cost);

            $newQty = bcadd($oldQty, $signedQty, 3);

            if (
                bccomp($newQty, self::MAX_QTY, 3) === 1
                || bccomp($newQty, '-' . self::MAX_QTY, 3) === -1
            ) {
                throw new InvalidArgumentException(
                    'Stock quantity exceeds the database column range.'
                );
            }

            // Prevent negative stock when shop settings disallow it.
            if (
                bccomp($signedQty, '0', 3) === -1
                && bccomp($newQty, '0', 3) === -1
                && ! $this->allowsNegativeStock($shopId)
            ) {
                throw InsufficientStockException::for(
                    $productId,
                    (float) $oldQty,
                    (float) ltrim($signedQty, '-'),
                );
            }

            if ($type->entersAverage()) {
                $cost = $unitCost
                    ?? throw new InvalidArgumentException(
                        'A unit cost is required for this stock movement.'
                    );

                if (bccomp($signedQty, '0', 3) !== 1) {
                    throw new InvalidArgumentException(
                        'Average-cost movements must have a positive quantity.'
                    );
                }

                // Ignore negative stock when calculating weighted average.
                $base = bccomp($oldQty, '0', 3) > 0
                    ? $oldQty
                    : '0.000';

                $denominator = bcadd($base, $signedQty, 3);

                if (bccomp($denominator, '0', 3) !== 1) {
                    throw new InvalidArgumentException(
                        'Cannot compute average cost with a non-positive quantity.'
                    );
                }

                $weightedBase = bcmul($base, $oldAvg, 8);
                $weightedIncoming = bcmul($signedQty, $cost, 8);

                $weightedTotal = bcadd(
                    $weightedBase,
                    $weightedIncoming,
                    8
                );

                $newAvg = Money::round(
                    bcdiv($weightedTotal, $denominator, 8),
                    4
                );

                if (bccomp($newAvg, self::MAX_UNIT_COST, 4) === 1) {
                    throw new InvalidArgumentException(
                        'Resulting average cost exceeds the database column range.'
                    );
                }
            } else {
                $cost = $unitCost ?? $oldAvg;
                $newAvg = $oldAvg;
            }

            // Update product stock.
            $locked->forceFill([
                'stock_qty' => $newQty,
                'avg_cost' => $newAvg,
            ]);

            if ($type->updatesLastCost()) {
                $locked->forceFill([
                    'last_cost' => $cost,
                ]);
            }

            $locked->save();

            // Record the movement and explicitly track its actor.
            $movement = new StockMovement([
                'product_id' => $locked->id,
                'user_id' => $userId ?? auth()->id(),
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

        return (bool) (
            $shop?->setting('allow_negative_stock', false) ?? false
        );
    }

    /** @return numeric-string */
    private function positiveQuantity(string|int|float $value): string
    {
        $quantity = $this->quantity($value, allowNegative: false);

        if (bccomp($quantity, '0', 3) !== 1) {
            throw new InvalidArgumentException(
                'Quantity must be greater than zero.'
            );
        }

        return $quantity;
    }

    /** @return numeric-string */
    private function quantity(
        string|int|float $value,
        bool $allowNegative
    ): string {
        $raw = Money::norm($value);

        if (preg_match('/\A-?\d+(?:\.\d+)?\z/', $raw) !== 1) {
            throw new InvalidArgumentException('Invalid quantity.');
        }

        $unsigned = str_starts_with($raw, '-')
            ? substr($raw, 1)
            : $raw;

        $fraction = explode('.', $unsigned, 2)[1] ?? '';

        if (
            strlen($fraction) > 3
            || (! $allowNegative && str_starts_with($raw, '-'))
        ) {
            throw new InvalidArgumentException(
                'Quantity must have at most three decimal places.'
            );
        }

        $quantity = bcadd($raw, '0', 3);

        if (
            bccomp($quantity, self::MAX_QTY, 3) === 1
            || bccomp($quantity, '-' . self::MAX_QTY, 3) === -1
        ) {
            throw new InvalidArgumentException(
                'Quantity exceeds the database column range.'
            );
        }

        return $quantity;
    }

    /** @return numeric-string */
    private function unitCost(string|int|float $value): string
    {
        $raw = Money::norm($value);

        if (preg_match('/\A\d+(?:\.\d+)?\z/', $raw) !== 1) {
            throw new InvalidArgumentException(
                'Unit cost must be a non-negative decimal value.'
            );
        }

        $fraction = explode('.', $raw, 2)[1] ?? '';

        if (
            strlen($fraction) > 4
            || bccomp($raw, self::MAX_UNIT_COST, 4) === 1
        ) {
            throw new InvalidArgumentException(
                'Unit cost exceeds the database column range or precision.'
            );
        }

        return bcadd($raw, '0', 4);
    }
}
