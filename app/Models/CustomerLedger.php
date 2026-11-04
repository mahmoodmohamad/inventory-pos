<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\CustomerLedgerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerLedger extends Model
{
    /** @use HasFactory<CustomerLedgerFactory> */
    use BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'sale_id',
        'payment_id',
        'created_by',
        'source',
        'direction',
        'amount',
        'notes',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Customer ledger entries are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Customer ledger entries cannot be deleted.');
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'source' => LedgerSource::class,
            'direction' => LedgerDirection::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
