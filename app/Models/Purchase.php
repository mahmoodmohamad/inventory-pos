<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Purchase extends Model
{
    use BelongsToShop;

    /** المجاميع والمستلم والرقم بتتكتب من PurchaseService بس (forceFill). */
    protected $fillable = ['supplier_id', 'supplier_invoice_ref', 'notes'];

    protected static function booted(): void
    {
        // المشتريات المستلمة مقفولة. التصحيح بمرتجع شراء (Phase 6).
        static::updating(function (): never {
            throw new LogicException('Purchases are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Purchases cannot be deleted.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'invoice_number' => 'integer',
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
