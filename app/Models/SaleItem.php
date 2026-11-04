<?php

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductUnit;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'barcode',
        'part_number',
        'oem_number',
        'condition',
        'unit',
        'quantity',
        'original_unit_price',
        'unit_price',
        'price_overridden_by',
        'discount_amount',
        'line_total',
        'unit_cost',
    ];

    protected static function booted(): void
    {
        // البنود بتتغيّر والفاتورة مسودة بس.
        $guard = function (SaleItem $item): void {
            /** @var Sale|null $sale */
            $sale = Sale::query()->whereKey($item->sale_id)->first();

            // فاتورة محل تاني أو مش موجودة: نسيب الـ composite FK يرفض في الـ DB.
            if ($sale === null) {
                return;
            }

            if ($sale->status !== SaleStatus::Draft) {
                throw new LogicException('Sale items can only change while the sale is a draft.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'unit' => ProductUnit::class,
            'quantity' => 'decimal:3',
            'original_unit_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function priceOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_overridden_by');
    }
}
