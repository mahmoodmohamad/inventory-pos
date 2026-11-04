<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'sale_id',
        'customer_id',
        'received_by',
        'amount',
        'method',
        'reference_number',
        'notes',
        'paid_at',
    ];

    protected static function booted(): void
    {
        // الدفعات append-only. التصحيح بقيد مقابل / refund في Phase 6.
        static::updating(function (): never {
            throw new LogicException('Payments are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Payments cannot be deleted.');
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasOne<CustomerLedger, $this> */
    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(CustomerLedger::class);
    }
}
