<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierPayment extends Model
{
    use BelongsToShop;

    protected $fillable = ['supplier_id', 'purchase_id', 'paid_by', 'amount', 'method', 'notes', 'paid_at'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Supplier payments are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Supplier payments cannot be deleted.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
