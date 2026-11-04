<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $shop_id
 * @property int|null $customer_id
 * @property int $created_by
 * @property int|null $completed_by
 * @property int $invoice_number
 * @property SaleStatus $status
 * @property PriceType $price_type
 * @property numeric-string $subtotal
 * @property numeric-string $discount_amount
 * @property numeric-string $total_amount
 * @property string|null $notes
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 */
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToShop, HasFactory;

    /**
     * الحقول المالية والحالة مش هنا عمدًا:
     * بتتكتب من SaleService بس (forceFill)، زي stock_qty في Product.
     */
    protected $fillable = [
        'customer_id',
        'price_type',
        'notes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'price_type' => 'retail',
    ];

    protected static function booted(): void
    {
        // الفاتورة المكتملة مقفولة. الاستثناء الوحيد: completed -> cancelled.
        static::updating(function (Sale $sale): void {
            $original = SaleStatus::tryFrom((string) $sale->getRawOriginal('status'));

            if ($original === null || $original === SaleStatus::Draft) {
                return;
            }

            $allowed = ($original === SaleStatus::Completed && $sale->status === SaleStatus::Cancelled)
                ? ['status', 'cancelled_at', 'cancellation_reason', 'updated_at']
                : [];

            if (array_diff(array_keys($sale->getDirty()), $allowed) !== []) {
                throw new LogicException('Completed or cancelled sales cannot be edited.');
            }
        });

        static::deleting(function (Sale $sale): void {
            if ($sale->status !== SaleStatus::Draft) {
                throw new LogicException('Only draft sales can be deleted.');
            }
        });
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'invoice_number' => 'integer',
            'status' => SaleStatus::class,
            'price_type' => PriceType::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<CustomerLedger, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
