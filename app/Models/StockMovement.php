<?php

// app/Models/StockMovement.php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockMovement extends Model
{
    use BelongsToShop;

    public const UPDATED_AT = null;

    /** بيتكتب من StockService بس */
    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity',
        'qty_after',
        'unit_cost',
        'avg_cost_after',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected static function booted(): void
    {
        // الـ ledger append-only
        static::updating(function (): never {
            throw new LogicException('Stock movements are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Stock movements cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'qty_after' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'avg_cost_after' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
