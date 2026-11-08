<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseItem extends Model
{
    use BelongsToShop;

    protected $fillable = ['product_id', 'product_name', 'quantity', 'unit_cost', 'line_total'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Purchase items are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Purchase items cannot be deleted.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
